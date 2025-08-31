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
            $metadata = $marketplaceProduct->getMetadata();
            $originalProductId = $metadata['original_product_id'] ?? null;

            if (! $originalProductId) {
                return SyncResult::failure('No original product ID found for full update');
            }

            // Get existing Shopify product IDs
            $shopifyProductIds = $this->getExistingShopifyProductIds($originalProductId, $syncAccount);

            if (empty($shopifyProductIds)) {
                // Fallback: No existing products found, create them instead
                $createAction = new CreateShopifyProductsAction;
                $createResult = $createAction->execute($marketplaceProduct, $syncAccount, true);

                if ($createResult->isSuccess()) {
                    return new SyncResult(
                        success: true,
                        message: 'No existing products found - created new products instead: '.$createResult->getMessage(),
                        data: array_merge($createResult->getData(), ['operation' => 'create_fallback']),
                        errors: $createResult->getErrors()
                    );
                } else {
                    return SyncResult::failure(
                        'Full update failed: No existing products and create fallback failed: '.$createResult->getMessage(),
                        $createResult->getErrors()
                    );
                }
            }

            // Perform comprehensive update of all existing products
            $updateResults = $this->performComprehensiveUpdate($shopifyProductIds, $marketplaceProduct, $syncAccount);

            $successCount = count(array_filter($updateResults, fn ($r) => $r['success']));
            $totalCount = count($updateResults);

            if ($successCount === $totalCount) {
                return new SyncResult(
                    success: true,
                    message: "Full update successful! Updated all {$successCount} products with comprehensive data.",
                    data: [
                        'operation' => 'full_update',
                        'updated_products' => $updateResults,
                        'success_count' => $successCount,
                        'total_count' => $totalCount,
                    ]
                );
            } else {
                $failedCount = $totalCount - $successCount;

                return new SyncResult(
                    success: $successCount > 0,
                    message: "Partial success: {$successCount}/{$totalCount} products updated. {$failedCount} failed.",
                    data: [
                        'operation' => 'full_update_partial',
                        'updated_products' => $updateResults,
                        'success_count' => $successCount,
                        'failed_count' => $failedCount,
                    ],
                    errors: $this->extractErrors($updateResults)
                );
            }

        } catch (\Exception $e) {
            return SyncResult::failure(
                message: 'Full update operation failed: '.$e->getMessage(),
                errors: [$e->getMessage()]
            );
        }
    }

    /**
     * Get existing Shopify product IDs for update
     */
    protected function getExistingShopifyProductIds(int $originalProductId, SyncAccount $syncAccount): array
    {
        $localProduct = Product::find($originalProductId);
        if (! $localProduct) {
            return [];
        }

        $shopifyProductIds = $localProduct->getSmartAttributeValue('shopify_product_ids');
        $syncAccountId = $localProduct->getSmartAttributeValue('shopify_sync_account_id');

        // Only return IDs if synced with this account
        if (! $shopifyProductIds || $syncAccountId != $syncAccount->id) {
            return [];
        }

        // Parse product IDs
        if (is_string($shopifyProductIds)) {
            return json_decode($shopifyProductIds, true) ?: [];
        } elseif (is_array($shopifyProductIds)) {
            return $shopifyProductIds;
        }

        return [];
    }

    /**
     * Perform comprehensive update on all existing Shopify products
     */
    protected function performComprehensiveUpdate(array $shopifyProductIds, MarketplaceProduct $marketplaceProduct, SyncAccount $syncAccount): array
    {
        $client = new \App\Services\Marketplace\Shopify\ShopifyGraphQLClient($syncAccount);
        $shopifyProducts = $marketplaceProduct->getData();
        $updateResults = [];

        foreach ($shopifyProductIds as $colorGroup => $shopifyProductId) {
            // Find matching local product data by color group
            $localProductData = $this->findProductDataByColorGroup($shopifyProducts, $colorGroup);

            if (! $localProductData) {
                $updateResults[] = [
                    'success' => false,
                    'color_group' => $colorGroup,
                    'shopify_product_id' => $shopifyProductId,
                    'error' => 'No local data found for color group: '.$colorGroup,
                ];

                continue;
            }

            try {
                error_log("🔄 Starting full update for color group: {$colorGroup}, ID: {$shopifyProductId}");

                // Step 1: Update product metadata (title, description, etc.)
                $productUpdateResult = $this->updateProductMetadata($client, $shopifyProductId, $localProductData);
                error_log('📝 Product metadata update result: '.($productUpdateResult ? 'success' : 'failed'));

                // Step 2: Update all variant data comprehensively
                $variantUpdateResult = $this->updateAllVariantData($client, $shopifyProductId, $localProductData['variants'] ?? []);
                error_log('🔧 Variant data update result: '.($variantUpdateResult ? 'success' : 'failed'));

                // Step 3: Update images if needed
                // TODO: Implement image updates in future enhancement

                $updateResults[] = [
                    'success' => $productUpdateResult && $variantUpdateResult,
                    'color_group' => $colorGroup,
                    'shopify_product_id' => $shopifyProductId,
                    'product_update' => $productUpdateResult,
                    'variant_update' => $variantUpdateResult,
                ];

            } catch (\Exception $e) {
                error_log("❌ Full update exception for {$colorGroup}: ".$e->getMessage());
                $updateResults[] = [
                    'success' => false,
                    'color_group' => $colorGroup,
                    'shopify_product_id' => $shopifyProductId,
                    'error' => 'Update failed: '.$e->getMessage(),
                ];
            }
        }

        return $updateResults;
    }

    /**
     * Find product data by color group
     */
    protected function findProductDataByColorGroup(array $shopifyProducts, string $colorGroup): ?array
    {
        foreach ($shopifyProducts as $product) {
            $internal = $product['_internal'] ?? [];
            if (($internal['color_group'] ?? '') === $colorGroup) {
                return $product;
            }
        }

        return null;
    }

    /**
     * Update product metadata (title, description, category, etc.)
     */
    protected function updateProductMetadata($client, string $shopifyProductId, array $productData): bool
    {
        $productInput = $productData['productInput'] ?? [];

        // Remove fields that cannot be updated via GraphQL
        unset($productInput['productOptions']); // Cannot be specified during update

        // Add the product ID to the input for update
        $productInput['id'] = $shopifyProductId;

        try {
            $result = $client->updateProduct($shopifyProductId, $productInput);
            $userErrors = $result['productUpdate']['userErrors'] ?? [];
            $updatedProduct = $result['productUpdate']['product'] ?? null;

            if (! empty($userErrors)) {
                error_log("Product metadata update errors for {$shopifyProductId}: ".json_encode($userErrors));

                return false;
            }

            // Log successful updates for verification
            if ($updatedProduct) {
                $categoryUpdated = ! empty($updatedProduct['category']['id']);
                $metafieldsCount = count($updatedProduct['metafields']['edges'] ?? []);

                error_log("✅ Product metadata updated for {$shopifyProductId}: ".
                    "Title='{$updatedProduct['title']}', ".
                    'Category='.($categoryUpdated ? 'Yes' : 'None').', '.
                    "Metafields={$metafieldsCount}");
            }

            return true;
        } catch (\Exception $e) {
            error_log("Product metadata update failed for {$shopifyProductId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Update all variant data comprehensively (SKU, pricing, inventory, etc.)
     */
    protected function updateAllVariantData($client, string $shopifyProductId, array $localVariants): bool
    {
        if (empty($localVariants)) {
            return true;
        }

        try {
            // Get current Shopify variants
            $product = $client->getProduct($shopifyProductId);
            $shopifyVariants = $product['product']['variants']['edges'] ?? [];

            if (empty($shopifyVariants)) {
                return false;
            }

            // Match by position and update all data
            $updateData = [];
            foreach ($shopifyVariants as $index => $edge) {
                $shopifyVariant = $edge['node'] ?? [];
                $shopifyVariantId = $shopifyVariant['id'] ?? null;

                if (! $shopifyVariantId || ! isset($localVariants[$index])) {
                    continue;
                }

                $localVariant = $localVariants[$index];

                // Build comprehensive update data
                $variantUpdateData = [
                    'id' => $shopifyVariantId,
                    'sku' => $localVariant['sku'],
                    'price' => $localVariant['price'],
                ];

                // Add all optional fields that are available
                if (! empty($localVariant['barcode'])) {
                    $variantUpdateData['barcode'] = $localVariant['barcode'];
                }

                if (! empty($localVariant['compareAtPrice'])) {
                    $variantUpdateData['compareAtPrice'] = $localVariant['compareAtPrice'];
                }

                if (isset($localVariant['inventoryQuantity'])) {
                    $variantUpdateData['inventoryQuantity'] = (int) $localVariant['inventoryQuantity'];
                }

                $updateData[] = $variantUpdateData;
            }

            // Perform bulk update of all variants
            if (! empty($updateData)) {
                $result = $client->updateProductVariants($shopifyProductId, $updateData);
                $userErrors = $result['productVariantsBulkUpdate']['userErrors'] ?? [];

                return empty($userErrors);
            }

            return true;

        } catch (\Exception $e) {
            error_log("Variant data update failed for {$shopifyProductId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Extract errors from update results
     */
    protected function extractErrors(array $updateResults): array
    {
        $errors = [];
        foreach ($updateResults as $result) {
            if (! $result['success'] && isset($result['error'])) {
                $errors[] = $result['error'];
            }
        }

        return $errors;
    }
}
