<?php

namespace App\Services\Shopify;

use App\Models\Product;
use App\Models\SyncAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 🔍 SHOPIFY PRODUCT DISCOVERY SERVICE
 *
 * Queries Shopify API to discover existing products for linking
 * Provides intelligent matching suggestions based on SKU/name patterns
 */
class ShopifyProductDiscoveryService
{
    private SyncAccount $syncAccount;

    private string $baseUrl;

    private array $headers;

    public function __construct(SyncAccount $syncAccount)
    {
        $this->syncAccount = $syncAccount;
        $this->baseUrl = "https://{$syncAccount->credentials['store_url']}/admin/api/2025-07";
        $this->headers = [
            'X-Shopify-Access-Token' => $syncAccount->credentials['access_token'],
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * 🔍 Discover all Shopify products for potential linking
     */
    public function discoverProducts(int $limit = 50): Collection
    {
        Log::info('🔍 Discovering Shopify products', [
            'store' => $this->syncAccount->credentials['store_url'],
            'limit' => $limit,
        ]);

        try {
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/products.json", [
                    'limit' => $limit,
                    // Remove 'status' parameter to get all products (defaults to active + draft)
                ]);

            if ($response->failed()) {
                Log::error('Failed to discover Shopify products', [
                    'status' => $response->status(),
                    'error' => $response->body(),
                    'full_response' => $response->json(),
                ]);

                return collect();
            }

            $products = $response->json('products', []);

            Log::info('✅ Shopify products discovered', [
                'count' => count($products),
            ]);

            return collect($products)->map(function ($product) {
                return [
                    'id' => $product['id'],
                    'title' => $product['title'],
                    'handle' => $product['handle'],
                    'status' => $product['status'],
                    'created_at' => $product['created_at'],
                    'updated_at' => $product['updated_at'],
                    'vendor' => $product['vendor'] ?? '',
                    'product_type' => $product['product_type'] ?? '',
                    'tags' => $product['tags'] ?? '',
                    'variant_count' => count($product['variants'] ?? []),
                    'variants' => collect($product['variants'] ?? [])->map(function ($variant) {
                        return [
                            'id' => $variant['id'],
                            'sku' => $variant['sku'] ?? '',
                            'title' => $variant['title'] ?? '',
                            'price' => $variant['price'] ?? '0.00',
                        ];
                    })->toArray(),
                ];
            });

        } catch (\Exception $e) {
            Log::error('Exception during Shopify product discovery', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return collect();
        }
    }

    /**
     * 🎯 Find potential matches for a PIM product
     */
    public function findPotentialMatches(Product $product): Collection
    {
        $shopifyProducts = $this->discoverProducts();

        if ($shopifyProducts->isEmpty()) {
            return collect();
        }

        Log::info('🎯 Finding potential matches', [
            'product_sku' => $product->parent_sku,
            'product_name' => $product->name,
            'available_shopify_products' => $shopifyProducts->count(),
        ]);

        return $shopifyProducts->map(function ($shopifyProduct) use ($product) {
            $matchScore = $this->calculateMatchScore($product, $shopifyProduct);

            return array_merge($shopifyProduct, [
                'match_score' => $matchScore,
                'match_reasons' => $this->getMatchReasons($product, $shopifyProduct),
                'suggested_color' => $this->extractSuggestedColor($shopifyProduct['title']),
            ]);
        })->sortByDesc('match_score');
    }

    /**
     * 🔢 Calculate match score between PIM product and Shopify product
     */
    private function calculateMatchScore(Product $product, array $shopifyProduct): int
    {
        $score = 0;
        $parentSku = $product->parent_sku;
        $productName = strtolower($product->name);
        $shopifyTitle = strtolower($shopifyProduct['title']);

        // SKU-based matching (highest priority)
        if (str_contains($shopifyTitle, strtolower($parentSku))) {
            $score += 50;
        }

        // Name-based matching
        $productWords = explode(' ', $productName);
        foreach ($productWords as $word) {
            if (strlen($word) > 3 && str_contains($shopifyTitle, strtolower($word))) {
                $score += 10;
            }
        }

        // Variant SKU matching
        foreach ($shopifyProduct['variants'] as $variant) {
            if (! empty($variant['sku']) && str_contains($variant['sku'], $parentSku)) {
                $score += 30;
            }
        }

        // Product type/category hints
        if (str_contains($shopifyTitle, 'blind') || str_contains($shopifyTitle, 'roller')) {
            $score += 5;
        }

        return $score;
    }

    /**
     * 📝 Get human-readable match reasons
     */
    private function getMatchReasons(Product $product, array $shopifyProduct): array
    {
        $reasons = [];
        $parentSku = $product->parent_sku;
        $shopifyTitle = strtolower($shopifyProduct['title']);

        if (str_contains($shopifyTitle, strtolower($parentSku))) {
            $reasons[] = "SKU '{$parentSku}' found in title";
        }

        foreach ($shopifyProduct['variants'] as $variant) {
            if (! empty($variant['sku']) && str_contains($variant['sku'], $parentSku)) {
                $reasons[] = "SKU '{$parentSku}' matches variant SKU";
                break;
            }
        }

        $productWords = explode(' ', strtolower($product->name));
        foreach ($productWords as $word) {
            if (strlen($word) > 3 && str_contains($shopifyTitle, $word)) {
                $reasons[] = "Product name contains '{$word}'";
            }
        }

        return $reasons;
    }

    /**
     * 🎨 Extract suggested color from Shopify product title
     */
    private function extractSuggestedColor(string $title): ?string
    {
        $colors = [
            'black', 'white', 'grey', 'gray', 'charcoal', 'natural',
            'cream', 'beige', 'brown', 'red', 'blue', 'green',
            'yellow', 'orange', 'pink', 'purple', 'aubergine',
            'lime green', 'dark grey', 'light grey', 'burnt orange',
        ];

        // Sort by length descending to match longer color names first
        usort($colors, fn ($a, $b) => strlen($b) - strlen($a));

        foreach ($colors as $color) {
            if (stripos($title, $color) !== false) {
                return ucwords($color);
            }
        }

        return null;
    }

    /**
     * 🔗 Get products that could be linked to specific colors
     */
    public function getColorLinkingSuggestions(Product $product): Collection
    {
        $potentialMatches = $this->findPotentialMatches($product);
        $productColors = $product->variants->pluck('color')->unique()->filter();

        Log::info('🎨 Generating color linking suggestions', [
            'product_colors' => $productColors->toArray(),
            'potential_matches' => $potentialMatches->count(),
        ]);

        return $productColors->map(function ($color) use ($potentialMatches) {
            $colorMatches = $potentialMatches->filter(function ($shopifyProduct) use ($color) {
                $title = strtolower($shopifyProduct['title']);

                return str_contains($title, strtolower($color));
            });

            return [
                'color' => $color,
                'suggested_products' => $colorMatches->take(3)->values()->toArray(),
                'match_count' => $colorMatches->count(),
            ];
        })->values();
    }
}
