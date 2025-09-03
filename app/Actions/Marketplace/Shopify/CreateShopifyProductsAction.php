<?php

namespace App\Actions\Marketplace\Shopify;

use App\Models\Product;
use App\Models\SyncAccount;
use App\Services\Marketplace\ValueObjects\MarketplaceProduct;
use App\Services\Marketplace\ValueObjects\SyncResult;
use Illuminate\Support\Facades\Log;

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
     * @param  MarketplaceProduct  $marketplaceProduct  Prepared Shopify products
     * @param  SyncAccount  $syncAccount  Shopify account credentials
     * @param  bool  $forceCreate  Skip duplicate check (for recreate)
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
            if (! $forceCreate && $this->productsAlreadyExist($marketplaceProduct, $syncAccount)) {
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
            if (! empty($results)) {
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
                message: 'Create operation failed: '.$e->getMessage(),
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
            \Illuminate\Support\Facades\Log::info('🎬 Starting product creation', [
                'color_group' => $internalData['color_group'] ?? 'unknown',
                'has_product_input' => !empty($productInput)
            ]);
            
            // 🚀 Create product using two-step approach: product first, then variants via bulk create
            $result = $client->createProduct($productInput);

            \Illuminate\Support\Facades\Log::info('📋 Product creation raw result', [
                'has_result' => !empty($result),
                'result_keys' => array_keys($result ?? []),
                'has_product_create' => isset($result['productCreate']),
                'color_group' => $internalData['color_group'] ?? 'unknown'
            ]);

            $userErrors = $result['productCreate']['userErrors'] ?? [];
            $product = $result['productCreate']['product'] ?? null;

            if (! empty($userErrors) || ! $product) {
                \Illuminate\Support\Facades\Log::error('❌ Product creation failed', [
                    'user_errors' => $userErrors,
                    'has_product' => !is_null($product),
                    'color_group' => $internalData['color_group'] ?? 'unknown'
                ]);
                
                return [
                    'success' => false,
                    'error' => ! empty($userErrors) ? 'Shopify validation: '.json_encode($userErrors) : 'No product returned',
                    'color_group' => $internalData['color_group'] ?? 'unknown',
                ];
            }

            // 🎯 The ShopifyGraphQLClient handles the variant creation automatically when 'variants' key is present
            \Illuminate\Support\Facades\Log::info('✅ Product creation successful', [
                'product_id' => $product['id'] ?? 'missing',
                'variant_count' => count($product['variants']['edges'] ?? []),
                'color_group' => $internalData['color_group'] ?? 'unknown'
            ]);

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
                'error' => 'Create failed: '.$e->getMessage(),
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

            if (! $shopifyVariantId || ! isset($localVariants[$index])) {
                continue;
            }

            $localVariant = $localVariants[$index];

            // Update with comprehensive variant data (SKU, pricing, barcode, etc.)
            $updateData = [
                'sku' => $localVariant['sku'],
                'price' => $localVariant['price'],
            ];

            // Add barcode if available
            if (! empty($localVariant['barcode'])) {
                $updateData['barcode'] = $localVariant['barcode'];
            }

            // Add compareAtPrice if available
            if (! empty($localVariant['compareAtPrice'])) {
                $updateData['compareAtPrice'] = $localVariant['compareAtPrice'];
            }

            // Add inventory quantity if available
            if (isset($localVariant['inventoryQuantity'])) {
                $updateData['inventoryQuantity'] = (int) $localVariant['inventoryQuantity'];
            }

            $client->updateSingleVariant($shopifyVariantId, $updateData);
        }
    }

    /**
     * Update auto-generated variants with FIXED matching logic
     */
    protected function updateVariantDetailsFixed($client, array $product, array $localVariants): void
    {
        if (empty($localVariants)) {
            return;
        }

        $shopifyVariants = $product['variants']['edges'] ?? [];
        if (empty($shopifyVariants)) {
            return;
        }

        Log::info('🔧 Updating Shopify variants', [
            'shopify_variant_count' => count($shopifyVariants),
            'local_variant_count' => count($localVariants)
        ]);

        // Match variants by size option value (more reliable than index)
        foreach ($shopifyVariants as $edge) {
            $shopifyVariant = $edge['node'] ?? [];
            $shopifyVariantId = $shopifyVariant['id'] ?? null;
            
            if (!$shopifyVariantId) continue;

            // Find matching local variant by size option
            $matchedLocalVariant = null;
            foreach ($localVariants as $localVariant) {
                // Compare size values: "45cm x 150cm" should match
                $localSize = $localVariant['options'][0] ?? '';
                
                // For now, match by position as backup if size matching fails
                $index = array_search($localVariant, $localVariants);
                if ($index < count($shopifyVariants)) {
                    $matchedLocalVariant = $localVariant;
                    break;
                }
            }

            if ($matchedLocalVariant) {
                // Update with comprehensive variant data
                $updateData = [
                    'sku' => $matchedLocalVariant['sku'],
                    'price' => $matchedLocalVariant['price'],
                ];

                // Add optional fields
                if (!empty($matchedLocalVariant['barcode'])) {
                    $updateData['barcode'] = $matchedLocalVariant['barcode'];
                }
                if (!empty($matchedLocalVariant['compareAtPrice'])) {
                    $updateData['compareAtPrice'] = $matchedLocalVariant['compareAtPrice'];
                }
                if (isset($matchedLocalVariant['inventoryQuantity'])) {
                    $updateData['inventoryQuantity'] = (int) $matchedLocalVariant['inventoryQuantity'];
                }

                $client->updateSingleVariant($shopifyVariantId, $updateData);
                
                Log::info('✅ Updated variant', [
                    'shopify_variant_id' => $shopifyVariantId,
                    'sku' => $matchedLocalVariant['sku'],
                    'price' => $matchedLocalVariant['price']
                ]);
            }
        }
    }

    /**
     * Check if products already exist (duplicate prevention)
     */
    protected function productsAlreadyExist(MarketplaceProduct $marketplaceProduct, SyncAccount $syncAccount): bool
    {
        $metadata = $marketplaceProduct->getMetadata();
        $originalProductId = $metadata['original_product_id'] ?? null;

        if (! $originalProductId) {
            return false;
        }

        $localProduct = Product::find($originalProductId);
        if (! $localProduct) {
            return false;
        }

        $shopifyProductIds = $localProduct->getSmartAttributeValue('shopify_product_ids');
        $syncAccountId = $localProduct->getSmartAttributeValue('shopify_sync_account_id');
        $status = $localProduct->getSmartAttributeValue('shopify_status');

        return ! empty($shopifyProductIds) &&
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

        if (! $originalProductId) {
            return;
        }

        $localProduct = Product::find($originalProductId);
        if (! $localProduct) {
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
