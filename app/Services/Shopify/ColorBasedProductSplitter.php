<?php

namespace App\Services\Shopify;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 🎨 COLOR-BASED PRODUCT SPLITTER
 *
 * Splits PIM products into separate Shopify products based on color variants.
 * Each color becomes its own Shopify product with width/drop as variants.
 *
 * Benefits:
 * - No 100 variant limit per product (each color gets own limit)
 * - Better SEO (each color gets own URL)
 * - Cleaner UX (focused product pages)
 * - Flexible inventory management
 */
class ColorBasedProductSplitter
{
    /**
     * Split a PIM product into color-based Shopify products
     *
     * @param  Product  $product  The PIM product to split
     * @return array<string, array> Array of Shopify product data keyed by color
     */
    public function splitProductByColor(Product $product): array
    {
        Log::info('🎨 Starting color-based product splitting', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'total_variants' => $product->variants->count(),
        ]);

        $startTime = microtime(true);

        // Group variants by color
        $colorGroups = $this->groupVariantsByColor($product->variants);

        if ($colorGroups->isEmpty()) {
            Log::warning('⚠️ No color variants found for product splitting', [
                'product_id' => $product->id,
            ]);

            return [];
        }

        $shopifyProducts = [];

        foreach ($colorGroups as $color => $variants) {
            $shopifyProducts[$color] = $this->createShopifyProductForColor(
                $product,
                $color,
                $variants
            );

            Log::debug('🎨 Created Shopify product for color', [
                'product_id' => $product->id,
                'color' => $color,
                'variants_count' => $variants->count(),
            ]);
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('✅ Color-based product splitting completed', [
            'product_id' => $product->id,
            'colors_created' => count($shopifyProducts),
            'duration_ms' => $duration,
            'colors' => array_keys($shopifyProducts),
        ]);

        return $shopifyProducts;
    }

    /**
     * Group product variants by color
     *
     * @param  Collection<ProductVariant>  $variants
     * @return Collection<string, Collection<ProductVariant>>
     */
    protected function groupVariantsByColor(Collection $variants): Collection
    {
        return $variants->groupBy(function (ProductVariant $variant) {
            // Use the color field, fallback to 'Default' if no color
            return $variant->color ?: 'Default';
        })->filter(function (Collection $colorVariants) {
            // Only keep color groups that have variants
            return $colorVariants->isNotEmpty();
        });
    }

    /**
     * Create Shopify product data for a specific color
     *
     * @param  Product  $product  Base PIM product
     * @param  string  $color  Color name for this Shopify product
     * @param  Collection<ProductVariant>  $variants  Variants for this color
     * @return array Shopify product data
     */
    protected function createShopifyProductForColor(Product $product, string $color, Collection $variants): array
    {
        // Create color-specific product title
        $colorTitle = $this->generateColorSpecificTitle($product->name, $color);

        // Build Shopify variants for this color
        $shopifyVariants = $this->buildShopifyVariants($variants, $color);

        // Determine product options based on available dimensions
        $productOptions = $this->determineProductOptions($variants, $color);

        return [
            'pim_product_id' => $product->id,
            'color' => $color,
            'shopify_title' => $colorTitle,
            'shopify_product_data' => [
                'product' => [
                    'title' => $colorTitle,
                    'body_html' => $this->generateColorSpecificDescription($product, $color),
                    'vendor' => $product->vendor ?? 'Your Store',
                    'product_type' => $product->category ?? 'Window Treatments',
                    'status' => 'draft', // Start as draft for safety
                    'tags' => $this->generateColorSpecificTags($product, $color),
                    'options' => $productOptions,
                    'variants' => $shopifyVariants,
                ],
            ],
            'variants_count' => count($shopifyVariants),
            'metadata' => [
                'original_product_id' => $product->id,
                'color_group' => $color,
                'split_strategy' => 'color_based',
                'generated_at' => now()->toISOString(),
                'dimensions_used' => $this->getDimensionsUsed($variants),
            ],
        ];
    }

    /**
     * Generate color-specific product title
     */
    protected function generateColorSpecificTitle(string $baseTitle, string $color): string
    {
        // Don't add 'Default' to the title
        if ($color === 'Default') {
            return $baseTitle;
        }

        // Check if color is already in the title
        if (stripos($baseTitle, $color) !== false) {
            return $baseTitle;
        }

        return "{$baseTitle} - {$color}";
    }

    /**
     * Generate color-specific description
     */
    protected function generateColorSpecificDescription(Product $product, string $color): string
    {
        $baseDescription = $product->description ?? '';

        if ($color === 'Default') {
            return $baseDescription;
        }

        $colorDescription = "Available in {$color}. ";

        return $colorDescription.$baseDescription;
    }

    /**
     * Generate color-specific tags
     */
    protected function generateColorSpecificTags(Product $product, string $color): array
    {
        $tags = [];

        // Add existing tags if any
        if ($product->tags) {
            $tags = is_array($product->tags) ? $product->tags : explode(',', $product->tags);
        }

        // Add color tag
        if ($color !== 'Default') {
            $tags[] = $color;
            $tags[] = "Color:{$color}";
        }

        // Add product type tags
        if ($product->category) {
            $tags[] = $product->category;
        }

        return array_unique(array_filter($tags));
    }

    /**
     * Build Shopify variants for a specific color group
     */
    protected function buildShopifyVariants(Collection $variants, string $color): array
    {
        $shopifyVariants = [];

        foreach ($variants as $variant) {
            $shopifyVariant = [
                'sku' => $variant->sku,
                'price' => number_format($variant->price ?? 0, 2, '.', ''),
                'inventory_management' => 'shopify',
                'inventory_quantity' => $variant->stock_level ?? 0,
                'option1' => $color, // Color is always option1
            ];

            // Add width as option2 if available
            if ($variant->width) {
                $shopifyVariant['option2'] = "{$variant->width}cm";
            }

            // Add drop as option3 if available
            if ($variant->drop) {
                $shopifyVariant['option3'] = "{$variant->drop}cm";
            }

            // Generate variant title
            $shopifyVariant['title'] = $this->generateVariantTitle($color, $variant);

            $shopifyVariants[] = $shopifyVariant;
        }

        return $shopifyVariants;
    }

    /**
     * Generate variant title
     */
    protected function generateVariantTitle(string $color, ProductVariant $variant): string
    {
        $titleParts = [$color];

        if ($variant->width) {
            $titleParts[] = "{$variant->width}cm";
        }

        if ($variant->drop) {
            $titleParts[] = "{$variant->drop}cm";
        }

        return implode(' / ', $titleParts);
    }

    /**
     * Determine product options based on variants
     */
    protected function determineProductOptions(Collection $variants, string $color): array
    {
        $options = [
            ['name' => 'Color', 'values' => [$color]],
        ];

        // Check if we have width variations
        $hasWidthVariations = $variants->whereNotNull('width')->isNotEmpty();
        if ($hasWidthVariations) {
            $widthValues = $variants->whereNotNull('width')
                ->pluck('width')
                ->unique()
                ->map(fn ($width) => "{$width}cm")
                ->sort()
                ->values()
                ->toArray();

            if (! empty($widthValues)) {
                $options[] = ['name' => 'Width', 'values' => $widthValues];
            }
        }

        // Check if we have drop variations
        $hasDropVariations = $variants->whereNotNull('drop')->isNotEmpty();
        if ($hasDropVariations) {
            $dropValues = $variants->whereNotNull('drop')
                ->pluck('drop')
                ->unique()
                ->map(fn ($drop) => "{$drop}cm")
                ->sort()
                ->values()
                ->toArray();

            if (! empty($dropValues)) {
                $options[] = ['name' => 'Drop', 'values' => $dropValues];
            }
        }

        return $options;
    }

    /**
     * Get dimensions used in variants
     */
    protected function getDimensionsUsed(Collection $variants): array
    {
        return [
            'has_width' => $variants->whereNotNull('width')->isNotEmpty(),
            'has_drop' => $variants->whereNotNull('drop')->isNotEmpty(),
            'width_count' => $variants->whereNotNull('width')->pluck('width')->unique()->count(),
            'drop_count' => $variants->whereNotNull('drop')->pluck('drop')->unique()->count(),
        ];
    }

    /**
     * Get split summary for a product
     *
     * @return array Split analysis summary
     */
    public function getSplitSummary(Product $product): array
    {
        $colorGroups = $this->groupVariantsByColor($product->variants);

        $summary = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'total_variants' => $product->variants->count(),
            'colors_found' => $colorGroups->count(),
            'shopify_products_needed' => $colorGroups->count(),
            'split_recommended' => $colorGroups->count() > 1 || $product->variants->count() > 50,
            'color_breakdown' => [],
        ];

        foreach ($colorGroups as $color => $variants) {
            $summary['color_breakdown'][$color] = [
                'variants_count' => $variants->count(),
                'has_width' => $variants->whereNotNull('width')->isNotEmpty(),
                'has_drop' => $variants->whereNotNull('drop')->isNotEmpty(),
                'width_options' => $variants->whereNotNull('width')->pluck('width')->unique()->count(),
                'drop_options' => $variants->whereNotNull('drop')->pluck('drop')->unique()->count(),
            ];
        }

        return $summary;
    }

    /**
     * Validate if splitting is beneficial
     */
    public function shouldSplitProduct(Product $product): bool
    {
        $colorGroups = $this->groupVariantsByColor($product->variants);

        // Split if multiple colors OR if single color has too many variants
        return $colorGroups->count() > 1 || $product->variants->count() > 80;
    }
}
