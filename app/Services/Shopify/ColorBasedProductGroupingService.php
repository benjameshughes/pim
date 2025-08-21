<?php

namespace App\Services\Shopify;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * 🎨 SHOPIFY COLOR SEPARATION SERVICE
 *
 * Takes 1 PIM Parent Product → Creates Multiple Shopify Products (1 per color)
 * Each color becomes its own Shopify product with size variants
 */
class ShopifyColorSeparationService
{
    /**
     * 🎨 Group product variants by color for Shopify
     *
     * @param  Product  $product  PIM product with variants
     * @return Collection Collection of color groups
     */
    public function groupVariantsByColor(Product $product): Collection
    {
        $variants = $product->variants()->active()->get();

        if ($variants->isEmpty()) {
            return collect();
        }

        return $variants->groupBy('color')->map(function ($colorVariants, $color) use ($product) {
            return [
                'color' => $color,
                'shopify_product_title' => $this->generateColorProductTitle($product, $color),
                'shopify_product_handle' => $this->generateColorProductHandle($product, $color),
                'variants' => $colorVariants->map(function ($variant) {
                    return $this->transformVariantForShopify($variant);
                }),
                'primary_variant' => $this->selectPrimaryVariant($colorVariants),
                'price_range' => $this->calculatePriceRange($colorVariants),
                'total_inventory' => $colorVariants->sum('stock_level'),
                'size_options' => $this->extractSizeOptions($colorVariants),
            ];
        })->values(); // Reset keys to numeric
    }

    /**
     * 🏷️ Generate Shopify product title with color
     */
    public function generateColorProductTitle(Product $product, string $color): string
    {
        $baseTitle = $product->name;

        // Clean up title and add color
        $cleanTitle = $this->cleanProductTitle($baseTitle);

        return "{$cleanTitle} - {$color}";
    }

    /**
     * 🔗 Generate Shopify product handle (URL slug)
     */
    public function generateColorProductHandle(Product $product, string $color): string
    {
        $baseHandle = Str::slug($product->name);
        $colorSlug = Str::slug($color);

        return "{$baseHandle}-{$colorSlug}";
    }

    /**
     * ✨ Transform PIM variant to Shopify variant structure
     */
    public function transformVariantForShopify(ProductVariant $variant): array
    {
        return [
            'pim_variant_id' => $variant->id,
            'title' => $this->generateVariantTitle($variant),
            'sku' => $variant->sku,
            'price' => $this->formatPrice($variant->price),
            'compare_at_price' => null, // Can be set for sales
            'barcode' => $this->getVariantBarcode($variant),
            'inventory_quantity' => $variant->stock_level,
            'weight' => $this->calculateWeight($variant),
            'weight_unit' => 'kg',
            'requires_shipping' => true,
            'taxable' => true,
            'position' => $this->calculatePosition($variant),
            'option1' => $this->extractOption1($variant), // Size
            'option2' => null, // Reserved for future use
            'option3' => null, // Reserved for future use
            'metafields' => $this->generateVariantMetafields($variant),
        ];
    }

    /**
     * 📏 Extract size options for Shopify product options
     */
    public function extractSizeOptions(Collection $variants): array
    {
        return $variants->pluck('width')
            ->unique()
            ->sort()
            ->map(function ($width) {
                return "{$width}cm";
            })
            ->values()
            ->toArray();
    }

    /**
     * 🎯 Select primary variant (typically smallest/most popular size)
     */
    public function selectPrimaryVariant(Collection $variants): ProductVariant
    {
        // Sort by width (smallest first) then by stock level (highest first)
        return $variants->sortBy([
            ['width', 'asc'],
            ['stock_level', 'desc'],
        ])->first();
    }

    /**
     * 💰 Calculate price range for color group
     */
    public function calculatePriceRange(Collection $variants): array
    {
        $prices = $variants->pluck('price')->filter();

        if ($prices->isEmpty()) {
            return ['min' => 0, 'max' => 0, 'formatted' => '£0.00'];
        }

        $min = $prices->min();
        $max = $prices->max();

        return [
            'min' => $min,
            'max' => $max,
            'formatted' => $min === $max
                ? '£'.number_format($min, 2)
                : '£'.number_format($min, 2).' - £'.number_format($max, 2),
        ];
    }

    /**
     * 🏷️ Generate variant title (just the size)
     */
    private function generateVariantTitle(ProductVariant $variant): string
    {
        if ($variant->drop && $variant->width) {
            return "{$variant->width}cm x {$variant->drop}cm";
        }

        if ($variant->width) {
            return "{$variant->width}cm";
        }

        return $variant->sku;
    }

    /**
     * 🔢 Get barcode for variant
     */
    private function getVariantBarcode(ProductVariant $variant): ?string
    {
        return $variant->barcodes()->first()?->barcode;
    }

    /**
     * ⚖️ Calculate weight based on dimensions
     */
    private function calculateWeight(ProductVariant $variant): float
    {
        // Estimate weight based on width and drop
        // This is a placeholder - you can improve this logic
        $width = $variant->width ?? 100;
        $drop = $variant->drop ?? 150;

        // Rough calculation: larger blinds weigh more
        $baseWeight = 0.5; // kg
        $sizeMultiplier = ($width * $drop) / (100 * 150); // Relative to base size

        return round($baseWeight * $sizeMultiplier, 2);
    }

    /**
     * 📍 Calculate variant position
     */
    private function calculatePosition(ProductVariant $variant): int
    {
        // Position based on width (smaller sizes first)
        return $variant->width ?? 999;
    }

    /**
     * 🏷️ Extract Option1 (Size) for Shopify
     */
    private function extractOption1(ProductVariant $variant): string
    {
        return $this->generateVariantTitle($variant);
    }

    /**
     * 📋 Generate metafields for variant
     */
    private function generateVariantMetafields(ProductVariant $variant): array
    {
        $metafields = [];

        // Add PIM reference
        $metafields[] = [
            'namespace' => 'pim',
            'key' => 'variant_id',
            'value' => (string) $variant->id,
            'type' => 'single_line_text_field',
        ];

        // Add dimensions if available
        if ($variant->width && $variant->drop) {
            $metafields[] = [
                'namespace' => 'product',
                'key' => 'dimensions',
                'value' => json_encode([
                    'width' => $variant->width,
                    'drop' => $variant->drop,
                    'unit' => 'cm',
                ]),
                'type' => 'json',
            ];
        }

        return $metafields;
    }

    /**
     * 🧹 Clean product title for Shopify
     */
    private function cleanProductTitle(string $title): string
    {
        // Remove any existing color references
        $colorWords = ['red', 'blue', 'green', 'white', 'black', 'grey', 'gray', 'brown', 'yellow', 'pink', 'purple', 'orange'];

        foreach ($colorWords as $color) {
            $title = preg_replace('/\b'.preg_quote($color, '/').'\b/i', '', $title);
        }

        // Clean up extra spaces
        return trim(preg_replace('/\s+/', ' ', $title));
    }

    /**
     * 💰 Format price for Shopify (remove currency symbol)
     */
    private function formatPrice(float $price): string
    {
        return number_format($price, 2, '.', '');
    }

    /**
     * 📊 Get grouping statistics
     */
    public function getGroupingStats(Product $product): array
    {
        $groups = $this->groupVariantsByColor($product);

        return [
            'total_colors' => $groups->count(),
            'total_variants' => $groups->sum(fn ($group) => $group['variants']->count()),
            'colors' => $groups->pluck('color')->toArray(),
            'size_range' => $this->getSizeRange($product),
            'price_range' => $this->getOverallPriceRange($groups),
        ];
    }

    /**
     * 📏 Get size range across all variants
     */
    private function getSizeRange(Product $product): array
    {
        $variants = $product->variants()->active()->get();
        $widths = $variants->pluck('width')->filter();

        if ($widths->isEmpty()) {
            return ['min' => 0, 'max' => 0];
        }

        return [
            'min' => $widths->min(),
            'max' => $widths->max(),
            'available_sizes' => $widths->unique()->sort()->values()->toArray(),
        ];
    }

    /**
     * 💰 Get overall price range across all color groups
     */
    private function getOverallPriceRange(Collection $groups): array
    {
        $allPrices = $groups->flatMap(function ($group) {
            return [$group['price_range']['min'], $group['price_range']['max']];
        })->filter();

        if ($allPrices->isEmpty()) {
            return ['min' => 0, 'max' => 0, 'formatted' => '£0.00'];
        }

        $min = $allPrices->min();
        $max = $allPrices->max();

        return [
            'min' => $min,
            'max' => $max,
            'formatted' => $min === $max
                ? '£'.number_format($min, 2)
                : '£'.number_format($min, 2).' - £'.number_format($max, 2),
        ];
    }

    /**
     * 🔍 Preview how product will be structured in Shopify
     */
    public function previewShopifyStructure(Product $product): array
    {
        $groups = $this->groupVariantsByColor($product);

        return [
            'pim_product' => [
                'id' => $product->id,
                'name' => $product->name,
                'total_variants' => $product->variants()->active()->count(),
            ],
            'shopify_products' => $groups->map(function ($group) {
                return [
                    'title' => $group['shopify_product_title'],
                    'handle' => $group['shopify_product_handle'],
                    'color' => $group['color'],
                    'variant_count' => $group['variants']->count(),
                    'price_range' => $group['price_range']['formatted'],
                    'total_inventory' => $group['total_inventory'],
                    'sizes' => $group['size_options'],
                ];
            })->toArray(),
            'stats' => $this->getGroupingStats($product),
        ];
    }
}
