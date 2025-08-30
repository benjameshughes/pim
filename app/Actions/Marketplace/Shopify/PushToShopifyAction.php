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
     * @param MarketplaceProduct $marketplaceProduct Prepared Shopify products
     * @param SyncAccount $syncAccount Shopify account credentials
     * @return SyncResult Push operation result
     */
    public function execute(MarketplaceProduct $marketplaceProduct, SyncAccount $syncAccount): SyncResult
    {
        try {
            // Get the transformed Shopify products
            $shopifyProducts = $marketplaceProduct->getData();
            
            if (empty($shopifyProducts)) {
                return SyncResult::failure('No Shopify products to push');
            }

            // Initialize Shopify GraphQL client
            $graphqlClient = $this->initializeGraphQLClient($syncAccount);
            
            $results = [];
            $errors = [];

            // Process each Shopify product (one per color)
            foreach ($shopifyProducts as $shopifyProduct) {
                $result = $this->pushSingleProduct($graphqlClient, $shopifyProduct);
                
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
                message: 'Push to Shopify failed: ' . $e->getMessage(),
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
    protected function pushSingleProduct(\App\Services\Marketplace\Shopify\ShopifyGraphQLClient $client, array $shopifyProduct): array
    {
        // Extract internal tracking data
        $internalData = $shopifyProduct['_internal'] ?? [];
        
        // Extract only the ProductInput data - this is what Shopify expects
        $productInput = $shopifyProduct['productInput'] ?? [];
        
        // Use the official SDK to create the product with correct ProductInput structure
        $result = $client->createProduct($productInput);
        
        // Check for user errors in the GraphQL response
        $userErrors = $result['productCreate']['userErrors'] ?? [];
        $product = $result['productCreate']['product'] ?? null;
        
        if (!empty($userErrors)) {
            return [
                'success' => false,
                'error' => 'Shopify validation errors: ' . json_encode($userErrors),
                'errors' => $userErrors,
                'color_group' => $internalData['color_group'] ?? 'unknown',
                'original_product_id' => $internalData['original_product_id'] ?? null,
            ];
        }

        if (!$product) {
            return [
                'success' => false,
                'error' => 'No product returned from Shopify',
                'color_group' => $internalData['color_group'] ?? 'unknown', 
                'original_product_id' => $internalData['original_product_id'] ?? null,
            ];
        }

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
}