<?php

namespace App\Actions\Shopify\Sync;

use App\Actions\Base\BaseAction;
use App\Models\Product;
use App\Models\SyncAccount;
use App\Services\Shopify\ShopifyGraphQLClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 💰 UPDATE SHOPIFY PRICING ACTION
 *
 * Updates pricing for Shopify products based on MarketplaceLinks.
 * Uses GraphQL bulk operations for efficient price updates.
 * Only updates variants for colors that have been linked via MarketplaceLinks.
 */
class UpdateShopifyPricingAction extends BaseAction
{
    private ShopifyGraphQLClient $graphqlClient;

    public function __construct(ShopifyGraphQLClient $graphqlClient)
    {
        parent::__construct();
        $this->graphqlClient = $graphqlClient;
    }

    /**
     * Execute Shopify pricing update
     */
    protected function performAction(...$params): array
    {
        $product = $params[0] ?? null;
        $options = $params[1] ?? [];

        if (!$product instanceof Product) {
            throw new \InvalidArgumentException('First parameter must be a Product instance');
        }

        $syncAccountId = $options['sync_account_id'] ?? null;
        $pricingSource = $options['pricing_source'] ?? 'base_price'; // base_price, channel_price, etc.

        if (!$syncAccountId) {
            return $this->failure('Sync account ID is required');
        }

        $syncAccount = SyncAccount::find($syncAccountId);
        if (!$syncAccount || $syncAccount->channel !== 'shopify') {
            return $this->failure('Valid Shopify sync account is required');
        }

        Log::info('💰 Starting Shopify pricing update', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sync_account' => $syncAccount->name,
            'pricing_source' => $pricingSource,
            'smart_attributes_enabled' => true,
        ]);

        // Log smart attribute values that affect pricing
        $this->logPricingRelevantAttributes($product);

        $startTime = microtime(true);

        try {
            // Step 1: Get MarketplaceLinks for this product and Shopify account
            $marketplaceLinks = $this->getShopifyMarketplaceLinks($product, $syncAccount);

            if ($marketplaceLinks->isEmpty()) {
                return $this->failure('No Shopify MarketplaceLinks found for this product. Link colors first.');
            }

            Log::info('🔗 Found MarketplaceLinks', [
                'product_id' => $product->id,
                'links_count' => $marketplaceLinks->count(),
                'colors' => $marketplaceLinks->pluck('marketplace_data.color_filter')->toArray(),
            ]);

            // Step 2: Group updates by Shopify product (one per color)
            $updatesByShopifyProduct = $this->groupUpdatesByShopifyProduct($product, $marketplaceLinks, $pricingSource);

            if ($updatesByShopifyProduct->isEmpty()) {
                return $this->failure('No variants found to update pricing for');
            }

            // Step 3: Execute pricing updates per Shopify product
            $results = [];
            $totalUpdated = 0;
            $totalFailed = 0;

            foreach ($updatesByShopifyProduct as $shopifyProductId => $colorData) {
                $color = $colorData['color'];
                $variantUpdates = $colorData['variant_updates'];

                Log::info("💰 Updating pricing for color '{$color}'", [
                    'shopify_product_id' => $shopifyProductId,
                    'variants_count' => count($variantUpdates),
                ]);

                $colorResult = $this->updateColorProductPricing($shopifyProductId, $color, $variantUpdates);
                $results[$color] = $colorResult;

                if ($colorResult['success']) {
                    $totalUpdated += $colorResult['updated_count'];
                } else {
                    $totalFailed++;
                }
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Step 4: Create comprehensive result
            $overallSuccess = $totalUpdated > 0 && $totalFailed === 0;

            if ($overallSuccess) {
                Log::info('✅ Shopify pricing update completed successfully', [
                    'product_id' => $product->id,
                    'variants_updated' => $totalUpdated,
                    'colors_updated' => count($results),
                    'duration_ms' => $duration,
                ]);

                return $this->success("Successfully updated pricing for {$totalUpdated} variants across " . count($results) . ' colors', [
                    'method' => 'shopify_pricing_update',
                    'variants_updated' => $totalUpdated,
                    'colors_updated' => count($results),
                    'colors_failed' => $totalFailed,
                    'duration_ms' => $duration,
                    'pricing_source' => $pricingSource,
                    'color_results' => $results,
                ]);
            } else {
                Log::warning('⚠️ Shopify pricing update completed with some failures', [
                    'product_id' => $product->id,
                    'variants_updated' => $totalUpdated,
                    'colors_failed' => $totalFailed,
                    'duration_ms' => $duration,
                ]);

                return $this->failure("Pricing update partially failed: {$totalUpdated} variants updated, {$totalFailed} colors failed", [
                    'variants_updated' => $totalUpdated,
                    'colors_failed' => $totalFailed,
                    'duration_ms' => $duration,
                    'color_results' => $results,
                ]);
            }

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('❌ Shopify pricing update failed with exception', [
                'product_id' => $product->id,
                'duration_ms' => $duration,
                'error' => $e->getMessage(),
            ]);

            return $this->failure('Shopify pricing update failed: ' . $e->getMessage(), [
                'error_type' => get_class($e),
                'duration_ms' => $duration,
            ]);
        }
    }

    /**
     * Get Shopify MarketplaceLinks for the product
     */
    protected function getShopifyMarketplaceLinks(Product $product, SyncAccount $syncAccount): Collection
    {
        return $product->marketplaceLinks()
            ->where('sync_account_id', $syncAccount->id)
            ->where('link_level', 'product')
            ->whereNotNull('marketplace_data->color_filter')
            ->get();
    }

    /**
     * Group pricing updates by Shopify product ID
     */
    protected function groupUpdatesByShopifyProduct(Product $product, Collection $marketplaceLinks, string $pricingSource): Collection
    {
        $updatesByShopifyProduct = collect();

        foreach ($marketplaceLinks as $link) {
            $color = $link->marketplace_data['color_filter'] ?? null;
            $shopifyProductId = $link->external_product_id;

            if (!$color || !$shopifyProductId) {
                Log::warning('⚠️ Skipping MarketplaceLink with missing data', [
                    'link_id' => $link->id,
                    'color' => $color,
                    'shopify_product_id' => $shopifyProductId,
                ]);
                continue;
            }

            // Get variants for this color
            $colorVariants = $product->variants()
                ->where('color', $color)
                ->with(['pricing' => function ($query) use ($link) {
                    $query->where('sales_channel_id', $link->sync_account_id)
                        ->orWhereNull('sales_channel_id'); // Fallback to global pricing
                }])
                ->get();

            if ($colorVariants->isEmpty()) {
                Log::info("⏭️ No variants found for color '{$color}'", [
                    'product_id' => $product->id,
                    'color' => $color,
                ]);
                continue;
            }

            // Build variant updates for this color
            $variantUpdates = $this->buildVariantUpdates($colorVariants, $pricingSource, $shopifyProductId);

            if (!empty($variantUpdates)) {
                $updatesByShopifyProduct->put($shopifyProductId, [
                    'color' => $color,
                    'variant_updates' => $variantUpdates,
                ]);
            }
        }

        return $updatesByShopifyProduct;
    }

    /**
     * Build variant updates array for GraphQL
     */
    protected function buildVariantUpdates(Collection $variants, string $pricingSource, string $shopifyProductId): array
    {
        $variantUpdates = [];

        foreach ($variants as $variant) {
            $price = $this->extractPrice($variant, $pricingSource);
            
            if ($price === null) {
                Log::info("⏭️ Skipping variant with no pricing", [
                    'variant_id' => $variant->id,
                    'variant_sku' => $variant->sku,
                    'pricing_source' => $pricingSource,
                ]);
                continue;
            }

            // For now, we need to fetch Shopify variant IDs
            // This could be optimized by storing them in MarketplaceLinks
            $shopifyVariantId = $this->findShopifyVariantId($shopifyProductId, $variant->sku);

            if (!$shopifyVariantId) {
                Log::warning('⚠️ Could not find Shopify variant ID', [
                    'variant_sku' => $variant->sku,
                    'shopify_product_id' => $shopifyProductId,
                ]);
                continue;
            }

            $variantUpdates[] = [
                'id' => $shopifyVariantId,
                'price' => $price,
                'sku' => $variant->sku,
            ];
        }

        return $variantUpdates;
    }

    /**
     * Extract price from variant based on pricing source
     * Enhanced to use smart attribute system for pricing logic
     */
    protected function extractPrice($variant, string $pricingSource): ?float
    {
        // First try to get from Pricing relationship
        $pricing = $variant->pricing->first();
        
        if ($pricing) {
            $basePrice = match ($pricingSource) {
                'base_price' => $pricing->base_price,
                'channel_price' => $pricing->channel_price ?? $pricing->base_price,
                'sale_price' => $pricing->sale_price ?? $pricing->base_price,
                default => $pricing->base_price,
            };

            // Apply attribute-based pricing modifications if available
            return $this->applyAttributePricingModifications($variant, $basePrice);
        }

        // Fallback to variant price field with attribute modifications
        $basePrice = $variant->price;
        return $this->applyAttributePricingModifications($variant, $basePrice);
    }

    /**
     * Apply pricing modifications based on smart attributes
     */
    protected function applyAttributePricingModifications($variant, ?float $basePrice): ?float
    {
        if ($basePrice === null) {
            return null;
        }

        // Get premium attributes that might affect pricing
        $isPremiumMaterial = $this->isPremiumMaterial($variant);
        $hasSpecialFeatures = $this->hasSpecialFeatures($variant);
        $warrantyYears = $variant->getSmartAttributeValue('warranty_years');

        // Apply material premium
        if ($isPremiumMaterial) {
            $materialMultiplier = $variant->getSmartAttributeValue('material_price_multiplier') ?? 1.15;
            $basePrice *= $materialMultiplier;
        }

        // Apply special features premium
        if ($hasSpecialFeatures) {
            $featureMultiplier = $variant->getSmartAttributeValue('features_price_multiplier') ?? 1.1;
            $basePrice *= $featureMultiplier;
        }

        // Apply warranty premium
        if ($warrantyYears && $warrantyYears > 2) {
            $warrantyMultiplier = 1 + (($warrantyYears - 2) * 0.05); // 5% per year over 2 years
            $basePrice *= $warrantyMultiplier;
        }

        return round($basePrice, 2);
    }

    /**
     * Check if variant has premium materials
     */
    protected function isPremiumMaterial($variant): bool
    {
        $material = $variant->getSmartAttributeValue('material');
        $premiumMaterials = ['silk', 'linen', 'organic cotton', 'bamboo', 'hemp'];
        
        return $material && in_array(strtolower($material), $premiumMaterials);
    }

    /**
     * Check if variant has special features
     */
    protected function hasSpecialFeatures($variant): bool
    {
        return $variant->getSmartAttributeValue('blackout_level') === 'total' ||
               $variant->getSmartAttributeValue('uv_protection') ||
               $variant->getSmartAttributeValue('fire_retardant') ||
               $variant->getSmartAttributeValue('child_safe');
    }

    /**
     * Find Shopify variant ID by SKU within a product
     */
    protected function findShopifyVariantId(string $shopifyProductId, string $sku): ?string
    {
        // Fetch variants for this Shopify product and find matching SKU
        $result = $this->graphqlClient->getProductVariantsWithPricing($shopifyProductId);

        if (!$result['success']) {
            Log::warning('⚠️ Failed to fetch Shopify variants', [
                'shopify_product_id' => $shopifyProductId,
                'error' => $result['error'],
            ]);
            return null;
        }

        foreach ($result['variants'] as $shopifyVariant) {
            if ($shopifyVariant['sku'] === $sku) {
                return $shopifyVariant['id'];
            }
        }

        return null;
    }

    /**
     * Update pricing for a single color product
     */
    protected function updateColorProductPricing(string $shopifyProductId, string $color, array $variantUpdates): array
    {
        if (empty($variantUpdates)) {
            return [
                'success' => false,
                'error' => "No valid variants found to update for color '{$color}'",
                'updated_count' => 0,
            ];
        }

        try {
            $result = $this->graphqlClient->updateProductVariantsPricing($variantUpdates);

            if ($result['success']) {
                Log::info("✅ Successfully updated pricing for color '{$color}'", [
                    'shopify_product_id' => $shopifyProductId,
                    'variants_updated' => $result['updated_count'],
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error("❌ Failed to update pricing for color '{$color}'", [
                'shopify_product_id' => $shopifyProductId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'updated_count' => 0,
            ];
        }
    }

    /**
     * Log pricing-relevant smart attributes for debugging
     */
    protected function logPricingRelevantAttributes(Product $product): void
    {
        $attributes = [
            'brand' => $product->getSmartAttributeValue('brand'),
            'material' => $product->getSmartAttributeValue('material'),
            'warranty_years' => $product->getSmartAttributeValue('warranty_years'),
            'blackout_level' => $product->getSmartAttributeValue('blackout_level'),
            'uv_protection' => $product->getSmartAttributeValue('uv_protection'),
            'fire_retardant' => $product->getSmartAttributeValue('fire_retardant'),
            'child_safe' => $product->getSmartAttributeValue('child_safe'),
        ];

        // Filter out null values
        $attributes = array_filter($attributes, fn($value) => $value !== null);

        if (!empty($attributes)) {
            Log::info('🏷️ Pricing-relevant smart attributes detected', [
                'product_id' => $product->id,
                'attributes' => $attributes,
            ]);
        }
    }
}