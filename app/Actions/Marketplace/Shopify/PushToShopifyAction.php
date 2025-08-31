<?php

namespace App\Actions\Marketplace\Shopify;

use App\Models\SyncAccount;
use App\Services\Marketplace\ValueObjects\MarketplaceProduct;
use App\Services\Marketplace\ValueObjects\SyncResult;

/**
 * 🚀 PUSH TO SHOPIFY ACTION
 *
 * Handles the actual GraphQL mutations to create/update products in Shopify.
 * Uses the Shopify GraphQL Admin API for efficient bulk operations.
 */
class PushToShopifyAction
{
    /**
     * Push marketplace product to Shopify
     *
     * @param  MarketplaceProduct  $marketplaceProduct  Prepared Shopify products
     * @param  SyncAccount  $syncAccount  Shopify account credentials
     * @return SyncResult Push operation result
     */
    public function execute(MarketplaceProduct $marketplaceProduct, SyncAccount $syncAccount, bool $forceRecreate = false): SyncResult
    {
        try {
            // Get the transformed Shopify products
            $shopifyProducts = $marketplaceProduct->getData();

            if (empty($shopifyProducts)) {
                return SyncResult::failure('No Shopify products to push');
            }

            // 🎯 DUPLICATE PREVENTION - Check if products already exist (unless forcing recreate)
            if (! $forceRecreate && $this->productsAlreadyExist($marketplaceProduct, $syncAccount)) {
                return SyncResult::failure('Products already exist on Shopify for this account. Use update() instead of create().');
            }

            // Initialize Shopify GraphQL client
            $graphqlClient = $this->initializeGraphQLClient($syncAccount);

            $results = [];
            $errors = [];

            // Process each Shopify product (one per color)
            foreach ($shopifyProducts as $shopifyProduct) {
                $result = $this->pushSingleProduct($graphqlClient, $shopifyProduct, $syncAccount);

                if ($result['success']) {
                    $results[] = $result;
                } else {
                    $errors[] = $result;
                }
            }

            // Determine overall success
            $success = empty($errors);
            $message = $success
                ? sprintf('Successfully pushed %d products to Shopify', count($results))
                : sprintf('Pushed %d products, %d failed', count($results), count($errors));

            return new SyncResult(
                success: $success,
                message: $message,
                data: [
                    'successful' => $results,
                    'failed' => $errors,
                    'total_processed' => count($shopifyProducts),
                ],
                errors: array_column($errors, 'error'),
                metadata: $marketplaceProduct->getMetadata()
            );

        } catch (\Exception $e) {
            return SyncResult::failure(
                message: 'Push to Shopify failed: '.$e->getMessage(),
                errors: [$e->getMessage()]
            );
        }
    }

    /**
     * Initialize Shopify GraphQL client using official SDK
     */
    protected function initializeGraphQLClient(SyncAccount $syncAccount): \App\Services\Marketplace\Shopify\ShopifyGraphQLClient
    {
        return new \App\Services\Marketplace\Shopify\ShopifyGraphQLClient($syncAccount);
    }

    /**
     * Push a single product to Shopify using official SDK
     */
    protected function pushSingleProduct(\App\Services\Marketplace\Shopify\ShopifyGraphQLClient $client, array $shopifyProduct, SyncAccount $syncAccount): array
    {
        // Extract internal tracking data
        $internalData = $shopifyProduct['_internal'] ?? [];

        // Extract only the ProductInput data - this is what Shopify expects
        $productInput = $shopifyProduct['productInput'] ?? [];

        // Step 1: Create product with options (Shopify auto-generates variants)
        $result = $client->createProduct($productInput);

        $userErrors = $result['productCreate']['userErrors'] ?? [];
        $product = $result['productCreate']['product'] ?? null;

        if (! empty($userErrors) || ! $product) {
            return [
                'success' => false,
                'error' => ! empty($userErrors) ? 'Shopify validation errors: '.json_encode($userErrors) : 'No product returned from Shopify',
                'errors' => $userErrors,
                'color_group' => $internalData['color_group'] ?? 'unknown',
                'original_product_id' => $internalData['original_product_id'] ?? null,
            ];
        }

        // Step 2: KISS - Update the auto-generated variants with correct pricing AND SKUs
        $this->updateVariantPricing($client, $product, $shopifyProduct['variants'] ?? []);

        // 🎯 SAVE TO ATTRIBUTES - This is where the magic happens!
        $this->saveShopifyAttributes($internalData, $product, $syncAccount);

        return [
            'success' => true,
            'shopify_product_id' => $product['id'],
            'shopify_product_handle' => $product['handle'],
            'shopify_product_title' => $product['title'],
            'color_group' => $internalData['color_group'] ?? 'unknown',
            'original_product_id' => $internalData['original_product_id'] ?? null,
            'variant_count' => count($product['variants']['edges'] ?? []),
            'note' => 'Product created with productOptions. Shopify automatically creates variants from the options.',
        ];
    }

    /**
     * 🎯 Save Shopify sync data to product attributes
     * This is where we embrace the attribute system for tracking!
     */
    protected function saveShopifyAttributes(array $internalData, array $shopifyProduct, SyncAccount $syncAccount): void
    {
        $originalProductId = $internalData['original_product_id'] ?? null;
        $colorGroup = $internalData['color_group'] ?? 'unknown';

        if (! $originalProductId) {
            return; // Can't save without product ID
        }

        $localProduct = \App\Models\Product::find($originalProductId);
        if (! $localProduct) {
            return;
        }

        // Get existing Shopify product IDs from attributes
        $existingIds = $localProduct->getSmartAttributeValue('shopify_product_ids');
        $productIds = [];

        if ($existingIds) {
            if (is_string($existingIds)) {
                $productIds = json_decode($existingIds, true) ?: [];
            } elseif (is_array($existingIds)) {
                $productIds = $existingIds;
            }
        }

        // Add this color group's Shopify product ID
        $productIds[$colorGroup] = $shopifyProduct['id'];

        // Save all the beautiful attribute data
        $localProduct->setAttributeValue('shopify_product_ids', json_encode($productIds));
        $localProduct->setAttributeValue('shopify_sync_account_id', $syncAccount->id);
        $localProduct->setAttributeValue('shopify_synced_at', now()->toISOString());
        $localProduct->setAttributeValue('shopify_status', 'synced');

        // Save metadata for future reference
        $metadata = [
            'handle' => $shopifyProduct['handle'] ?? null,
            'title' => $shopifyProduct['title'] ?? null,
            'color_groups' => array_keys($productIds),
            'last_push_timestamp' => now()->timestamp,
        ];
        $localProduct->setAttributeValue('shopify_metadata', json_encode($metadata));
    }

    /**
     * 🎯 KISS - Update auto-generated variants with correct pricing
     */
    protected function updateVariantPricing($client, array $product, array $localVariants): void
    {
        if (empty($localVariants)) {
            return;
        }

        // Get auto-generated variants from Shopify
        $shopifyVariants = $product['variants']['edges'] ?? [];
        if (empty($shopifyVariants)) {
            return;
        }

        // Match local variants to Shopify variants (SKU might be empty initially)
        foreach ($shopifyVariants as $edge) {
            $shopifyVariant = $edge['node'] ?? [];
            $shopifyVariantId = $shopifyVariant['id'] ?? null;

            if (! $shopifyVariantId) {
                continue;
            }

            // Since SKUs might be empty initially, match by position/order
            // The first shopify variant matches the first local variant, etc.
            $matchingLocalVariant = array_shift($localVariants);

            if ($matchingLocalVariant) {
                // KISS: Update this specific variant ID with correct price AND SKU
                $client->updateSingleVariant($shopifyVariantId, [
                    'sku' => $matchingLocalVariant['sku'],
                    'price' => $matchingLocalVariant['price'],
                ]);
            }
        }
    }

    /**
     * 🎯 KISS - Check if products already exist to prevent duplicates
     */
    protected function productsAlreadyExist(MarketplaceProduct $marketplaceProduct, SyncAccount $syncAccount): bool
    {
        $metadata = $marketplaceProduct->getMetadata();
        $originalProductId = $metadata['original_product_id'] ?? null;

        if (! $originalProductId) {
            return false; // Can't check without product ID
        }

        $localProduct = \App\Models\Product::find($originalProductId);
        if (! $localProduct) {
            return false;
        }

        // Check if this product has been synced to Shopify for this account
        $shopifyProductIds = $localProduct->getSmartAttributeValue('shopify_product_ids');
        $syncAccountId = $localProduct->getSmartAttributeValue('shopify_sync_account_id');
        $status = $localProduct->getSmartAttributeValue('shopify_status');

        // If we have Shopify product IDs, correct sync account, and status is synced - products exist
        return ! empty($shopifyProductIds) &&
               $syncAccountId == $syncAccount->id &&
               $status === 'synced';
    }
}
