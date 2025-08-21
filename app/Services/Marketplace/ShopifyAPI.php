<?php

namespace App\Services\Marketplace;

use App\Models\Product;
use App\Services\Marketplace\Contracts\MarketplaceInterface;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PHPShopify\ShopifySDK;

/**
 * Ultra-simple Shopify API integration
 *
 * Does exactly what you need without overengineering:
 * - Push products (with optional color splitting)
 * - Pull products
 * - Test connection
 */
class ShopifyAPI implements MarketplaceInterface
{
    protected ShopifySDK $sdk;

    public function __construct()
    {
        $this->sdk = new ShopifySDK([
            'ShopUrl' => config('services.shopify.store_url'),
            'AccessToken' => config('services.shopify.access_token'),
            'ApiVersion' => config('services.shopify.api_version', '2024-07'),
        ]);
    }

    /**
     * Push products to Shopify (simple version)
     */
    public function push(array $products): array
    {
        $results = [];

        foreach ($products as $productData) {
            try {
                $result = $this->sdk->Product->post($productData);
                $results[] = [
                    'success' => true,
                    'shopify_id' => $result['id'],
                    'title' => $result['title'],
                ];

                Log::info('Product pushed to Shopify', [
                    'title' => $result['title'],
                    'shopify_id' => $result['id'],
                ]);

            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'title' => $productData['title'] ?? 'Unknown',
                ];

                Log::error('Failed to push product to Shopify', [
                    'title' => $productData['title'] ?? 'Unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Push products with color splitting - your specific requirement
     */
    public function pushWithColors(array $products): array
    {
        $results = [];

        foreach ($products as $product) {
            if ($product instanceof Product) {
                $results = array_merge($results, $this->pushProductWithColors($product));
            } else {
                // Handle array data
                $results[] = [
                    'success' => false,
                    'error' => 'Color splitting requires Product model',
                    'title' => $product['title'] ?? 'Unknown',
                ];
            }
        }

        return $results;
    }

    /**
     * Push a single product split by colors
     */
    protected function pushProductWithColors(Product $product): array
    {
        $results = [];

        // Group variants by color
        $colorGroups = $product->variants->groupBy('color')->filter(function ($variants, $color) {
            return ! empty($color); // Only colors with actual values
        });

        if ($colorGroups->isEmpty()) {
            // No color variants, push as single product
            return $this->push([$this->buildShopifyProductData($product)]);
        }

        // Create separate Shopify product for each color
        foreach ($colorGroups as $color => $variants) {
            try {
                $shopifyData = $this->buildShopifyProductData($product, $color, $variants);
                $expectedTitle = $shopifyData['title'];

                // Check if color-specific product already exists by searching for title
                $existingProduct = $this->findProductByTitle($expectedTitle);

                if ($existingProduct) {
                    // Update existing color product
                    $result = $this->sdk->Product($existingProduct['id'])->put($shopifyData);
                    $action = 'updated';
                    Log::info('Color product updated on Shopify', [
                        'original_product' => $product->name,
                        'color' => $color,
                        'shopify_id' => $result['id'],
                        'variants' => $variants->count(),
                    ]);
                } else {
                    // Create new color product
                    $result = $this->sdk->Product->post($shopifyData);
                    $action = 'created';
                    Log::info('Color product created on Shopify', [
                        'original_product' => $product->name,
                        'color' => $color,
                        'shopify_id' => $result['id'],
                        'variants' => $variants->count(),
                    ]);
                }

                $results[] = [
                    'success' => true,
                    'shopify_id' => $result['id'],
                    'title' => $result['title'],
                    'color' => $color,
                    'variant_count' => $variants->count(),
                    'action' => $action,
                ];

            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'title' => $product->name,
                    'color' => $color,
                ];

                Log::error('Failed to process color product on Shopify', [
                    'product' => $product->name,
                    'color' => $color,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Find product by exact title match on Shopify
     */
    protected function findProductByTitle(string $title): ?array
    {
        try {
            // Search for products with matching title
            $products = $this->sdk->Product->get(['title' => $title, 'limit' => 10]);

            // Return exact title match if found
            foreach ($products as $product) {
                if ($product['title'] === $title) {
                    return $product;
                }
            }

            return null;

        } catch (Exception $e) {
            Log::warning('Could not search for existing product by title', [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Pull products from Shopify
     */
    public function pull(array $filters = []): Collection
    {
        try {
            $params = array_merge(['limit' => 50], $filters);
            $products = $this->sdk->Product->get($params);

            Log::info('Pulled products from Shopify', [
                'count' => count($products),
                'filters' => $filters,
            ]);

            return collect($products);

        } catch (Exception $e) {
            Log::error('Failed to pull products from Shopify', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);

            return collect();
        }
    }

    /**
     * Test connection to Shopify
     */
    public function testConnection(): array
    {
        try {
            $shop = $this->sdk->Shop->get();

            return [
                'success' => true,
                'message' => 'Connected to Shopify successfully',
                'shop_name' => $shop['name'],
                'domain' => $shop['domain'],
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build Shopify product data from Laravel product
     */
    protected function buildShopifyProductData(Product $product, ?string $specificColor = null, ?Collection $specificVariants = null): array
    {
        $variants = $specificVariants ?? $product->variants;
        $title = $specificColor ? "{$product->name} - {$specificColor}" : $product->name;

        $shopifyData = [
            'title' => $title,
            'body_html' => $product->description ?? '',
            'vendor' => $product->vendor ?? config('app.name'),
            'product_type' => $product->product_type ?? 'Product',
            'status' => 'active',
        ];

        // Add variants
        if ($variants->isNotEmpty()) {
            $shopifyData['variants'] = $variants->map(function ($variant) use ($specificColor) {
                // For color-specific products, use dimensions as options instead of color
                if ($specificColor) {
                    return [
                        'sku' => $variant->sku,
                        'price' => $variant->retail_price ?? '0.00',
                        'barcode' => $variant->barcode?->barcode_number,
                        'inventory_quantity' => $variant->stock_level ?? 0,
                        'option1' => $variant->width ? $variant->width.'cm' : 'Standard',
                        'option2' => $variant->drop ? $variant->drop.'cm' : null,
                        'option3' => $variant->size,
                    ];
                } else {
                    // For regular products, use color as first option
                    return [
                        'sku' => $variant->sku,
                        'price' => $variant->retail_price ?? '0.00',
                        'barcode' => $variant->barcode?->barcode_number,
                        'inventory_quantity' => $variant->stock_level ?? 0,
                        'option1' => $variant->color,
                        'option2' => $variant->size,
                        'option3' => $variant->width,
                    ];
                }
            })->toArray();

            // Add options based on whether this is a color-specific product
            if ($specificColor) {
                // For color-specific products, use dimensions as options
                $shopifyData['options'] = [];
                $hasWidths = $variants->pluck('width')->filter()->unique()->count() > 1;
                $hasDrops = $variants->pluck('drop')->filter()->unique()->count() > 1;
                $hasSizes = $variants->pluck('size')->filter()->unique()->count() > 1;

                if ($hasWidths) {
                    $shopifyData['options'][] = ['name' => 'Width'];
                }
                if ($hasDrops) {
                    $shopifyData['options'][] = ['name' => 'Drop'];
                }
                if ($hasSizes) {
                    $shopifyData['options'][] = ['name' => 'Size'];
                }

                // Fallback if no varying options
                if (empty($shopifyData['options'])) {
                    $shopifyData['options'][] = ['name' => 'Size'];
                }
            } else {
                // For regular products, use standard options
                $hasColors = $variants->pluck('color')->filter()->unique()->count() > 1;
                $hasSizes = $variants->pluck('size')->filter()->unique()->count() > 1;
                $hasWidths = $variants->pluck('width')->filter()->unique()->count() > 1;

                if ($hasColors || $hasSizes || $hasWidths) {
                    $shopifyData['options'] = [];
                    if ($hasColors) {
                        $shopifyData['options'][] = ['name' => 'Color'];
                    }
                    if ($hasSizes) {
                        $shopifyData['options'][] = ['name' => 'Size'];
                    }
                    if ($hasWidths) {
                        $shopifyData['options'][] = ['name' => 'Width'];
                    }
                }
            }
        }

        return $shopifyData;
    }
}
