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
 * Each color becomes its own independent Shopify product with size variants
 */
class ShopifyColorSeparationService
{
    /**
     * 🎨 Separate PIM parent product into color-based Shopify products
     *
     * @param  Product  $pimParent  The PIM parent product
     * @return Collection Collection of color-separated Shopify product data
     */
    public function separateByColors(Product $pimParent): Collection
    {
        $variants = $pimParent->variants()->active()->get();

        if ($variants->isEmpty()) {
            return collect();
        }

        // Group all variants by color
        $colorGroups = $variants->groupBy('color');

        return $colorGroups->map(function ($colorVariants, $color) use ($pimParent) {
            return $this->buildShopifyProductForColor($pimParent, $color, $colorVariants);
        })->values();
    }

    /**
     * 🏗️ Build complete Shopify product data for a specific color
     */
    private function buildShopifyProductForColor(Product $pimParent, string $color, Collection $colorVariants): array
    {
        return [
            // Core Shopify Product Data
            'color' => $color,
            'title' => $this->generateColorProductTitle($pimParent, $color),
            'handle' => $this->generateColorProductHandle($pimParent, $color),
            'body_html' => $this->generateColorProductDescription($pimParent, $color),
            'vendor' => $pimParent->brand ?? 'HomeStyle',
            'product_type' => $this->getProductType($pimParent),
            'status' => 'active',
            'published' => true,

            // SEO Data
            'seo_title' => $this->generateSEOTitle($pimParent, $color),
            'seo_description' => $this->generateSEODescription($pimParent, $color),
            'tags' => $this->generateTags($pimParent, $color),

            // Product Options (Size in this case)
            'options' => [
                [
                    'name' => 'Size',
                    'values' => $this->extractSizeOptions($colorVariants),
                ],
            ],

            // Variants (sizes within this color)
            'variants' => $this->buildVariantsForColor($colorVariants),

            // Images (color-specific)
            'images' => $this->buildImagesForColor($pimParent, $color),

            // Metafields
            'metafields' => $this->buildMetafieldsForColor($pimParent, $color, $colorVariants),

            // Analytics Data
            'stats' => $this->calculateColorStats($colorVariants),

            // PIM Reference
            'pim_parent_id' => $pimParent->id,
            'pim_variant_ids' => $colorVariants->pluck('id')->toArray(),
        ];
    }

    /**
     * 🏷️ Generate Shopify product title with color
     */
    private function generateColorProductTitle(Product $pimParent, string $color): string
    {
        $baseTitle = $this->cleanProductTitle($pimParent->name);

        return "{$baseTitle} - {$color}";
    }

    /**
     * 🔗 Generate Shopify product handle (URL slug)
     */
    private function generateColorProductHandle(Product $pimParent, string $color): string
    {
        $baseHandle = Str::slug($pimParent->name);
        $colorSlug = Str::slug($color);

        return "{$baseHandle}-{$colorSlug}";
    }

    /**
     * 📝 Generate color-specific product description
     */
    private function generateColorProductDescription(Product $pimParent, string $color): string
    {
        $baseDescription = $pimParent->description ?? '';

        // Enhance description with color-specific content
        $colorDescription = "<p>Our <strong>{$pimParent->name}</strong> in <strong>{$color}</strong> offers the perfect blend of style and functionality.</p>";

        if ($baseDescription) {
            return $colorDescription.'<p>'.nl2br(e($baseDescription)).'</p>';
        }

        // Generate default description
        return $colorDescription.
               '<p>Available in multiple sizes to fit your windows perfectly. Premium quality materials ensure long-lasting durability and smooth operation.</p>'.
               '<ul><li>Easy to install</li><li>Smooth operation</li><li>Premium materials</li><li>Perfect fit guarantee</li></ul>';
    }

    /**
     * 🏷️ Generate SEO-optimized title
     */
    private function generateSEOTitle(Product $pimParent, string $color): string
    {
        $cleanTitle = $this->cleanProductTitle($pimParent->name);

        return "{$color} {$cleanTitle} | Premium Window Treatments | HomeStyle";
    }

    /**
     * 📄 Generate SEO description
     */
    private function generateSEODescription(Product $pimParent, string $color): string
    {
        return "Shop our premium {$color} {$pimParent->name} - Available in multiple sizes. High-quality window treatments with fast delivery. Perfect for modern homes.";
    }

    /**
     * 🏷️ Generate product tags
     */
    private function generateTags(Product $pimParent, string $color): array
    {
        $tags = [
            strtolower($color),
            'window treatments',
            'home decor',
            'blinds',
            strtolower($pimParent->brand ?? 'premium'),
        ];

        // Add product type specific tags
        $productName = strtolower($pimParent->name);
        if (str_contains($productName, 'blackout')) {
            $tags = array_merge($tags, ['blackout', 'room darkening', 'privacy']);
        }
        if (str_contains($productName, 'thermal')) {
            $tags = array_merge($tags, ['thermal', 'energy efficient', 'insulating']);
        }
        if (str_contains($productName, 'roller')) {
            $tags[] = 'roller blind';
        }

        return array_unique($tags);
    }

    /**
     * 📏 Extract size options for this color
     */
    private function extractSizeOptions(Collection $colorVariants): array
    {
        return $colorVariants
            ->map(function ($variant) {
                if ($variant->drop && $variant->width) {
                    return "{$variant->width}cm x {$variant->drop}cm";
                }

                return "{$variant->width}cm";
            })
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * 💎 Build Shopify variants for this color (different sizes)
     */
    private function buildVariantsForColor(Collection $colorVariants): array
    {
        return $colorVariants->map(function ($variant, $index) {
            return [
                'title' => $this->generateVariantTitle($variant),
                'sku' => $variant->sku,
                'price' => number_format($variant->price, 2, '.', ''),
                'compare_at_price' => null, // Can be set for sales
                'barcode' => $this->getVariantBarcode($variant),
                'inventory_quantity' => $variant->stock_level,
                'inventory_management' => 'shopify',
                'inventory_policy' => 'deny',
                'weight' => $this->calculateWeight($variant),
                'weight_unit' => 'kg',
                'requires_shipping' => true,
                'taxable' => true,
                'position' => $index + 1,
                'option1' => $this->generateVariantTitle($variant), // Size
                'metafields' => $this->buildVariantMetafields($variant),

                // PIM Reference
                'pim_variant_id' => $variant->id,
            ];
        })->toArray();
    }

    /**
     * 🖼️ Build images for this color
     */
    private function buildImagesForColor(Product $pimParent, string $color): array
    {
        $images = [];

        // Add main product image if exists
        if ($pimParent->image_url) {
            $images[] = [
                'src' => $pimParent->image_url,
                'alt' => "{$pimParent->name} in {$color}",
                'position' => 1,
            ];
        }

        // TODO: Add color-specific images from variants or media library

        return $images;
    }

    /**
     * 📋 Build metafields for color product
     */
    private function buildMetafieldsForColor(Product $pimParent, string $color, Collection $colorVariants): array
    {
        return [
            [
                'namespace' => 'pim',
                'key' => 'parent_product_id',
                'value' => (string) $pimParent->id,
                'type' => 'single_line_text_field',
            ],
            [
                'namespace' => 'product',
                'key' => 'color',
                'value' => $color,
                'type' => 'single_line_text_field',
            ],
            [
                'namespace' => 'product',
                'key' => 'variant_count',
                'value' => (string) $colorVariants->count(),
                'type' => 'number_integer',
            ],
            [
                'namespace' => 'product',
                'key' => 'size_range',
                'value' => json_encode([
                    'min_width' => $colorVariants->min('width'),
                    'max_width' => $colorVariants->max('width'),
                    'available_sizes' => $this->extractSizeOptions($colorVariants),
                ]),
                'type' => 'json',
            ],
        ];
    }

    /**
     * 📊 Calculate stats for this color
     */
    private function calculateColorStats(Collection $colorVariants): array
    {
        $prices = $colorVariants->pluck('price')->filter();

        return [
            'variant_count' => $colorVariants->count(),
            'total_inventory' => $colorVariants->sum('stock_level'),
            'price_range' => [
                'min' => $prices->min() ?? 0,
                'max' => $prices->max() ?? 0,
                'formatted' => $this->formatPriceRange($prices),
            ],
            'sizes_available' => $colorVariants->pluck('width')->unique()->count(),
            'avg_price' => $prices->avg() ?? 0,
        ];
    }

    /**
     * 🧹 Clean product title (remove existing color references)
     */
    private function cleanProductTitle(string $title): string
    {
        // Remove any existing color references
        $colorWords = [
            'red', 'blue', 'green', 'white', 'black', 'grey', 'gray', 'brown',
            'yellow', 'pink', 'purple', 'orange', 'charcoal', 'navy', 'cream',
        ];

        foreach ($colorWords as $color) {
            $title = preg_replace('/\b'.preg_quote($color, '/').'\b/i', '', $title);
        }

        // Clean up extra spaces
        return trim(preg_replace('/\s+/', ' ', $title));
    }

    /**
     * 🏷️ Generate variant title (size description)
     */
    private function generateVariantTitle(ProductVariant $variant): string
    {
        if ($variant->drop && $variant->width) {
            return "{$variant->width}cm x {$variant->drop}cm";
        }

        if ($variant->width) {
            return "{$variant->width}cm";
        }

        return 'Standard Size';
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
        $width = $variant->width ?? 100;
        $drop = $variant->drop ?? 150;

        // Simple weight calculation based on size
        $baseWeight = 0.5; // kg
        $sizeMultiplier = ($width * $drop) / (100 * 150);

        return round($baseWeight * $sizeMultiplier, 2);
    }

    /**
     * 🏷️ Get product type for Shopify
     */
    private function getProductType(Product $pimParent): string
    {
        $name = strtolower($pimParent->name);

        if (str_contains($name, 'roller')) {
            return 'Roller Blinds';
        }
        if (str_contains($name, 'venetian')) {
            return 'Venetian Blinds';
        }
        if (str_contains($name, 'vertical')) {
            return 'Vertical Blinds';
        }
        if (str_contains($name, 'roman')) {
            return 'Roman Blinds';
        }
        if (str_contains($name, 'blackout')) {
            return 'Blackout Blinds';
        }
        if (str_contains($name, 'blind')) {
            return 'Window Blinds';
        }

        return 'Window Treatments';
    }

    /**
     * 📋 Build variant metafields
     */
    private function buildVariantMetafields(ProductVariant $variant): array
    {
        $metafields = [
            [
                'namespace' => 'pim',
                'key' => 'variant_id',
                'value' => (string) $variant->id,
                'type' => 'single_line_text_field',
            ],
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
     * 💰 Format price range
     */
    private function formatPriceRange(Collection $prices): string
    {
        if ($prices->isEmpty()) {
            return '£0.00';
        }

        $min = $prices->min();
        $max = $prices->max();

        if ($min === $max) {
            return '£'.number_format($min, 2);
        }

        return '£'.number_format($min, 2).' - £'.number_format($max, 2);
    }

    /**
     * 📊 Get separation summary for PIM parent
     */
    public function getSeparationSummary(Product $pimParent): array
    {
        $colorProducts = $this->separateByColors($pimParent);

        return [
            'pim_parent' => [
                'id' => $pimParent->id,
                'name' => $pimParent->name,
                'total_variants' => $pimParent->variants()->active()->count(),
            ],
            'shopify_products' => $colorProducts->map(function ($colorProduct) {
                return [
                    'color' => $colorProduct['color'],
                    'title' => $colorProduct['title'],
                    'handle' => $colorProduct['handle'],
                    'variants_count' => count($colorProduct['variants']),
                    'price_range' => $colorProduct['stats']['price_range']['formatted'],
                    'inventory' => $colorProduct['stats']['total_inventory'],
                ];
            })->toArray(),
            'summary' => [
                'colors_found' => $colorProducts->count(),
                'total_shopify_products' => $colorProducts->count(),
                'total_variants' => $colorProducts->sum(fn ($cp) => count($cp['variants'])),
                'colors' => $colorProducts->pluck('color')->toArray(),
            ],
        ];
    }

    /**
     * 🔍 Preview separation (without creating)
     */
    public function previewSeparation(Product $pimParent): array
    {
        return $this->getSeparationSummary($pimParent);
    }
}
