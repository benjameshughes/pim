<?php

namespace App\Actions\Marketplace\Shopify;

use App\Models\Product;
use App\Models\SyncAccount;
use App\Services\Marketplace\Shopify\ShopifyTaxonomyHelper;

/**
 * 🔄 TRANSFORM TO SHOPIFY ACTION
 *
 * Transforms color-grouped variants into Shopify GraphQL format.
 * Each color group becomes a separate Shopify product.
 */
class TransformToShopifyAction
{
    protected ?SyncAccount $syncAccount = null;

    /**
     * Transform color groups to Shopify products
     *
     * @param array $colorGroups Color-grouped variants from SplitProductByColourAction
     * @param Product $originalProduct The original product for metadata
     * @param SyncAccount|null $syncAccount For account-specific pricing
     * @return array Array of Shopify product data structures
     */
    public function execute(array $colorGroups, Product $originalProduct, ?SyncAccount $syncAccount = null): array
    {
        $this->syncAccount = $syncAccount;
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
        // Build ProductInput with optional category
        $productInput = [
            'title' => $originalProduct->name . ' - ' . $color,
            'descriptionHtml' => $originalProduct->description ?? '',
            'vendor' => 'Blinds Outlet',
            'productType' => 'Window Blinds',
            'status' => 'ACTIVE',
            'metafields' => $this->createMetafields($originalProduct, $color),
            'productOptions' => $this->createProductOptions($variants),
        ];

        // Add category only if we have a valid taxonomy ID
        $detectedCategory = $this->detectProductCategory($originalProduct, $color);
        if ($detectedCategory && $this->isValidTaxonomyId($detectedCategory)) {
            $productInput['category'] = $detectedCategory;
        }

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
                'barcode' => $this->getVariantBarcode($variant),
                'price' => (string) $this->getVariantPriceForAccount($variant),
                'compareAtPrice' => $this->getCompareAtPrice($variant),
                'inventoryQuantity' => max(0, $variant->stock_level ?? 0),
                'inventoryPolicy' => $this->getInventoryPolicy($variant),
                'inventoryManagement' => 'SHOPIFY',
                'requiresShipping' => true,
                'weight' => $this->calculateVariantWeight($variant),
                'weightUnit' => 'KILOGRAMS',
                'metafields' => $this->createVariantMetafields($variant),
                'options' => [
                    $variant->width . 'cm',
                    $variant->drop . 'cm'
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
        
        // Add variant-specific images for this color
        $variantImages = $this->getVariantSpecificImages($variants, $color);
        $shopifyImages = array_merge($shopifyImages, $variantImages);
        
        return $shopifyImages;
    }

    /**
     * Get variant price for specific sync account (account-specific pricing)
     */
    protected function getVariantPriceForAccount(\App\Models\ProductVariant $variant): float
    {
        if (!$this->syncAccount) {
            // Fallback to default channel pricing
            return $variant->getChannelPrice('shopify');
        }

        // Use account-specific channel code: shopify_main, ebay_blindsoutlet, etc.
        $channelCode = $this->syncAccount->getChannelCode();
        return $variant->getChannelPrice($channelCode);
    }

    /**
     * Create Shopify productOptions (for ProductInput)
     */
    protected function createProductOptions(array $variants): array
    {
        if (empty($variants)) {
            return [];
        }
        
        // Get unique widths and drops from variants
        $widths = [];
        $drops = [];
        foreach ($variants as $variant) {
            $width = $variant->width . 'cm';
            $drop = $variant->drop . 'cm';
            
            if (!in_array($width, $widths)) {
                $widths[] = $width;
            }
            if (!in_array($drop, $drops)) {
                $drops[] = $drop;
            }
        }
        
        // Sort dimensions numerically for better UX
        usort($widths, fn($a, $b) => (int)$a <=> (int)$b);
        usort($drops, fn($a, $b) => (int)$a <=> (int)$b);
        
        return [
            [
                'name' => 'Width',
                'values' => array_map(fn($width) => ['name' => $width], $widths),
            ],
            [
                'name' => 'Drop', 
                'values' => array_map(fn($drop) => ['name' => $drop], $drops),
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

    /**
     * Get variant barcode from barcodes table
     */
    protected function getVariantBarcode(\App\Models\ProductVariant $variant): ?string
    {
        $barcode = $variant->barcode; // HasOne relationship
        return ($barcode && $barcode->is_assigned) ? $barcode->barcode : null;
    }

    /**
     * Determine inventory policy based on stock level
     */
    protected function getInventoryPolicy(\App\Models\ProductVariant $variant): string
    {
        $stockLevel = $variant->stock_level ?? 0;
        
        // CONTINUE = allow orders when out of stock
        // DENY = prevent orders when out of stock
        return $stockLevel > 0 ? 'DENY' : 'DENY'; // Always deny out-of-stock for now
    }

    /**
     * Calculate variant weight with fallback logic
     */
    protected function calculateVariantWeight(\App\Models\ProductVariant $variant): float
    {
        // Use parcel_weight if available
        if ($variant->parcel_weight && $variant->parcel_weight > 0) {
            return (float) $variant->parcel_weight;
        }
        
        // Calculate estimated weight based on dimensions (for blinds)
        $width = $variant->width ?? 60; // cm
        $drop = $variant->drop ?? 100; // cm
        
        // Rough estimate: 0.5kg base + (width * drop * 0.0001) for fabric weight
        $estimatedWeight = 0.5 + (($width * $drop) * 0.0001);
        
        return round($estimatedWeight, 2);
    }

    /**
     * Get variant-specific images for the color group
     */
    protected function getVariantSpecificImages(array $variants, string $color): array
    {
        $variantImages = [];
        $nextPosition = 100; // Start after product images
        
        foreach ($variants as $variant) {
            if ($variant->color !== $color) {
                continue; // Only include variants for this color group
            }
            
            // Get images specific to this variant
            $images = $variant->images()
                ->orderBy('is_primary', 'desc')
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->get();
            
            foreach ($images as $image) {
                $variantImages[] = [
                    'src' => $image->url,
                    'altText' => $image->alt_text ?: "{$variant->title} - {$variant->color}",
                    'position' => $image->sort_order ?: $nextPosition++,
                ];
            }
        }
        
        return $variantImages;
    }

    /**
     * Get compare at price for sales/discount display
     */
    protected function getCompareAtPrice(\App\Models\ProductVariant $variant): ?string
    {
        // Check if variant has a compare_at_price attribute
        $compareAtPrice = $variant->getSmartAttributeValue('compare_at_price');
        
        if ($compareAtPrice && is_numeric($compareAtPrice) && $compareAtPrice > 0) {
            return (string) $compareAtPrice;
        }
        
        // Alternative: Use original price if current price is lower (sale scenario)
        $currentPrice = $this->getVariantPriceForAccount($variant);
        
        // Check for a "original_price" or "msrp" attribute
        $originalPrice = $variant->getSmartAttributeValue('original_price') 
                      ?: $variant->getSmartAttributeValue('msrp')
                      ?: $variant->getSmartAttributeValue('rrp');
        
        if ($originalPrice && is_numeric($originalPrice) && $originalPrice > $currentPrice) {
            return (string) $originalPrice;
        }
        
        return null; // No compare at price
    }

    /**
     * Create variant-specific metafields for custom data
     */
    protected function createVariantMetafields(\App\Models\ProductVariant $variant): array
    {
        $metafields = [];
        
        // Dimensions metafields
        $metafields[] = [
            'namespace' => 'custom',
            'key' => 'width_cm',
            'value' => (string) ($variant->width ?? 0),
            'type' => 'number_integer',
        ];
        
        $metafields[] = [
            'namespace' => 'custom',
            'key' => 'drop_cm',
            'value' => (string) ($variant->drop ?? 0),
            'type' => 'number_integer',
        ];
        
        if ($variant->max_drop) {
            $metafields[] = [
                'namespace' => 'custom',
                'key' => 'max_drop_cm',
                'value' => (string) $variant->max_drop,
                'type' => 'number_integer',
            ];
        }
        
        // Physical dimensions if available
        if ($variant->parcel_length || $variant->parcel_width || $variant->parcel_depth) {
            $metafields[] = [
                'namespace' => 'shipping',
                'key' => 'dimensions_cm',
                'value' => json_encode([
                    'length' => $variant->parcel_length ?? 0,
                    'width' => $variant->parcel_width ?? 0,
                    'depth' => $variant->parcel_depth ?? 0,
                ]),
                'type' => 'json',
            ];
        }
        
        // External SKU if different from main SKU
        if ($variant->external_sku && $variant->external_sku !== $variant->sku) {
            $metafields[] = [
                'namespace' => 'custom',
                'key' => 'external_sku',
                'value' => $variant->external_sku,
                'type' => 'single_line_text_field',
            ];
        }
        
        // Status information
        $metafields[] = [
            'namespace' => 'custom',
            'key' => 'status',
            'value' => $variant->status ?? 'active',
            'type' => 'single_line_text_field',
        ];
        
        return $metafields;
    }

    /**
     * Detect appropriate Shopify taxonomy category for the product
     */
    protected function detectProductCategory(Product $product, string $color): ?string
    {
        // Prepare product data for category detection
        $productData = [
            'title' => $product->name . ' - ' . $color,
            'productType' => 'Window Blinds', // Your product type
            'descriptionHtml' => $product->description ?? '',
            'vendor' => 'Blinds Outlet',
        ];

        // Use the taxonomy helper for smart detection
        $detectedCategory = ShopifyTaxonomyHelper::detectCategory($productData);
        
        // Try blinds-specific detection if general detection fails
        if (!$detectedCategory) {
            $detectedCategory = ShopifyTaxonomyHelper::detectBlindsCategory($productData);
        }
        
        // Log the detection for debugging
        if ($detectedCategory) {
            $categoryName = ShopifyTaxonomyHelper::getCategoryName($detectedCategory);
            \Illuminate\Support\Facades\Log::info('🏷️ Detected Shopify category', [
                'product_name' => $product->name,
                'color' => $color,
                'detected_category_id' => $detectedCategory,
                'category_name' => $categoryName,
                'will_be_used' => $this->isValidTaxonomyId($detectedCategory),
            ]);
        }
        
        return $detectedCategory;
    }

    /**
     * Validate if taxonomy ID is from real Shopify taxonomy
     * 
     * For now, we disable category detection since we're using placeholder IDs.
     * TODO: Replace with real Shopify taxonomy IDs when available.
     */
    protected function isValidTaxonomyId(?string $taxonomyId): bool
    {
        if (!$taxonomyId) {
            return false;
        }
        
        // For now, disable category detection to prevent invalid ID errors
        // TODO: Enable when we have real Shopify taxonomy IDs
        return false;
        
        // Future implementation when we have real IDs:
        // return ShopifyTaxonomyHelper::isValidCategoryId($taxonomyId);
    }
}