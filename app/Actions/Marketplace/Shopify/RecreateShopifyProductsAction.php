<?php

namespace App\Actions\Marketplace\Shopify;

use App\Services\Marketplace\ValueObjects\MarketplaceProduct;
use App\Services\Marketplace\ValueObjects\SyncResult;
use App\Models\SyncAccount;
use App\Models\Product;

/**
 * 🔄 RECREATE SHOPIFY PRODUCTS ACTION
 * 
 * Single responsibility: Recreate products on Shopify (delete + create)
 * - Deletes existing products first (if they exist)
 * - Creates fresh products with current data
 * - Updates attributes with new IDs
 * - No complex logic - pure recreate
 */
class RecreateShopifyProductsAction
{
    /**
     * Recreate products on Shopify (delete existing + create new)
     *
     * @param MarketplaceProduct $marketplaceProduct Prepared Shopify products
     * @param SyncAccount $syncAccount Shopify account credentials
     * @return SyncResult Recreate operation result
     */
    public function execute(MarketplaceProduct $marketplaceProduct, SyncAccount $syncAccount): SyncResult
    {
        try {
            $metadata = $marketplaceProduct->getMetadata();
            $originalProductId = $metadata['original_product_id'] ?? null;

            if (!$originalProductId) {
                return SyncResult::failure('No original product ID found for recreation');
            }

            // Step 1: Delete existing products (if they exist)
            $deleteResult = $this->deleteExistingProducts($originalProductId, $syncAccount);
            
            // Step 2: Create fresh products (force create = true to bypass duplicate check)
            $createAction = new CreateShopifyProductsAction();
            $createResult = $createAction->execute($marketplaceProduct, $syncAccount, true);

            if ($createResult->isSuccess()) {
                $message = $deleteResult['deleted_count'] > 0 
                    ? "Recreated successfully! Deleted {$deleteResult['deleted_count']} old products, created {$this->countCreatedProducts($createResult)} new products."
                    : "Created successfully! " . $createResult->getMessage();

                return new SyncResult(
                    success: true,
                    message: $message,
                    data: array_merge($createResult->getData(), [
                        'recreated' => true,
                        'deleted_products' => $deleteResult['deleted_products'],
                    ]),
                    errors: $createResult->getErrors(),
                    metadata: ['recreated' => true]
                );
            } else {
                return SyncResult::failure(
                    "Recreate failed during create phase: " . $createResult->getMessage(),
                    $createResult->getErrors()
                );
            }

        } catch (\Exception $e) {
            return SyncResult::failure(
                message: 'Recreate operation failed: ' . $e->getMessage(),
                errors: [$e->getMessage()]
            );
        }
    }

    /**
     * Delete existing Shopify products
     */
    protected function deleteExistingProducts(int $originalProductId, SyncAccount $syncAccount): array
    {
        $localProduct = Product::find($originalProductId);
        if (!$localProduct) {
            return ['deleted_count' => 0, 'deleted_products' => []];
        }

        $shopifyProductIds = $localProduct->getSmartAttributeValue('shopify_product_ids');
        $syncAccountId = $localProduct->getSmartAttributeValue('shopify_sync_account_id');

        // Only delete if synced with this account
        if (!$shopifyProductIds || $syncAccountId != $syncAccount->id) {
            return ['deleted_count' => 0, 'deleted_products' => []];
        }

        // Parse product IDs
        $productIds = [];
        if (is_string($shopifyProductIds)) {
            $productIds = json_decode($shopifyProductIds, true) ?: [];
        } elseif (is_array($shopifyProductIds)) {
            $productIds = $shopifyProductIds;
        }

        if (empty($productIds)) {
            return ['deleted_count' => 0, 'deleted_products' => []];
        }

        $client = new \App\Services\Marketplace\Shopify\ShopifyGraphQLClient($syncAccount);
        $deletedProducts = [];
        $deletedCount = 0;

        // Delete each Shopify product
        foreach ($productIds as $colorGroup => $shopifyProductId) {
            try {
                $deleteResult = $client->deleteProduct($shopifyProductId);
                
                // Check if deletion was successful
                $userErrors = $deleteResult['productDelete']['userErrors'] ?? [];
                if (empty($userErrors)) {
                    $deletedProducts[] = [
                        'id' => $shopifyProductId,
                        'color_group' => $colorGroup,
                        'status' => 'deleted'
                    ];
                    $deletedCount++;
                } else {
                    error_log("Failed to delete Shopify product {$shopifyProductId}: " . json_encode($userErrors));
                }
                
            } catch (\Exception $e) {
                error_log("Error deleting Shopify product {$shopifyProductId}: " . $e->getMessage());
            }
        }

        // Clear attributes after deletion
        if ($deletedCount > 0) {
            $localProduct->setAttributeValue('shopify_product_ids', null);
            $localProduct->setAttributeValue('shopify_status', 'pending');
            $localProduct->setAttributeValue('shopify_synced_at', null);
        }

        return [
            'deleted_count' => $deletedCount,
            'deleted_products' => $deletedProducts
        ];
    }

    /**
     * Count created products from create result
     */
    protected function countCreatedProducts(SyncResult $createResult): int
    {
        $data = $createResult->getData();
        return count($data['created_products'] ?? []);
    }
}