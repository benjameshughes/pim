<?php

namespace App\Services\Marketplace\Adapters;

use App\Actions\Marketplace\Shopify\SplitProductByColourAction;
use App\Actions\Marketplace\Shopify\TransformToShopifyAction;
use App\Actions\Marketplace\Shopify\PushToShopifyAction;
use App\Services\Marketplace\ValueObjects\MarketplaceProduct;
use App\Services\Marketplace\ValueObjects\SyncResult;

/**
 * 🛍️ SHOPIFY MARKETPLACE ADAPTER
 *
 * Handles Shopify-specific product transformations and API operations.
 * Implements the unique Shopify requirement of splitting products by color.
 */
class ShopifyAdapter extends AbstractAdapter
{
    /**
     * Get the marketplace name
     */
    protected function getMarketplaceName(): string
    {
        return 'shopify';
    }

    /**
     * Create Shopify-specific product data
     *
     * Shopify's unique behavior: Split single product into multiple
     * Shopify products, one for each color variant.
     */
    public function create(int $productId): self
    {
        $product = $this->loadProduct($productId);
        
        // Step 1: Split product by colors using Action
        $colorGroups = app(SplitProductByColourAction::class)
            ->execute($product);

        // Step 2: Transform each color group to Shopify format using Action  
        $shopifyProducts = app(TransformToShopifyAction::class)
            ->execute($colorGroups, $product);

        // Create the marketplace product with metadata
        $marketplaceProduct = new MarketplaceProduct(
            data: $shopifyProducts,
            metadata: [
                'original_product_id' => $product->id,
                'original_product_name' => $product->name,
                'color_groups_count' => count($colorGroups),
                'shopify_products_count' => count($shopifyProducts),
                'transformation_type' => 'color_split',
            ]
        );

        $this->setMarketplaceProduct($marketplaceProduct);

        return $this; // Return self for fluent chaining
    }

    /**
     * Push the prepared Shopify products via GraphQL
     */
    public function push(): SyncResult
    {
        $syncAccount = $this->requireSyncAccount();
        $marketplaceProduct = $this->getMarketplaceProduct();

        // Use Action to push to Shopify
        return app(PushToShopifyAction::class)
            ->execute($marketplaceProduct, $syncAccount);
    }

    /**
     * Test Shopify connection using GraphQL
     */
    public function testConnection(): SyncResult
    {
        if (!$this->hasSyncAccount()) {
            return SyncResult::failure('No Shopify sync account configured');
        }

        try {
            // Use Action to test connection
            return app(\App\Actions\Marketplace\Shopify\TestShopifyConnectionAction::class)
                ->execute($this->syncAccount);
        } catch (\Exception $e) {
            return SyncResult::failure('Connection test failed: ' . $e->getMessage());
        }
    }

    /**
     * Pull products from Shopify (if needed)
     */
    public function pull(array $filters = []): SyncResult
    {
        if (!$this->hasSyncAccount()) {
            return SyncResult::failure('No Shopify sync account configured');
        }

        try {
            // Use Action to pull from Shopify
            return app(\App\Actions\Marketplace\Shopify\PullFromShopifyAction::class)
                ->execute($this->syncAccount, $filters);
        } catch (\Exception $e) {
            return SyncResult::failure('Pull operation failed: ' . $e->getMessage());
        }
    }
}