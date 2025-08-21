<?php

namespace App\Services\Shopify;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Shopify SKU-Based Product Grouper
 *
 * Groups Shopify products by extracting parent SKU patterns from variant SKUs.
 * This handles the fact that Shopify splits products by color, but we want
 * them grouped under one parent with multiple color variants.
 */
class ShopifySkuGrouper
{
    /**
     * Group Shopify products by parent SKU extracted from variant SKUs
     */
    public function groupProductsBySkuPattern(Collection $shopifyProducts): Collection
    {
        $groups = collect();

        foreach ($shopifyProducts as $product) {
            $groupInfo = $this->analyzeProductSkus($product);

            if ($groupInfo) {
                $parentSku = $groupInfo['parent_sku'];

                if (! $groups->has($parentSku)) {
                    $groups[$parentSku] = [
                        'parent_sku' => $parentSku,
                        'base_name' => $this->generateBaseName($product, $parentSku),
                        'products' => collect(),
                        'total_variants' => 0,
                        'colors_detected' => collect(),
                        'sku_patterns' => collect(),
                    ];
                }

                // Add product to group - need to get reference to the group first
                $group = $groups[$parentSku];
                $group['products']->push($product);
                $group['total_variants'] += count($product['variants'] ?? []);

                // Track detected colors and patterns for debugging
                $group['colors_detected'] = $group['colors_detected']->merge($groupInfo['colors'])->unique()->sort();
                $group['sku_patterns'] = $group['sku_patterns']->merge($groupInfo['patterns'])->unique();

                // Update the group back in the collection
                $groups[$parentSku] = $group;
            }
        }

        if (function_exists('app') && app()->bound('log')) {
            Log::info('Shopify products grouped by SKU patterns', [
                'total_products' => $shopifyProducts->count(),
                'groups_created' => $groups->count(),
                'parent_skus' => $groups->keys()->toArray(),
            ]);
        }

        return $groups;
    }

    /**
     * Analyze a Shopify product's variant SKUs to extract parent SKU pattern
     */
    private function analyzeProductSkus(array $product): ?array
    {
        $variants = $product['variants'] ?? [];
        $colors = collect();
        $patterns = collect();
        $parentSkuCandidates = collect();

        foreach ($variants as $variant) {
            $sku = $variant['sku'] ?? '';
            if (empty($sku)) {
                continue;
            }

            $patterns->push($sku);

            // Try different SKU patterns to extract parent SKU
            $parentSku = $this->extractParentSku($sku);
            if ($parentSku) {
                $parentSkuCandidates->push($parentSku);

                // Extract color from remaining part of SKU
                $color = $this->extractColorFromSku($sku, $parentSku);
                if ($color) {
                    $colors->push($color);
                }
            }
        }

        // Use the most common parent SKU if multiple candidates
        $mode = $parentSkuCandidates->mode();
        $finalParentSku = is_array($mode) ? $mode[0] ?? $parentSkuCandidates->first() : $mode->first() ?? $parentSkuCandidates->first();

        if ($finalParentSku) {
            return [
                'parent_sku' => $finalParentSku,
                'colors' => $colors->unique()->values(),
                'patterns' => $patterns,
            ];
        }

        return null;
    }

    /**
     * Extract parent SKU from variant SKU using common patterns
     */
    private function extractParentSku(string $sku): ?string
    {
        // Pattern 1: "001-RED-120x160" → "001"
        if (preg_match('/^(\d{3})-/', $sku, $matches)) {
            return $matches[1];
        }

        // Pattern 2: "001-001" → "001" (first part)
        if (preg_match('/^(\d{3})-\d+/', $sku, $matches)) {
            return $matches[1];
        }

        // Pattern 3: "BLIND001-R" → "BLIND001"
        if (preg_match('/^([A-Z]+\d+)-/', $sku, $matches)) {
            return $matches[1];
        }

        // Pattern 4: "RB-001-RED" → "RB-001"
        if (preg_match('/^([A-Z]+-\d+)-/', $sku, $matches)) {
            return $matches[1];
        }

        // Pattern 5: "001RED120" → "001" (3 digits at start)
        if (preg_match('/^(\d{3})/', $sku, $matches)) {
            return $matches[1];
        }

        // Pattern 6: Generic - letters/numbers before first dash
        if (preg_match('/^([A-Z0-9]+)-/', $sku, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract color from SKU by removing parent SKU part
     */
    private function extractColorFromSku(string $sku, string $parentSku): ?string
    {
        // Remove parent SKU and common separators to get remaining part
        $remaining = str_replace($parentSku, '', $sku);
        $remaining = trim($remaining, '-_');

        // Common color abbreviations and full names
        $colorMap = [
            'R' => 'Red', 'RED' => 'Red',
            'B' => 'Blue', 'BLUE' => 'Blue', 'BL' => 'Blue',
            'G' => 'Green', 'GREEN' => 'Green', 'GR' => 'Green',
            'W' => 'White', 'WHITE' => 'White', 'WH' => 'White',
            'BK' => 'Black', 'BLACK' => 'Black',
            'GY' => 'Grey', 'GREY' => 'Grey', 'GRAY' => 'Grey',
            'Y' => 'Yellow', 'YELLOW' => 'Yellow',
            'O' => 'Orange', 'ORANGE' => 'Orange',
            'P' => 'Purple', 'PURPLE' => 'Purple',
            'PK' => 'Pink', 'PINK' => 'Pink',
            'BR' => 'Brown', 'BROWN' => 'Brown',
        ];

        // Check for exact color match
        $upperRemaining = strtoupper($remaining);
        if (isset($colorMap[$upperRemaining])) {
            return $colorMap[$upperRemaining];
        }

        // Check if remaining contains a color (extract first word)
        $firstWord = strtoupper(explode('-', $remaining)[0]);
        if (isset($colorMap[$firstWord])) {
            return $colorMap[$firstWord];
        }

        // Check for full color names in the remaining string
        foreach ($colorMap as $code => $color) {
            if (strlen($code) > 2 && strpos($upperRemaining, $code) !== false) {
                return $color;
            }
        }

        return 'Default';
    }

    /**
     * Generate base product name from Shopify product, removing color if present
     */
    private function generateBaseName(array $product, string $parentSku): string
    {
        $title = $product['title'] ?? "Product {$parentSku}";

        // Try to remove color from title to get base name
        $cleanTitle = preg_replace('/\b(Red|Blue|Green|White|Black|Grey|Gray|Yellow|Orange|Purple|Pink|Brown|Cream|Beige)\b/i', '', $title);
        $cleanTitle = preg_replace('/\s+/', ' ', trim($cleanTitle));

        return $cleanTitle ?: $title;
    }

    /**
     * Get next available parent SKU number for new products
     */
    public function getNextAvailableParentSku(): string
    {
        // This could be enhanced to check existing products in database
        // For now, return a simple incremental number
        $lastNumber = \App\Models\Product::where('parent_sku', 'REGEXP', '^[0-9]{3}$')
            ->selectRaw('CAST(parent_sku AS UNSIGNED) as num')
            ->orderByDesc('num')
            ->value('num') ?? 0;

        $nextNumber = $lastNumber + 1;

        return str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generate sequential variant SKUs for grouped products
     */
    public function generateVariantSkus(string $parentSku, int $variantCount): array
    {
        $skus = [];
        for ($i = 1; $i <= $variantCount; $i++) {
            $variantNumber = str_pad($i, 3, '0', STR_PAD_LEFT);
            $skus[] = "{$parentSku}-{$variantNumber}";
        }

        return $skus;
    }

    /**
     * Get grouping summary for debugging
     */
    public function getGroupingSummary(Collection $groups): array
    {
        return [
            'total_groups' => $groups->count(),
            'groups' => $groups->map(function ($group) {
                return [
                    'parent_sku' => $group['parent_sku'],
                    'base_name' => $group['base_name'],
                    'product_count' => $group['products']->count(),
                    'total_variants' => $group['total_variants'],
                    'colors_detected' => $group['colors_detected']->toArray(),
                    'sample_sku_patterns' => $group['sku_patterns']->take(3)->toArray(),
                ];
            })->values()->toArray(),
        ];
    }
}
