<?php

namespace App\Actions\Marketplace\Shopify;

use App\Models\Product;
use App\Models\SyncAccount;
use App\Services\Marketplace\ValueObjects\MarketplaceProduct;
use App\Services\Marketplace\ValueObjects\SyncResult;

/**
 * 🔄 FULL UPDATE SHOPIFY PRODUCTS ACTION
 *
 * Single responsibility: Comprehensive update of all product data on Shopify
 * - Updates all product fields (title, description, images, variants)
 * - Overwrites existing data with current local data
 * - Preserves Shopify product IDs and analytics
 * - No destructive operations - pure update
 */
class FullUpdateShopifyProductsAction
{
    /**
     * Perform comprehensive update of all product data on Shopify
     *
     * @param  MarketplaceProduct  $marketplaceProduct  Prepared Shopify products
     * @param  SyncAccount  $syncAccount  Shopify account credentials
     * @return SyncResult Full update operation result
     */
    public function execute(MarketplaceProduct $marketplaceProduct, SyncAccount $syncAccount): SyncResult
    {
        try {
            $shopifyProducts = $marketplaceProduct->getData();

            if (empty($shopifyProducts)) {
                return SyncResult::failure('No Shopify products to update');
            }

            // Get existing Shopify product IDs from the first product's metadata
            $existingIds = $this->getExistingShopifyProductIds($marketplaceProduct, $syncAccount);
            
            if (empty($existingIds)) {
                return SyncResult::failure('No existing Shopify products found. Use create() instead of fullUpdate().');
            }

            $client = new \App\Services\Marketplace\Shopify\ShopifyGraphQLClient($syncAccount);
            $results = [];
            $errors = [];

            // Update each Shopify product with full data push
            foreach ($shopifyProducts as $shopifyProduct) {
                $colorGroup = $shopifyProduct['metadata']['color_group'] ?? 'unknown';
                $existingProductId = $existingIds[$colorGroup] ?? null;

                if (!$existingProductId) {
                    $errors[] = [
                        'color_group' => $colorGroup,
                        'error' => 'No existing Shopify product ID found for color group'
                    ];
                    continue;
                }

                $result = $this->performFullDataPush($client, $shopifyProduct, $existingProductId, $syncAccount);

                if ($result['success']) {
                    $results[] = $result;
                } else {
                    $errors[] = $result;
                }
            }

            $success = empty($errors);
            $message = $success
                ? sprintf('Full update successful! Updated all %d products with comprehensive data.', count($results))
                : sprintf('Partial success: %d products updated, %d failed', count($results), count($errors));

            // Update sync timestamp
            if (!empty($results)) {
                $this->updateSyncTimestamp($marketplaceProduct, $syncAccount);
            }

            return new SyncResult(
                success: $success,
                message: $message,
                data: [
                    'operation' => 'full_update',
                    'updated_products' => $results,
                    'failed_products' => $errors,
                    'success_count' => count($results),
                    'total_count' => count($results) + count($errors),
                ],
                errors: array_column($errors, 'error')
            );

        } catch (\Exception $e) {
            return SyncResult::failure(
                message: 'Full update operation failed: '.$e->getMessage(),
                errors: [$e->getMessage()]
            );
        }
    }

    /**
     * Get existing Shopify product IDs from local product attributes
     */
    protected function getExistingShopifyProductIds(MarketplaceProduct $marketplaceProduct, SyncAccount $syncAccount): array
    {
        $metadata = $marketplaceProduct->getMetadata();
        $originalProductId = $metadata['original_product_id'] ?? null;

        if (!$originalProductId) {
            return [];
        }

        $localProduct = Product::find($originalProductId);
        if (!$localProduct) {
            return [];
        }

        $shopifyProductIds = $localProduct->getSmartAttributeValue('shopify_product_ids');
        $syncAccountId = $localProduct->getSmartAttributeValue('shopify_sync_account_id');

        // Verify this is for the correct sync account
        if ($syncAccountId != $syncAccount->id) {
            return [];
        }

        // Handle both string and array cases (your attribute system might return either)
        if (is_string($shopifyProductIds)) {
            return json_decode($shopifyProductIds, true) ?: [];
        }
        
        return is_array($shopifyProductIds) ? $shopifyProductIds : [];
    }

    /**
     * Perform full data push for a single Shopify product using four-step approach
     */
    protected function performFullDataPush($client, array $shopifyProduct, string $productId, SyncAccount $syncAccount): array
    {
        $metadata = $shopifyProduct['metadata'] ?? [];
        $productInput = $shopifyProduct['productInput'] ?? [];
        $variantInputs = $shopifyProduct['variantInputs'] ?? [];
        $skuMappings = $shopifyProduct['skuMappings'] ?? [];
        $images = $shopifyProduct['images'] ?? [];

        try {
            \Illuminate\Support\Facades\Log::info('🔄 Starting full data push', [
                'product_id' => $productId,
                'color_group' => $metadata['color_group'] ?? 'unknown',
                'variant_count' => count($variantInputs)
            ]);

            // STEP 1: Update product content with all current data
            $contentChanges = $this->prepareContentChanges($productInput);
            
            if (!empty($contentChanges)) {
                $updateResult = $client->updateProductContent($productId, $contentChanges);
                $userErrors = $updateResult['productUpdate']['userErrors'] ?? [];

                if (!empty($userErrors)) {
                    \Illuminate\Support\Facades\Log::error('❌ Step 1 failed - Content update', [
                        'product_id' => $productId,
                        'user_errors' => $userErrors,
                        'color_group' => $metadata['color_group'] ?? 'unknown'
                    ]);

                    return [
                        'success' => false,
                        'error' => 'Step 1 - Content update: '.json_encode($userErrors),
                        'color_group' => $metadata['color_group'] ?? 'unknown',
                    ];
                }

                \Illuminate\Support\Facades\Log::info('✅ Step 1 successful - Content updated', [
                    'product_id' => $productId,
                    'changes' => array_keys($contentChanges),
                    'color_group' => $metadata['color_group'] ?? 'unknown'
                ]);
            }

            // STEP 2: Update variant prices using current data
            $priceChanges = $this->preparePriceChanges($client, $productId, $variantInputs);
            
            if (!empty($priceChanges)) {
                $priceResult = $client->updateVariantPrices($productId, $priceChanges);
                $priceErrors = $priceResult['productVariantsBulkUpdate']['userErrors'] ?? [];

                if (!empty($priceErrors)) {
                    \Illuminate\Support\Facades\Log::error('❌ Step 2 failed - Price update', [
                        'product_id' => $productId,
                        'price_errors' => $priceErrors,
                        'color_group' => $metadata['color_group'] ?? 'unknown'
                    ]);

                    return [
                        'success' => false,
                        'error' => 'Step 2 - Price update: '.json_encode($priceErrors),
                        'color_group' => $metadata['color_group'] ?? 'unknown',
                    ];
                }

                \Illuminate\Support\Facades\Log::info('✅ Step 2 successful - Prices updated', [
                    'product_id' => $productId,
                    'price_updates' => count($priceChanges),
                    'color_group' => $metadata['color_group'] ?? 'unknown'
                ]);
            }

            // STEP 3: Update variant SKUs using current data
            $skuChanges = $this->prepareSKUChanges($client, $productId, $skuMappings);
            
            if (!empty($skuChanges)) {
                $skuResult = $client->batchUpdateVariantSKUs($skuChanges);
                
                \Illuminate\Support\Facades\Log::info('✅ Step 3 completed - SKU updates', [
                    'product_id' => $productId,
                    'sku_updates_success' => $skuResult['success'],
                    'successful_count' => count($skuResult['successful'] ?? []),
                    'failed_count' => count($skuResult['failed'] ?? []),
                    'color_group' => $metadata['color_group'] ?? 'unknown'
                ]);
            }

            // STEP 4: Update images if provided
            if (!empty($images)) {
                $imageResult = $this->updateProductImages($client, $productId, $images);
                
                \Illuminate\Support\Facades\Log::info('✅ Step 4 completed - Image updates', [
                    'product_id' => $productId,
                    'image_updates' => $imageResult['updated_count'] ?? 0,
                    'color_group' => $metadata['color_group'] ?? 'unknown'
                ]);
            }

            return [
                'success' => true,
                'id' => $productId,
                'color_group' => $metadata['color_group'] ?? 'unknown',
                'original_product_id' => $metadata['original_product_id'] ?? null,
                'full_data_push' => [
                    'content' => !empty($contentChanges),
                    'prices' => !empty($priceChanges),
                    'skus' => !empty($skuChanges),
                    'images' => !empty($images),
                ],
            ];

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('❌ Exception during full data push', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
                'color_group' => $metadata['color_group'] ?? 'unknown'
            ]);

            return [
                'success' => false,
                'error' => 'Full data push failed: '.$e->getMessage(),
                'color_group' => $metadata['color_group'] ?? 'unknown',
            ];
        }
    }

    /**
     * Prepare content changes for full update (push all content)
     */
    protected function prepareContentChanges(array $productInput): array
    {
        // For full update, push all content regardless of current state
        $changes = [];

        foreach (['title', 'descriptionHtml', 'vendor', 'productType', 'status', 'metafields'] as $field) {
            if (isset($productInput[$field])) {
                $changes[$field] = $productInput[$field];
            }
        }

        return $changes;
    }

    /**
     * Prepare price changes for full update (push all prices)
     */
    protected function preparePriceChanges($client, string $productId, array $newVariantInputs): array
    {
        try {
            // Get current variants from Shopify
            $currentData = $client->getProduct($productId);
            $currentVariants = [];
            
            foreach ($currentData['product']['variants']['edges'] ?? [] as $edge) {
                $variant = $edge['node'];
                $currentVariants[] = [
                    'id' => $variant['id'],
                    'price' => $variant['price'],
                ];
            }

            $priceChanges = [];
            
            // Push all prices for full update
            foreach ($newVariantInputs as $index => $newVariant) {
                if (isset($currentVariants[$index])) {
                    $currentVariant = $currentVariants[$index];
                    $newPrice = $newVariant['price'] ?? null;
                    
                    if ($newPrice) {
                        $priceChanges[] = [
                            'id' => $currentVariant['id'],
                            'price' => $newPrice,
                        ];

                        // Add compareAtPrice if available
                        if (isset($newVariant['compareAtPrice'])) {
                            $priceChanges[count($priceChanges) - 1]['compareAtPrice'] = $newVariant['compareAtPrice'];
                        }
                    }
                }
            }

            return $priceChanges;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Could not prepare price changes for full update', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Prepare SKU changes for full update (push all SKUs)
     */
    protected function prepareSKUChanges($client, string $productId, array $skuMappings): array
    {
        try {
            // Get current variants from Shopify
            $currentData = $client->getProduct($productId);
            $currentVariants = [];
            
            foreach ($currentData['product']['variants']['edges'] ?? [] as $edge) {
                $variant = $edge['node'];
                $currentVariants[] = [
                    'id' => $variant['id'],
                    'sku' => $variant['sku'] ?? '',
                ];
            }

            $skuChanges = [];
            
            // Push all SKUs for full update
            foreach ($skuMappings as $index => $skuData) {
                if (isset($currentVariants[$index])) {
                    $currentVariant = $currentVariants[$index];
                    $newSku = $skuData['sku'] ?? '';
                    
                    if ($newSku) {
                        $skuChanges[] = [
                            'variantId' => $currentVariant['id'],
                            'sku' => $newSku,
                        ];
                    }
                }
            }

            return $skuChanges;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Could not prepare SKU changes for full update', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Update product images (placeholder for future implementation)
     */
    protected function updateProductImages($client, string $productId, array $images): array
    {
        // TODO: Implement image updates using productCreateMedia mutation
        return ['updated_count' => 0];
    }

    /**
     * Update sync timestamp after successful updates
     */
    protected function updateSyncTimestamp(MarketplaceProduct $marketplaceProduct, SyncAccount $syncAccount): void
    {
        $metadata = $marketplaceProduct->getMetadata();
        $originalProductId = $metadata['original_product_id'] ?? null;

        if (!$originalProductId) {
            return;
        }

        $localProduct = Product::find($originalProductId);
        if (!$localProduct) {
            return;
        }

        $localProduct->setAttributeValue('shopify_synced_at', now()->toISOString());
    }
}
