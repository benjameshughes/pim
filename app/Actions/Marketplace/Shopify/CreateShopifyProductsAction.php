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
     * Create a single Shopify product using clean two-step approach
     */
    protected function createSingleProduct($client, array $shopifyProduct, SyncAccount $syncAccount): array
    {
        $metadata = $shopifyProduct['metadata'] ?? [];
        $productInput = $shopifyProduct['productInput'] ?? [];
        $variantInputs = $shopifyProduct['variantInputs'] ?? [];

        try {
            \Illuminate\Support\Facades\Log::info('🎬 Starting two-step product creation', [
                'color_group' => $metadata['color_group'] ?? 'unknown',
                'variant_count' => count($variantInputs)
            ]);
            
            // STEP 1: Create product with options
            $step1Result = $client->createProductWithOptions($productInput);
            $userErrors = $step1Result['productCreate']['userErrors'] ?? [];
            $product = $step1Result['productCreate']['product'] ?? null;

            if (!empty($userErrors) || !$product) {
                \Illuminate\Support\Facades\Log::error('❌ Step 1 failed - Product creation', [
                    'user_errors' => $userErrors,
                    'color_group' => $metadata['color_group'] ?? 'unknown'
                ]);
                
                return [
                    'success' => false,
                    'error' => !empty($userErrors) ? 'Step 1 - Product creation: '.json_encode($userErrors) : 'No product returned',
                    'color_group' => $metadata['color_group'] ?? 'unknown',
                ];
            }

            $productId = $product['id'];
            \Illuminate\Support\Facades\Log::info('✅ Step 1 successful - Product created', [
                'product_id' => $productId,
                'color_group' => $metadata['color_group'] ?? 'unknown'
            ]);

            // STEP 2: Create variants
            if (!empty($variantInputs)) {
                $step2Result = $client->createVariantsBulk($productId, $variantInputs);
                $variantErrors = $step2Result['productVariantsBulkCreate']['userErrors'] ?? [];
                $variants = $step2Result['productVariantsBulkCreate']['productVariants'] ?? [];

                if (!empty($variantErrors)) {
                    \Illuminate\Support\Facades\Log::error('❌ Step 2 failed - Variant creation', [
                        'variant_errors' => $variantErrors,
                        'color_group' => $metadata['color_group'] ?? 'unknown'
                    ]);
                    
                    return [
                        'success' => false,
                        'error' => 'Step 2 - Variant creation: '.json_encode($variantErrors),
                        'color_group' => $metadata['color_group'] ?? 'unknown',
                    ];
                }

                \Illuminate\Support\Facades\Log::info('✅ Step 2 successful - Variants created', [
                    'product_id' => $productId,
                    'variant_count' => count($variants),
                    'color_group' => $metadata['color_group'] ?? 'unknown'
                ]);
            }

            return [
                'success' => true,
                'id' => $productId,
                'title' => $product['title'],
                'handle' => $product['handle'],
                'color_group' => $metadata['color_group'] ?? 'unknown',
                'original_product_id' => $metadata['original_product_id'] ?? null,
                'variant_count' => count($variantInputs),
            ];

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('❌ Exception during product creation', [
                'error' => $e->getMessage(),
                'color_group' => $metadata['color_group'] ?? 'unknown'
            ]);
            
            return [
                'success' => false,
                'error' => 'Create failed: '.$e->getMessage(),
                'color_group' => $metadata['color_group'] ?? 'unknown',
            ];
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
