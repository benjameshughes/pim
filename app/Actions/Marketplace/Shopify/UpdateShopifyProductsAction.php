<?php

namespace App\Actions\Marketplace\Shopify;

use App\Models\Product;
use App\Models\SyncAccount;
use App\Services\Marketplace\ValueObjects\SyncResult;

/**
 * ✏️ UPDATE SHOPIFY PRODUCTS ACTION
 *
 * Single responsibility: Update EXISTING products on Shopify
 * - Gets Shopify IDs from stored attributes
 * - Updates specific fields only (title, pricing, images)
 * - Fails clearly if products don't exist
 * - No fallback logic - pure updates
 */
class UpdateShopifyProductsAction
{
    protected ?SyncAccount $syncAccount = null;

    /**
     * Update existing products on Shopify
     *
     * @param  int  $productId  Local product ID
     * @param  array  $fieldsToUpdate  Fields to update (title, pricing, images)
     * @param  SyncAccount  $syncAccount  Shopify account
     * @return SyncResult Update result
     */
    public function execute(int $productId, array $fieldsToUpdate, SyncAccount $syncAccount): SyncResult
    {
        $this->syncAccount = $syncAccount;

        try {
            $product = Product::with(['variants'])->find($productId);

            if (! $product) {
                return SyncResult::failure("Product with ID {$productId} not found");
            }

            // Get existing Shopify product IDs from attributes
            $existingProducts = $this->getExistingShopifyProducts($product, $syncAccount);

            if (empty($existingProducts)) {
                return SyncResult::failure('No existing Shopify products found. Use create() instead of update().');
            }

            $client = new \App\Services\Marketplace\Shopify\ShopifyGraphQLClient($syncAccount);
            $updateResults = [];
            $errors = [];

            // Update each existing Shopify product
            foreach ($existingProducts as $shopifyProductId => $colorGroup) {
                $result = $this->updateSingleShopifyProduct($client, $product, $shopifyProductId, $colorGroup, $fieldsToUpdate);

                if ($result['success']) {
                    $updateResults[] = $result;
                } else {
                    $errors[] = $result;
                }
            }

            $success = empty($errors);
            $message = $success
                ? sprintf('Successfully updated %d Shopify products', count($updateResults))
                : sprintf('Updated %d products, %d failed', count($updateResults), count($errors));

            return new SyncResult(
                success: $success,
                message: $message,
                data: [
                    'updated' => $updateResults,
                    'failed' => $errors,
                    'fields_updated' => array_keys($fieldsToUpdate),
                ],
                errors: array_column($errors, 'error')
            );

        } catch (\Exception $e) {
            return SyncResult::failure(
                message: 'Update operation failed: '.$e->getMessage(),
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

        // Verify this product is synced with the correct account
        if (! $shopifyProductIds || ! $syncAccountId || $syncAccountId != $syncAccount->id || $status !== 'synced') {
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
     * Update a single Shopify product
     */
    protected function updateSingleShopifyProduct($client, Product $product, string $shopifyProductId, string $colorGroup, array $fieldsToUpdate): array
    {
        try {
            $updatedFields = [];

            // Handle title updates
            if (isset($fieldsToUpdate['title'])) {
                $newTitle = $fieldsToUpdate['title'].' - '.$colorGroup;
                $result = $client->updateProductTitle($shopifyProductId, $newTitle);
                $userErrors = $result['productUpdate']['userErrors'] ?? [];

                if (empty($userErrors)) {
                    $updatedFields[] = 'title';
                } else {
                    throw new \Exception('Title update failed: '.json_encode($userErrors));
                }
            }

            // Handle pricing updates - this is the main fix!
            if (isset($fieldsToUpdate['pricing'])) {
                $pricingUpdated = $this->updateProductPricing($client, $product, $shopifyProductId, $colorGroup);
                if ($pricingUpdated) {
                    $updatedFields[] = 'pricing';
                }
            }

            // Handle image updates (placeholder)
            if (isset($fieldsToUpdate['images'])) {
                // TODO: Implement image updates
                $updatedFields[] = 'images';
            }

            return [
                'success' => true,
                'shopify_product_id' => $shopifyProductId,
                'color_group' => $colorGroup,
                'updated_fields' => $updatedFields,
                'message' => empty($updatedFields) ? 'No updates needed' : 'Updated: '.implode(', ', $updatedFields),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'shopify_product_id' => $shopifyProductId,
                'color_group' => $colorGroup,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 🎯 KISS - Update pricing using stored Shopify product ID
     */
    protected function updateProductPricing($client, Product $product, string $shopifyProductId, string $colorGroup): bool
    {
        try {
            // Step 1: Get current Shopify product with variant IDs
            $productData = $client->getProduct($shopifyProductId);
            $shopifyProduct = $productData['product'] ?? null;

            if (! $shopifyProduct) {
                throw new \Exception("Shopify product {$shopifyProductId} not found");
            }

            // Step 2: Get local variants for this color group
            $colorVariants = $product->variants->where('color', $colorGroup);

            if ($colorVariants->isEmpty()) {
                throw new \Exception("No local variants found for color group: {$colorGroup}");
            }

            // Step 3: Update each Shopify variant with correct pricing
            $shopifyVariants = $shopifyProduct['variants']['edges'] ?? [];
            $updatedCount = 0;

            foreach ($shopifyVariants as $index => $edge) {
                $shopifyVariant = $edge['node'] ?? [];
                $shopifyVariantId = $shopifyVariant['id'] ?? null;

                if (! $shopifyVariantId) {
                    continue;
                }

                // Match by position or SKU
                $localVariant = $this->findMatchingLocalVariant($colorVariants, $shopifyVariant, $index);

                if ($localVariant) {
                    $channelPrice = $localVariant->getChannelPrice($this->syncAccount->getChannelCode());

                    $result = $client->updateSingleVariant($shopifyVariantId, [
                        'price' => (string) $channelPrice,
                    ]);

                    $userErrors = $result['productVariantsBulkUpdate']['userErrors'] ?? [];
                    if (empty($userErrors)) {
                        $updatedCount++;
                    }
                }
            }

            return $updatedCount > 0;

        } catch (\Exception $e) {
            error_log("Pricing update failed for product {$shopifyProductId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Find matching local variant (by SKU if available, otherwise by position)
     */
    protected function findMatchingLocalVariant($colorVariants, array $shopifyVariant, int $index)
    {
        $shopifyVariantSku = $shopifyVariant['sku'] ?? null;

        // Try to match by SKU first
        if ($shopifyVariantSku) {
            foreach ($colorVariants as $localVariant) {
                if ($localVariant->sku === $shopifyVariantSku) {
                    return $localVariant;
                }
            }
        }

        // Fallback: match by position
        return $colorVariants->values()->get($index);
    }
}
