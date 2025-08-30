<?php

namespace App\Actions\Marketplace\Shopify;

use App\Services\Marketplace\ValueObjects\MarketplaceProduct;
use App\Services\Marketplace\ValueObjects\SyncResult;
use App\Models\SyncAccount;
use App\Models\Product;

/**
 * 🆕 CREATE SHOPIFY PRODUCTS ACTION
 * 
 * Single responsibility: Create NEW products on Shopify
 * - Fails if products already exist (unless forced)
 * - Sets SKUs and pricing correctly from the start
 * - No fallback logic - pure creation
 */
class CreateShopifyProductsAction
{
    /**
     * Create new products on Shopify
     *
     * @param MarketplaceProduct $marketplaceProduct Prepared Shopify products
     * @param SyncAccount $syncAccount Shopify account credentials  
     * @param bool $forceCreate Skip duplicate check (for recreate)
     * @return SyncResult Creation result
     */
    public function execute(MarketplaceProduct $marketplaceProduct, SyncAccount $syncAccount, bool $forceCreate = false): SyncResult
    {
        try {
            $shopifyProducts = $marketplaceProduct->getData();
            
            if (empty($shopifyProducts)) {
                return SyncResult::failure('No Shopify products to create');
            }

            // 🎯 DUPLICATE PREVENTION (unless forcing create)
            if (!$forceCreate && $this->productsAlreadyExist($marketplaceProduct, $syncAccount)) {
                return SyncResult::failure('Products already exist on Shopify. Use update() instead of create().');
            }

            $client = new \App\Services\Marketplace\Shopify\ShopifyGraphQLClient($syncAccount);
            $results = [];
            $errors = [];

            // Create each Shopify product (one per color)
            foreach ($shopifyProducts as $shopifyProduct) {
                $result = $this->createSingleProduct($client, $shopifyProduct, $syncAccount);
                
                if ($result['success']) {
                    $results[] = $result;
                } else {
                    $errors[] = $result;
                }
            }

            $success = empty($errors);
            $message = $success 
                ? sprintf('Successfully created %d Shopify products', count($results))
                : sprintf('Created %d products, %d failed', count($results), count($errors));

            // Save successful creations to attributes
            if (!empty($results)) {
                $this->saveShopifyAttributes($results, $marketplaceProduct, $syncAccount);
            }

            return new SyncResult(
                success: $success,
                message: $message,
                data: [
                    'created_products' => $results,
                    'failed_products' => $errors,
                ],
                errors: array_column($errors, 'error')
            );

        } catch (\Exception $e) {
            return SyncResult::failure(
                message: 'Create operation failed: ' . $e->getMessage(),
                errors: [$e->getMessage()]
            );
        }
    }

    /**
     * Create a single Shopify product with correct SKUs and pricing
     */
    protected function createSingleProduct($client, array $shopifyProduct, SyncAccount $syncAccount): array
    {
        $internalData = $shopifyProduct['_internal'] ?? [];
        $productInput = $shopifyProduct['productInput'] ?? [];

        try {
            // Step 1: Create product (Shopify auto-generates variants from options)
            $result = $client->createProduct($productInput);
            
            $userErrors = $result['productCreate']['userErrors'] ?? [];
            $product = $result['productCreate']['product'] ?? null;
            
            if (!empty($userErrors) || !$product) {
                return [
                    'success' => false,
                    'error' => !empty($userErrors) ? 'Shopify validation: ' . json_encode($userErrors) : 'No product returned',
                    'color_group' => $internalData['color_group'] ?? 'unknown',
                ];
            }

            // Step 2: Update auto-generated variants with correct SKUs and pricing
            $this->updateVariantDetails($client, $product, $shopifyProduct['variants'] ?? []);

            return [
                'success' => true,
                'id' => $product['id'],
                'title' => $product['title'],
                'handle' => $product['handle'],
                'color_group' => $internalData['color_group'] ?? 'unknown',
                'original_product_id' => $internalData['original_product_id'] ?? null,
                'variant_count' => count($product['variants']['edges'] ?? []),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Create failed: ' . $e->getMessage(),
                'color_group' => $internalData['color_group'] ?? 'unknown',
            ];
        }
    }

    /**
     * Update auto-generated variants with correct SKUs and pricing
     */
    protected function updateVariantDetails($client, array $product, array $localVariants): void
    {
        if (empty($localVariants)) {
            return;
        }

        $shopifyVariants = $product['variants']['edges'] ?? [];
        if (empty($shopifyVariants)) {
            return;
        }

        // Match by position (first shopify variant = first local variant)
        foreach ($shopifyVariants as $index => $edge) {
            $shopifyVariant = $edge['node'] ?? [];
            $shopifyVariantId = $shopifyVariant['id'] ?? null;

            if (!$shopifyVariantId || !isset($localVariants[$index])) {
                continue;
            }

            $localVariant = $localVariants[$index];

            // Update with SKU and pricing
            $client->updateSingleVariant($shopifyVariantId, [
                'sku' => $localVariant['sku'],
                'price' => $localVariant['price']
            ]);
        }
    }

    /**
     * Check if products already exist (duplicate prevention)
     */
    protected function productsAlreadyExist(MarketplaceProduct $marketplaceProduct, SyncAccount $syncAccount): bool
    {
        $metadata = $marketplaceProduct->getMetadata();
        $originalProductId = $metadata['original_product_id'] ?? null;

        if (!$originalProductId) {
            return false;
        }

        $localProduct = Product::find($originalProductId);
        if (!$localProduct) {
            return false;
        }

        $shopifyProductIds = $localProduct->getSmartAttributeValue('shopify_product_ids');
        $syncAccountId = $localProduct->getSmartAttributeValue('shopify_sync_account_id');
        $status = $localProduct->getSmartAttributeValue('shopify_status');

        return !empty($shopifyProductIds) && 
               $syncAccountId == $syncAccount->id && 
               $status === 'synced';
    }

    /**
     * Save creation results to product attributes
     */
    protected function saveShopifyAttributes(array $results, MarketplaceProduct $marketplaceProduct, SyncAccount $syncAccount): void
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

        // Build product IDs mapping: color_group => shopify_product_id
        $productIds = [];
        foreach ($results as $result) {
            if ($result['success']) {
                $productIds[$result['color_group']] = $result['id'];
            }
        }

        // Save to attributes
        $localProduct->setAttributeValue('shopify_product_ids', json_encode($productIds));
        $localProduct->setAttributeValue('shopify_sync_account_id', $syncAccount->id);
        $localProduct->setAttributeValue('shopify_synced_at', now()->toISOString());
        $localProduct->setAttributeValue('shopify_status', 'synced');
    }
}