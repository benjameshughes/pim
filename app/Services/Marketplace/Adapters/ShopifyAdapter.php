<?php

namespace App\Services\Marketplace\Adapters;

use App\Actions\Marketplace\Shopify\SplitProductByColourAction;
use App\Actions\Marketplace\Shopify\TransformToShopifyAction;
use App\Actions\Marketplace\Shopify\PushToShopifyAction;
use App\Services\Marketplace\ValueObjects\MarketplaceProduct;
use App\Services\Marketplace\ValueObjects\SyncResult;
use App\Models\SyncAccount;

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

        if ($this->isUpdateMode()) {
            // Handle update mode with auto-recreate fallback
            return $this->pushUpdates($syncAccount);
        }

        if ($this->isRecreateMode()) {
            // Handle recreate mode - create fresh products
            $marketplaceProduct = $this->create($this->currentProductId)->getCreatedProduct();
            return app(PushToShopifyAction::class)
                ->execute($marketplaceProduct, $syncAccount);
        }

        // Handle create mode (existing logic)
        $marketplaceProduct = $this->getMarketplaceProduct();
        return app(PushToShopifyAction::class)
            ->execute($marketplaceProduct, $syncAccount);
    }

    /**
     * Handle update operations with auto-recreate fallback
     */
    protected function pushUpdates(SyncAccount $syncAccount): SyncResult
    {
        $fieldsToUpdate = $this->getFieldsToUpdate();
        
        if (empty($fieldsToUpdate)) {
            // Full update - recreate and push
            $marketplaceProduct = $this->create($this->currentProductId)->getCreatedProduct();
            return app(PushToShopifyAction::class)
                ->execute($marketplaceProduct, $syncAccount);
        }

        // Partial update - use UpdateShopifyAction
        $updateResult = app(\App\Actions\Marketplace\Shopify\UpdateShopifyAction::class)
            ->execute($this->currentProductId, $fieldsToUpdate, $syncAccount);

        // If update failed because products don't exist, auto-recreate
        if (!$updateResult->isSuccess() && $this->shouldAutoRecreate($updateResult)) {
            // Switch to recreate mode and try again
            $marketplaceProduct = $this->recreate($this->currentProductId)->create($this->currentProductId)->getCreatedProduct();
            $recreateResult = app(PushToShopifyAction::class)
                ->execute($marketplaceProduct, $syncAccount);
            
            // Enhance the result message to indicate auto-recreate happened
            if ($recreateResult->isSuccess()) {
                return new \App\Services\Marketplace\ValueObjects\SyncResult(
                    success: true,
                    message: 'Original products missing - auto-recreated successfully! ' . $recreateResult->getMessage(),
                    data: $recreateResult->getData(),
                    errors: $recreateResult->getErrors(),
                    metadata: array_merge($recreateResult->getMetadata() ?? [], ['auto_recreated' => true])
                );
            }
        }

        return $updateResult;
    }

    /**
     * Determine if we should auto-recreate based on the failure reason
     */
    protected function shouldAutoRecreate(\App\Services\Marketplace\ValueObjects\SyncResult $result): bool
    {
        $message = strtolower($result->getMessage());
        $errors = array_map('strtolower', $result->getErrors());
        $allText = $message . ' ' . implode(' ', $errors);
        
        return str_contains($allText, 'no existing shopify products found') ||
               str_contains($allText, 'product not found') ||
               str_contains($allText, 'does not exist') ||
               str_contains($allText, 'product does not exist');
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