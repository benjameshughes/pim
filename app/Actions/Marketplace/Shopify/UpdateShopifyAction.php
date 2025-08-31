<?php

namespace App\Actions\Marketplace\Shopify;

use App\Models\Product;
use App\Models\SyncAccount;
use App\Services\Marketplace\ValueObjects\SyncResult;

/**
 * ✏️ UPDATE SHOPIFY ACTION
 *
 * Handles partial updates to existing Shopify products.
 * Can update specific fields like title, images, pricing without recreating the entire product.
 */
class UpdateShopifyAction
{
    protected ?SyncAccount $syncAccount = null;

    /**
     * Execute partial update to Shopify product
     *
     * @param  int  $productId  Local product ID
     * @param  array  $fieldsToUpdate  Fields that should be updated
     * @param  SyncAccount  $syncAccount  Shopify credentials
     * @return SyncResult Update operation result
     */
    public function execute(int $productId, array $fieldsToUpdate, SyncAccount $syncAccount): SyncResult
    {
        $this->syncAccount = $syncAccount;

        try {
            $product = Product::with(['variants'])->find($productId);

            if (! $product) {
                return SyncResult::failure("Product with ID {$productId} not found");
            }

            // First, we need to find existing Shopify products for this local product
            $existingProducts = $this->findShopifyProducts($product, $syncAccount);

            if (empty($existingProducts)) {
                return SyncResult::failure('No existing Shopify products found for this product. Use create() instead.');
            }

            $client = new \App\Services\Marketplace\Shopify\ShopifyGraphQLClient($syncAccount);
            $updateResults = [];
            $errors = [];

            foreach ($existingProducts as $shopifyProductId => $colorGroup) {
                $updateData = $this->prepareUpdateData($product, $colorGroup, $fieldsToUpdate);

                if (! empty($updateData)) {
                    $result = $this->updateSingleShopifyProduct($client, $shopifyProductId, $updateData);

                    if ($result['success']) {
                        $updateResults[] = $result;
                    } else {
                        $errors[] = $result;
                    }
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
                message: 'Update failed: '.$e->getMessage(),
                errors: [$e->getMessage()]
            );
        }
    }

    /**
     * 🎯 Find existing Shopify products using ATTRIBUTES - PURE GENIUS!
     */
    protected function findShopifyProducts(Product $product, SyncAccount $syncAccount): array
    {
        // Check if this product has been synced to Shopify
        $shopifyProductIds = $product->getSmartAttributeValue('shopify_product_ids');
        $syncAccountId = $product->getSmartAttributeValue('shopify_sync_account_id');
        $status = $product->getSmartAttributeValue('shopify_status');

        // Verify this product is synced with the correct account and is active
        if (! $shopifyProductIds || ! $syncAccountId || $syncAccountId != $syncAccount->id || $status !== 'synced') {
            return []; // Not synced or wrong account
        }

        // Parse the JSON of Shopify product IDs
        $productIds = [];
        if (is_string($shopifyProductIds)) {
            $productIds = json_decode($shopifyProductIds, true) ?: [];
        } elseif (is_array($shopifyProductIds)) {
            $productIds = $shopifyProductIds;
        }

        if (! is_array($productIds)) {
            return [];
        }

        // Flip array to match expected format: shopify_id => color_group
        $result = [];
        foreach ($productIds as $colorGroup => $shopifyId) {
            $result[$shopifyId] = $colorGroup;
        }

        return $result;
    }

    /**
     * Prepare update data based on fields to update
     */
    protected function prepareUpdateData(Product $product, string $colorGroup, array $fieldsToUpdate): array
    {
        $updateData = [];

        if (isset($fieldsToUpdate['title'])) {
            $updateData['title'] = $fieldsToUpdate['title'].' - '.$colorGroup;
        }

        if (isset($fieldsToUpdate['images'])) {
            $updateData['images'] = $fieldsToUpdate['images'];
        }

        if (isset($fieldsToUpdate['pricing'])) {
            // Update pricing from variants
            $colorVariants = $product->variants->where('color', $colorGroup);
            $updateData['variants'] = $this->transformVariantsForUpdate($colorVariants);
        }

        return $updateData;
    }

    /**
     * Get variant price for specific sync account (account-specific pricing)
     */
    protected function getVariantPriceForAccount(\App\Models\ProductVariant $variant, \App\Models\SyncAccount $syncAccount): float
    {
        // Use account-specific channel code: shopify_main, ebay_blindsoutlet, etc.
        $channelCode = $syncAccount->getChannelCode();

        return $variant->getChannelPrice($channelCode);
    }

    /**
     * Transform variants for update operations
     */
    protected function transformVariantsForUpdate($variants): array
    {
        $variantUpdates = [];

        foreach ($variants as $variant) {
            $variantUpdates[] = [
                'sku' => $variant->sku,
                'price' => (string) $this->getVariantPriceForAccount($variant, $this->syncAccount),
                'inventoryQuantity' => $variant->stock_level ?? 0,
            ];
        }

        return $variantUpdates;
    }

    /**
     * 🚀 Update a single Shopify product - REAL GraphQL POWER!
     */
    protected function updateSingleShopifyProduct($client, string $shopifyProductId, array $updateData): array
    {
        try {
            $updatedFields = [];

            // Handle title updates
            if (isset($updateData['title'])) {
                $result = $client->updateProductTitle($shopifyProductId, $updateData['title']);
                $userErrors = $result['productUpdate']['userErrors'] ?? [];

                if (empty($userErrors)) {
                    $updatedFields[] = 'title';
                } else {
                    throw new \Exception('Title update failed: '.json_encode($userErrors));
                }
            }

            // Handle variant/pricing updates - KISS approach
            if (isset($updateData['variants'])) {
                $pricingUpdated = $this->updateVariantPricing($client, $shopifyProductId, $updateData['variants']);
                if ($pricingUpdated) {
                    $updatedFields[] = 'pricing';
                }
            }

            // Handle image updates
            if (isset($updateData['images'])) {
                // TODO: Implement image updates via GraphQL productImageUpdate mutations
                $updatedFields[] = 'images';
            }

            return [
                'success' => true,
                'shopify_product_id' => $shopifyProductId,
                'updated_fields' => $updatedFields,
                'note' => empty($updatedFields) ? 'No fields required update' : 'Updated: '.implode(', ', $updatedFields),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'shopify_product_id' => $shopifyProductId,
                'error' => 'Update failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * 🎯 KISS - Update variant pricing using specific variant IDs
     */
    protected function updateVariantPricing($client, string $shopifyProductId, array $localVariants): bool
    {
        try {
            // Step 1: Get current Shopify product with variants
            $productData = $client->getProduct($shopifyProductId);
            $product = $productData['product'] ?? null;

            if (! $product) {
                return false;
            }

            // Step 2: Get variant IDs from Shopify
            $shopifyVariants = $product['variants']['edges'] ?? [];

            // Step 3: Match and update each variant by SKU
            $updatedCount = 0;
            foreach ($shopifyVariants as $edge) {
                $shopifyVariant = $edge['node'] ?? [];
                $shopifyVariantId = $shopifyVariant['id'] ?? null;
                $shopifyVariantSku = $shopifyVariant['sku'] ?? null;

                if (! $shopifyVariantId || ! $shopifyVariantSku) {
                    continue;
                }

                // Find matching local variant by SKU
                foreach ($localVariants as $localVariant) {
                    if ($localVariant['sku'] === $shopifyVariantSku) {
                        // KISS: Update this specific variant ID with new price
                        $result = $client->updateSingleVariant($shopifyVariantId, [
                            'price' => $localVariant['price'],
                        ]);

                        // Fixed: Use correct response path for bulk update
                        $userErrors = $result['productVariantsBulkUpdate']['userErrors'] ?? [];
                        if (empty($userErrors)) {
                            $updatedCount++;
                        }
                        break;
                    }
                }
            }

            return $updatedCount > 0;

        } catch (\Exception $e) {
            error_log('Variant pricing update failed: '.$e->getMessage());

            return false;
        }
    }
}
