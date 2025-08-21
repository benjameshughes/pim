<?php

namespace App\Services\Shopify\PIM;

use App\Models\Product;
use App\Services\Shopify\ColorBasedProductGroupingService;
use Illuminate\Support\Collection;

/**
 * 🔄 PIM TO SHOPIFY TRANSFORMER
 *
 * Pure data transformation class that converts PIM data to Shopify format.
 * Implements color-based product separation for window treatments.
 * No API calls, no business logic - just clean data mapping.
 */
class PimToShopifyTransformer
{
    protected ColorBasedProductGroupingService $colorGroupingService;

    public function __construct()
    {
        $this->colorGroupingService = new ColorBasedProductGroupingService;
    }

    /**
     * 🏗️ TRANSFORM PRODUCTS FOR SHOPIFY
     *
     * Converts PIM Product data to Shopify format with color separation
     *
     * @param  Collection<Product>  $products
     * @param  string  $shopDomain  Target shop domain
     * @param  array<string, mixed>  $options  Transformation options
     * @return array<string, mixed>
     */
    public function transformProducts(Collection $products, string $shopDomain, array $options = []): array
    {
        return $products->map(function (Product $product) use ($shopDomain, $options) {
            return $this->transformSingleProduct($product, $shopDomain, $options);
        })->toArray();
    }

    /**
     * 🎯 TRANSFORM SINGLE PRODUCT
     *
     * Transform one PIM product into multiple Shopify products (color-separated)
     *
     * @param  Product  $product  PIM product
     * @param  string  $shopDomain  Target shop domain
     * @param  array<string, mixed>  $options  Transformation options
     * @return array<string, mixed>
     */
    public function transformSingleProduct(Product $product, string $shopDomain, array $options = []): array
    {
        // Get color groups using existing service
        $colorGroups = $this->colorGroupingService->groupVariantsByColor($product);

        if ($colorGroups->isEmpty()) {
            return [
                'pim_product_id' => $product->id,
                'shopify_products' => [],
                'error' => 'No variants found for color separation',
                'shop_domain' => $shopDomain,
            ];
        }

        // Transform each color group to Shopify product
        $shopifyProducts = $colorGroups->map(function ($colorGroup) use ($product, $shopDomain, $options) {
            return $this->transformColorGroupToShopifyProduct($product, $colorGroup, $shopDomain, $options);
        })->toArray();

        return [
            'pim_product_id' => $product->id,
            'shop_domain' => $shopDomain,
            'shopify_products' => $shopifyProducts,
            'transformation_summary' => [
                'colors_separated' => $colorGroups->count(),
                'total_variants' => $colorGroups->sum(fn ($group) => $group['variants']->count()),
                'transformation_options' => $options,
            ],
        ];
    }

    /**
     * 🎨 TRANSFORM COLOR GROUP TO SHOPIFY PRODUCT
     *
     * Transform a single color group into Shopify product format
     *
     * @param  Product  $product  Original PIM product
     * @param  array<string, mixed>  $colorGroup  Color group data
     * @param  string  $shopDomain  Target shop domain
     * @param  array<string, mixed>  $options  Transformation options
     * @return array<string, mixed>
     */
    protected function transformColorGroupToShopifyProduct(Product $product, array $colorGroup, string $shopDomain, array $options = []): array
    {
        $variants = $colorGroup['variants'];

        // Build main product data
        $shopifyProduct = [
            'title' => $colorGroup['shopify_product_title'],
            'handle' => $colorGroup['shopify_product_handle'],
            'body_html' => $this->buildProductDescription($product, $colorGroup, $options),
            'product_type' => $options['product_type'] ?? 'Window Treatments',
            'vendor' => $product->brand ?? 'Unknown',
            'tags' => $this->buildProductTags($product, $colorGroup, $options),
            'status' => $this->mapProductStatus($product->status, $options),
            'published' => $options['auto_publish'] ?? false,
            'template_suffix' => $options['template_suffix'] ?? null,
        ];

        // Add options (Size for window treatments)
        $shopifyProduct['options'] = [
            [
                'name' => 'Size',
                'position' => 1,
                'values' => $colorGroup['size_options'],
            ],
        ];

        // Transform variants
        $shopifyProduct['variants'] = $variants->map(function ($variantData, $index) use ($colorGroup, $options) {
            return $this->transformVariantToShopify($variantData, $index, $colorGroup, $options);
        })->values()->toArray();

        // Add images
        $shopifyProduct['images'] = $this->buildProductImages($product, $colorGroup, $options);

        // Add metafields
        $shopifyProduct['metafields'] = $this->buildProductMetafields($product, $colorGroup, $options);

        // Add SEO data
        $shopifyProduct['seo'] = [
            'title' => $this->buildSeoTitle($product, $colorGroup),
            'description' => $this->buildSeoDescription($product, $colorGroup),
        ];

        // Add collections if specified
        if (! empty($options['collections'])) {
            $shopifyProduct['collections'] = $options['collections'];
        }

        // Add transformation metadata
        $shopifyProduct['pim_metadata'] = [
            'pim_product_id' => $product->id,
            'color' => $colorGroup['color'],
            'original_title' => $product->name,
            'variant_count' => $variants->count(),
            'price_range' => $colorGroup['price_range'],
            'total_inventory' => $colorGroup['total_inventory'],
            'transformation_timestamp' => now()->toISOString(),
        ];

        return $shopifyProduct;
    }

    /**
     * 🎨 TRANSFORM VARIANT TO SHOPIFY
     *
     * Transform PIM variant data to Shopify variant format
     *
     * @param  array<string, mixed>  $variantData  Variant data from color grouping
     * @param  int  $position  Variant position
     * @param  array<string, mixed>  $colorGroup  Color group data
     * @param  array<string, mixed>  $options  Transformation options
     * @return array<string, mixed>
     */
    protected function transformVariantToShopify(array $variantData, int $position, array $colorGroup, array $options = []): array
    {
        $variant = [
            'title' => $variantData['title'],
            'option1' => $variantData['title'], // Size option
            'option2' => null,
            'option3' => null,
            'sku' => $variantData['sku'],
            'price' => $variantData['price'],
            'compare_at_price' => $variantData['compare_at_price'] ?? null,
            'grams' => $this->convertWeightToGrams($variantData['weight']),
            'inventory_quantity' => $variantData['inventory_quantity'],
            'inventory_management' => 'shopify',
            'inventory_policy' => 'deny', // Don't allow overselling
            'fulfillment_service' => 'manual',
            'requires_shipping' => $variantData['requires_shipping'],
            'taxable' => $variantData['taxable'],
            'barcode' => $variantData['barcode'] ?? null,
            'position' => $position + 1, // Shopify positions start at 1
        ];

        // Add metafields for variant
        $variant['metafields'] = $this->buildVariantMetafields($variantData, $options);

        return $variant;
    }

    /**
     * 📝 BUILD PRODUCT DESCRIPTION
     *
     * Create rich HTML description for Shopify product
     *
     * @param  Product  $product  Original PIM product
     * @param  array<string, mixed>  $colorGroup  Color group data
     * @param  array<string, mixed>  $options  Transformation options
     */
    protected function buildProductDescription(Product $product, array $colorGroup, array $options = []): string
    {
        $description = '';

        // Main title
        $description .= "<h2>{$colorGroup['shopify_product_title']}</h2>";

        // Product description
        if ($product->description) {
            $description .= "<p>{$product->description}</p>";
        }

        // Color highlight
        $description .= '<div style="margin: 15px 0;">';
        $description .= "<strong>Color:</strong> <span style=\"color: #2563eb;\">{$colorGroup['color']}</span>";
        $description .= '</div>';

        // Available sizes
        if (! empty($colorGroup['size_options'])) {
            $description .= '<div style="margin: 15px 0;">';
            $description .= '<strong>Available Sizes:</strong><br>';
            $description .= '<ul style="margin: 10px 0; padding-left: 20px;">';
            foreach ($colorGroup['size_options'] as $size) {
                $description .= "<li>{$size}</li>";
            }
            $description .= '</ul>';
            $description .= '</div>';
        }

        // Price range
        $description .= '<div style="margin: 15px 0;">';
        $description .= "<strong>Price Range:</strong> {$colorGroup['price_range']['formatted']}";
        $description .= '</div>';

        // Features
        if ($product->features) {
            $features = explode(',', $product->features);
            $description .= '<div style="margin: 15px 0;">';
            $description .= '<strong>Features:</strong>';
            $description .= '<ul style="margin: 10px 0; padding-left: 20px;">';
            foreach ($features as $feature) {
                $description .= '<li>'.trim($feature).'</li>';
            }
            $description .= '</ul>';
            $description .= '</div>';
        }

        // Brand information
        if ($product->brand) {
            $description .= '<div style="margin: 15px 0;">';
            $description .= "<strong>Brand:</strong> {$product->brand}";
            $description .= '</div>';
        }

        // Window treatment specific info
        $description .= '<div style="margin: 20px 0; padding: 15px; background-color: #f8fafc; border-left: 4px solid #2563eb;">';
        $description .= '<h4 style="margin: 0 0 10px 0;">Window Treatment Information</h4>';
        $description .= '<p style="margin: 5px 0;">Professional quality window treatments designed for modern homes.</p>';
        $description .= '<p style="margin: 5px 0;">Easy installation with included mounting hardware.</p>';
        $description .= '</div>';

        return $description;
    }

    /**
     * 🏷️ BUILD PRODUCT TAGS
     *
     * Create comprehensive tags for Shopify product
     *
     * @param  Product  $product  Original PIM product
     * @param  array<string, mixed>  $colorGroup  Color group data
     * @param  array<string, mixed>  $options  Transformation options
     * @return array<string>
     */
    protected function buildProductTags(Product $product, array $colorGroup, array $options = []): array
    {
        $tags = [];

        // Core category tags
        $tags[] = 'Window Treatments';
        $tags[] = 'Blinds';
        $tags[] = 'Home Decor';

        // Color tag
        $tags[] = "Color-{$colorGroup['color']}";

        // Brand tag (only if brand is not empty)
        if ($product->brand && trim($product->brand) !== '') {
            $tags[] = "Brand-{$product->brand}";
        }

        // Size range tags
        if (! empty($colorGroup['size_options'])) {
            $tags[] = 'Multiple Sizes';

            // Add specific size tags for common sizes
            $commonSizes = ['60cm', '90cm', '120cm', '150cm'];
            foreach ($colorGroup['size_options'] as $size) {
                if (in_array($size, $commonSizes)) {
                    $tags[] = "Size-{$size}";
                }
            }
        }

        // Price range tags
        $priceRange = $colorGroup['price_range'];
        if ($priceRange['max'] <= 50) {
            $tags[] = 'Budget Friendly';
        } elseif ($priceRange['min'] >= 100) {
            $tags[] = 'Premium';
        } else {
            $tags[] = 'Mid Range';
        }

        // Stock status
        if ($colorGroup['total_inventory'] > 10) {
            $tags[] = 'In Stock';
        } elseif ($colorGroup['total_inventory'] > 0) {
            $tags[] = 'Limited Stock';
        }

        // PIM reference
        $tags[] = "PIM-{$product->id}";

        // Custom tags from options
        if (! empty($options['additional_tags'])) {
            $tags = array_merge($tags, $options['additional_tags']);
        }

        return array_unique($tags);
    }

    /**
     * 📋 BUILD PRODUCT METAFIELDS
     *
     * Create comprehensive metafields for Shopify product
     *
     * @param  Product  $product  Original PIM product
     * @param  array<string, mixed>  $colorGroup  Color group data
     * @param  array<string, mixed>  $options  Transformation options
     * @return array<array<string, mixed>>
     */
    protected function buildProductMetafields(Product $product, array $colorGroup, array $options = []): array
    {
        $metafields = [];

        // PIM reference metafields
        $metafields[] = [
            'namespace' => 'pim',
            'key' => 'product_id',
            'value' => (string) $product->id,
            'type' => 'single_line_text_field',
        ];

        $metafields[] = [
            'namespace' => 'pim',
            'key' => 'parent_sku',
            'value' => $product->parent_sku ?? '',
            'type' => 'single_line_text_field',
        ];

        // Color information
        $metafields[] = [
            'namespace' => 'product',
            'key' => 'color',
            'value' => $colorGroup['color'],
            'type' => 'single_line_text_field',
        ];

        // Product statistics
        $metafields[] = [
            'namespace' => 'product',
            'key' => 'variant_count',
            'value' => (string) $colorGroup['variants']->count(),
            'type' => 'number_integer',
        ];

        // Price range
        $metafields[] = [
            'namespace' => 'product',
            'key' => 'price_range',
            'value' => json_encode($colorGroup['price_range']),
            'type' => 'json',
        ];

        // Size information
        $metafields[] = [
            'namespace' => 'product',
            'key' => 'available_sizes',
            'value' => json_encode($colorGroup['size_options']),
            'type' => 'json',
        ];

        // Product dimensions (from PIM)
        if ($product->length || $product->width || $product->depth) {
            $metafields[] = [
                'namespace' => 'product',
                'key' => 'dimensions',
                'value' => json_encode([
                    'length' => $product->length,
                    'width' => $product->width,
                    'depth' => $product->depth,
                    'unit' => 'cm',
                ]),
                'type' => 'json',
            ];
        }

        // SEO metafields
        $metafields[] = [
            'namespace' => 'seo',
            'key' => 'focus_keyword',
            'value' => strtolower($colorGroup['color'].' window treatments'),
            'type' => 'single_line_text_field',
        ];

        return $metafields;
    }

    /**
     * 📋 BUILD VARIANT METAFIELDS
     *
     * Create metafields for Shopify variant
     *
     * @param  array<string, mixed>  $variantData  Variant data
     * @param  array<string, mixed>  $options  Transformation options
     * @return array<array<string, mixed>>
     */
    protected function buildVariantMetafields(array $variantData, array $options = []): array
    {
        $metafields = [];

        // PIM variant reference
        $metafields[] = [
            'namespace' => 'pim',
            'key' => 'variant_id',
            'value' => (string) $variantData['pim_variant_id'],
            'type' => 'single_line_text_field',
        ];

        // Dimensions from variant data
        if (isset($variantData['width']) && isset($variantData['drop'])) {
            $metafields[] = [
                'namespace' => 'variant',
                'key' => 'dimensions',
                'value' => json_encode([
                    'width' => $variantData['width'],
                    'drop' => $variantData['drop'],
                    'max_drop' => $variantData['max_drop'] ?? null,
                    'unit' => 'cm',
                ]),
                'type' => 'json',
            ];
        }

        // Shipping dimensions
        if (isset($variantData['parcel_length'])) {
            $metafields[] = [
                'namespace' => 'shipping',
                'key' => 'package_dimensions',
                'value' => json_encode([
                    'length' => $variantData['parcel_length'],
                    'width' => $variantData['parcel_width'],
                    'depth' => $variantData['parcel_depth'],
                    'weight' => $variantData['parcel_weight'],
                ]),
                'type' => 'json',
            ];
        }

        return $metafields;
    }

    /**
     * 🖼️ BUILD PRODUCT IMAGES
     *
     * Prepare image data for Shopify product
     *
     * @param  Product  $product  Original PIM product
     * @param  array<string, mixed>  $colorGroup  Color group data
     * @param  array<string, mixed>  $options  Transformation options
     * @return array<array<string, mixed>>
     */
    protected function buildProductImages(Product $product, array $colorGroup, array $options = []): array
    {
        $images = [];

        // Main product image
        if ($product->image_url) {
            $images[] = [
                'src' => $product->image_url,
                'alt' => $colorGroup['shopify_product_title'],
                'position' => 1,
            ];
        }

        // Additional images from options
        if (! empty($options['additional_images'])) {
            foreach ($options['additional_images'] as $index => $imageUrl) {
                $images[] = [
                    'src' => $imageUrl,
                    'alt' => $colorGroup['shopify_product_title'].' - Image '.($index + 2),
                    'position' => $index + 2,
                ];
            }
        }

        return $images;
    }

    /**
     * 🔍 BUILD SEO TITLE
     *
     * Create SEO-optimized title
     *
     * @param  Product  $product  Original PIM product
     * @param  array<string, mixed>  $colorGroup  Color group data
     */
    protected function buildSeoTitle(Product $product, array $colorGroup): string
    {
        $title = $colorGroup['shopify_product_title'];

        // Add category context
        $title .= ' | Window Treatments';

        // Add brand if available
        if ($product->brand) {
            $title .= ' | '.$product->brand;
        }

        // Limit to 60 characters for SEO
        return substr($title, 0, 60);
    }

    /**
     * 🔍 BUILD SEO DESCRIPTION
     *
     * Create SEO-optimized description
     *
     * @param  Product  $product  Original PIM product
     * @param  array<string, mixed>  $colorGroup  Color group data
     */
    protected function buildSeoDescription(Product $product, array $colorGroup): string
    {
        $description = "Shop {$colorGroup['shopify_product_title']} - High-quality window treatments";

        if (! empty($colorGroup['size_options'])) {
            $description .= ' available in '.count($colorGroup['size_options']).' sizes';
        }

        $description .= ". {$colorGroup['price_range']['formatted']}.";

        if ($product->brand) {
            $description .= " By {$product->brand}.";
        }

        $description .= ' Free delivery available.';

        // Limit to 160 characters for SEO
        return substr($description, 0, 160);
    }

    /**
     * 📊 MAP PRODUCT STATUS
     *
     * Map PIM product status to Shopify status
     *
     * @param  mixed  $pimStatus  PIM product status
     * @param  array<string, mixed>  $options  Transformation options
     */
    protected function mapProductStatus($pimStatus, array $options = []): string
    {
        // Default status from options
        if (isset($options['default_status'])) {
            return $options['default_status'];
        }

        // Map PIM status to Shopify
        return match (strtolower((string) $pimStatus)) {
            'active', 'published' => 'active',
            'inactive', 'draft' => 'draft',
            'archived' => 'archived',
            default => 'draft',
        };
    }

    /**
     * ⚖️ CONVERT WEIGHT TO GRAMS
     *
     * Convert weight to grams for Shopify
     *
     * @param  float  $weight  Weight value
     * @param  string  $unit  Weight unit (default: kg)
     * @return int Weight in grams
     */
    protected function convertWeightToGrams(float $weight, string $unit = 'kg'): int
    {
        return match (strtolower($unit)) {
            'kg' => (int) ($weight * 1000),
            'g' => (int) $weight,
            'lb' => (int) ($weight * 453.592),
            'oz' => (int) ($weight * 28.3495),
            default => (int) ($weight * 1000), // Default to kg
        };
    }

    /**
     * 📊 GET TRANSFORMATION STATISTICS
     *
     * Get statistics about the transformation process
     *
     * @param  Collection<Product>  $products  Products to analyze
     * @return array<string, mixed>
     */
    public function getTransformationStatistics(Collection $products): array
    {
        $stats = [
            'total_pim_products' => $products->count(),
            'total_variants' => 0,
            'total_colors' => 0,
            'estimated_shopify_products' => 0,
            'color_distribution' => [],
            'size_distribution' => [],
        ];

        foreach ($products as $product) {
            $colorGroups = $this->colorGroupingService->groupVariantsByColor($product);

            $stats['total_variants'] += $product->variants()->count();
            $stats['total_colors'] += $colorGroups->count();
            $stats['estimated_shopify_products'] += $colorGroups->count();

            // Track color distribution
            foreach ($colorGroups as $group) {
                $color = $group['color'];
                $stats['color_distribution'][$color] = ($stats['color_distribution'][$color] ?? 0) + 1;
            }

            // Track size distribution
            $allSizes = $colorGroups->flatMap(fn ($group) => $group['size_options']);
            foreach ($allSizes as $size) {
                $stats['size_distribution'][$size] = ($stats['size_distribution'][$size] ?? 0) + 1;
            }
        }

        return $stats;
    }

    /**
     * 🎯 PREVIEW TRANSFORMATION
     *
     * Preview how products will be transformed without actually transforming
     *
     * @param  Product  $product  Product to preview
     * @param  string  $shopDomain  Target shop domain
     * @return array<string, mixed>
     */
    public function previewTransformation(Product $product, string $shopDomain): array
    {
        $colorGroups = $this->colorGroupingService->groupVariantsByColor($product);

        return [
            'pim_product' => [
                'id' => $product->id,
                'name' => $product->name,
                'total_variants' => $product->variants()->count(),
            ],
            'shopify_preview' => $colorGroups->map(function ($group) {
                return [
                    'title' => $group['shopify_product_title'],
                    'handle' => $group['shopify_product_handle'],
                    'color' => $group['color'],
                    'variant_count' => $group['variants']->count(),
                    'price_range' => $group['price_range']['formatted'],
                    'sizes' => $group['size_options'],
                    'total_inventory' => $group['total_inventory'],
                ];
            })->toArray(),
            'transformation_summary' => [
                'colors_will_create' => $colorGroups->count(),
                'total_variants' => $colorGroups->sum(fn ($group) => $group['variants']->count()),
                'shop_domain' => $shopDomain,
            ],
        ];
    }
}
