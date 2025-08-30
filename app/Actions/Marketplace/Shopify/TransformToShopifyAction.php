<?php

namespace App\Actions\Marketplace\Shopify;

use App\Models\Product;

/**
 * 🔄 TRANSFORM TO SHOPIFY ACTION
 *
 * Transforms color-grouped variants into Shopify GraphQL format.
 * Each color group becomes a separate Shopify product.
 */
class TransformToShopifyAction
{
    /**
     * Transform color groups to Shopify products
     *
     * @param array $colorGroups Color-grouped variants from SplitProductByColourAction
     * @param Product $originalProduct The original product for metadata
     * @return array Array of Shopify product data structures
     */
    public function execute(array $colorGroups, Product $originalProduct): array
    {
        $shopifyProducts = [];

        foreach ($colorGroups as $color => $variants) {
            $shopifyProducts[] = $this->createShopifyProduct($color, $variants, $originalProduct);
        }

        return $shopifyProducts;
    }

    /**
     * Create a single Shopify product from color group
     */
    protected function createShopifyProduct(string $color, array $variants, Product $originalProduct): array
    {
        // Valid ProductInput fields only
        $productInput = [
            'title' => $originalProduct->name . ' - ' . $color,
            'descriptionHtml' => $originalProduct->description ?? '',
            'vendor' => 'Blinds Outlet',
            'productType' => 'Window Blinds',
            'status' => 'ACTIVE',
            'metafields' => $this->createMetafields($originalProduct, $color),
            'productOptions' => $this->createProductOptions($variants),
        ];

        return [
            // Main product data for ProductInput
            'productInput' => $productInput,
            // Additional data for subsequent operations (variants, images handled separately)
            'variants' => $this->transformVariants($variants),
            'images' => $this->getColorImages($originalProduct, $color),
            // Internal tracking
            '_internal' => [
                'original_product_id' => $originalProduct->id,
                'color_group' => $color,
                'variant_count' => count($variants),
            ],
        ];
    }

    /**
     * Transform variants to Shopify format
     */
    protected function transformVariants(array $variants): array
    {
        $shopifyVariants = [];
        
        foreach ($variants as $variant) {
            $shopifyVariants[] = [
                'title' => $variant->title,
                'sku' => $variant->sku,
                'price' => (string) $variant->getChannelPrice('shopify'),
                'inventoryQuantity' => $variant->stock_level ?? 0,
                'inventoryPolicy' => 'DENY',
                'inventoryManagement' => 'SHOPIFY',
                'requiresShipping' => true,
                'weight' => $variant->parcel_weight ?? 0.5,
                'weightUnit' => 'KILOGRAMS',
                'options' => [
                    $variant->color,
                    $variant->width . 'cm x ' . $variant->drop . 'cm'
                ],
            ];
        }
        
        return $shopifyVariants;
    }

    /**
     * Get images for specific color using decoupled images system
     */
    protected function getColorImages(Product $product, string $color): array
    {
        // Use decoupled images() relationship with proper ordering
        $images = $product->images()
            ->orderBy('is_primary', 'desc')  // Primary images first
            ->orderBy('sort_order')          // Then by sort order
            ->orderBy('created_at')          // Finally by creation time
            ->get();
        
        if ($images->isEmpty()) {
            // Fallback to legacy image_url if no decoupled images exist
            if ($product->image_url) {
                return [
                    [
                        'src' => $product->image_url,
                        'altText' => $product->name . ' - ' . $color,
                    ]
                ];
            }
            return [];
        }
        
        // Transform decoupled images to Shopify format
        $shopifyImages = [];
        foreach ($images as $image) {
            $shopifyImages[] = [
                'src' => $image->url,
                'altText' => $image->alt_text ?: $product->name . ' - ' . $color,
                // Optional: Add position based on sort order
                'position' => $image->sort_order,
            ];
        }
        
        return $shopifyImages;
    }

    /**
     * Create Shopify productOptions (for ProductInput)
     */
    protected function createProductOptions(array $variants): array
    {
        if (empty($variants)) {
            return [];
        }
        
        // Get unique sizes from variants
        $sizes = [];
        foreach ($variants as $variant) {
            $size = $variant->width . 'cm x ' . $variant->drop . 'cm';
            if (!in_array($size, $sizes)) {
                $sizes[] = $size;
            }
        }
        
        return [
            [
                'name' => 'Color',
                'values' => [
                    ['name' => $variants[0]->color], // All variants in this group have same color
                ],
            ],
            [
                'name' => 'Size', 
                'values' => array_map(fn($size) => ['name' => $size], $sizes),
            ],
        ];
    }

    /**
     * Create Shopify metafields
     */
    protected function createMetafields(Product $product, string $color): array
    {
        return [
            [
                'namespace' => 'custom',
                'key' => 'parent_sku',
                'value' => $product->parent_sku,
                'type' => 'single_line_text_field',
            ],
            [
                'namespace' => 'custom',
                'key' => 'color_group',
                'value' => $color,
                'type' => 'single_line_text_field',
            ],
        ];
    }
}