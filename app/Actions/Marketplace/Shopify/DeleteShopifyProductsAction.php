<?php

namespace App\Actions\Marketplace\Shopify;

use App\Models\Product;
use App\Models\SyncAccount;
use App\Services\Marketplace\ValueObjects\SyncResult;

/**
 * 🗑️ DELETE SHOPIFY PRODUCTS ACTION
 *
 * Single responsibility: Delete existing products from Shopify
 * - Gets Shopify IDs from stored attributes
 * - Deletes each product individually
 * - Clears attributes after successful deletion
 * - No complex logic - pure deletion
 */
class DeleteShopifyProductsAction
{
    /**
     * Delete existing products from Shopify
     *
     * @param  int  $productId  Local product ID
     * @param  SyncAccount  $syncAccount  Shopify account
     * @return SyncResult Delete operation result
     */
    public function execute(int $productId, SyncAccount $syncAccount): SyncResult
    {
        try {
            $product = Product::find($productId);

            if (! $product) {
                return SyncResult::failure("Product with ID {$productId} not found");
            }

            // Get existing Shopify product IDs from attributes
            $existingProducts = $this->getExistingShopifyProducts($product, $syncAccount);

            if (empty($existingProducts)) {
                return SyncResult::failure('No Shopify products found to delete. Product may not be synced to this account.');
            }

            $client = new \App\Services\Marketplace\Shopify\ShopifyGraphQLClient($syncAccount);
            $deletedProducts = [];
            $errors = [];

            // Delete each Shopify product
            foreach ($existingProducts as $shopifyProductId => $colorGroup) {
                $result = $this->deleteSingleShopifyProduct($client, $shopifyProductId, $colorGroup);

                if ($result['success']) {
                    $deletedProducts[] = $result;
                } else {
                    $errors[] = $result;
                }
            }

            $success = empty($errors);
            $message = $success
                ? sprintf('Successfully deleted %d Shopify products', count($deletedProducts))
                : sprintf('Deleted %d products, %d failed', count($deletedProducts), count($errors));

            // Clear attributes after successful deletions
            if (! empty($deletedProducts)) {
                $this->clearShopifyAttributes($product);
            }

            return new SyncResult(
                success: $success,
                message: $message,
                data: [
                    'deleted' => $deletedProducts,
                    'failed' => $errors,
                    'cleared_attributes' => ! empty($deletedProducts),
                ],
                errors: array_column($errors, 'error')
            );

        } catch (\Exception $e) {
            return SyncResult::failure(
                message: 'Delete operation failed: '.$e->getMessage(),
                errors: [$e->getMessage()]
            );
        }
    }

    /**
     * Get existing Shopify products from stored attributes
     */
    protected function getExistingShopifyProducts(Product $product, SyncAccount $syncAccount): array
    {
        $shopifyProductIds = $product->getSmartAttributeValue('shopify_product_ids');
        $syncAccountId = $product->getSmartAttributeValue('shopify_sync_account_id');
        $status = $product->getSmartAttributeValue('shopify_status');

        // Only delete if synced with this account
        if (! $shopifyProductIds || ! $syncAccountId || $syncAccountId != $syncAccount->id) {
            return [];
        }

        // Parse stored product IDs
        $productIds = [];
        if (is_string($shopifyProductIds)) {
            $productIds = json_decode($shopifyProductIds, true) ?: [];
        } elseif (is_array($shopifyProductIds)) {
            $productIds = $shopifyProductIds;
        }

        if (! is_array($productIds)) {
            return [];
        }

        // Return format: shopify_id => color_group
        $result = [];
        foreach ($productIds as $colorGroup => $shopifyId) {
            $result[$shopifyId] = $colorGroup;
        }

        return $result;
    }

    /**
     * Delete a single Shopify product
     */
    protected function deleteSingleShopifyProduct($client, string $shopifyProductId, string $colorGroup): array
    {
        try {
            // Extract numeric ID for user-friendly display
            $numericId = str_replace('gid://shopify/Product/', '', $shopifyProductId);

            $result = $client->deleteProduct($shopifyProductId);

            $userErrors = $result['productDelete']['userErrors'] ?? [];
            $deletedProductId = $result['productDelete']['deletedProductId'] ?? null;

            if (empty($userErrors) && $deletedProductId) {
                return [
                    'success' => true,
                    'shopify_product_id' => $shopifyProductId,
                    'numeric_id' => $numericId,
                    'color_group' => $colorGroup,
                    'deleted_id' => $deletedProductId,
                    'message' => "Successfully deleted {$colorGroup} product ({$numericId})",
                ];
            } else {
                return [
                    'success' => false,
                    'shopify_product_id' => $shopifyProductId,
                    'numeric_id' => $numericId,
                    'color_group' => $colorGroup,
                    'error' => ! empty($userErrors) ? 'Shopify errors: '.json_encode($userErrors) : 'No deletion confirmation received',
                ];
            }

        } catch (\Exception $e) {
            $numericId = str_replace('gid://shopify/Product/', '', $shopifyProductId);

            return [
                'success' => false,
                'shopify_product_id' => $shopifyProductId,
                'numeric_id' => $numericId,
                'color_group' => $colorGroup,
                'error' => 'Delete failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Clear Shopify attributes after successful deletion
     */
    protected function clearShopifyAttributes(Product $product): void
    {
        $product->setAttributeValue('shopify_product_ids', null);
        $product->setAttributeValue('shopify_sync_account_id', null);
        $product->setAttributeValue('shopify_synced_at', null);
        $product->setAttributeValue('shopify_status', null);
        $product->setAttributeValue('shopify_metadata', null);
    }
}
