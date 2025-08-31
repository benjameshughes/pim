<?php

namespace App\Services\Marketplace\Adapters;

use App\Actions\Marketplace\Shopify\CreateShopifyProductsAction;
use App\Actions\Marketplace\Shopify\DeleteShopifyProductsAction;
use App\Actions\Marketplace\Shopify\FullUpdateShopifyProductsAction;
use App\Actions\Marketplace\Shopify\LinkShopifyProductsAction;
use App\Actions\Marketplace\Shopify\SplitProductByColourAction;
use App\Actions\Marketplace\Shopify\TransformToShopifyAction;
use App\Actions\Marketplace\Shopify\UpdateShopifyProductsAction;
use App\Models\SyncAccount;
use App\Services\Marketplace\ValueObjects\MarketplaceProduct;
use App\Services\Marketplace\ValueObjects\SyncResult;

/**
 * 🛍️ SHOPIFY MARKETPLACE ADAPTER - REFACTORED
 *
 * Simple orchestrator for Shopify operations using clean, single-responsibility actions.
 * Each method has one clear purpose - no complex mode checking or fallback logic.
 */
class ShopifyAdapter extends AbstractAdapter
{
    protected string $operationType = 'none';

    protected array $fieldsToUpdate = [];

    /**
     * Get the marketplace name
     */
    protected function getMarketplaceName(): string
    {
        return 'shopify';
    }

    /**
     * 🆕 SET UP CREATE OPERATION
     * Creates NEW products on Shopify (fails if they already exist)
     */
    public function create(int $productId): self
    {
        $this->operationType = 'create';
        $this->currentProductId = $productId;

        // Prepare marketplace product data
        $this->prepareMarketplaceProduct($productId);

        return $this;
    }

    /**
     * ✏️ SET UP UPDATE OPERATION
     * Updates EXISTING products on Shopify using stored IDs
     */
    public function update(int $productId): self
    {
        $this->operationType = 'update';
        $this->currentProductId = $productId;
        $this->fieldsToUpdate = [];

        return $this;
    }

    /**
     * 🔄 SET UP FULL UPDATE OPERATION
     * Comprehensively updates all existing product data (preserves Shopify IDs)
     */
    public function fullUpdate(int $productId): self
    {
        $this->operationType = 'fullUpdate';
        $this->currentProductId = $productId;

        // Prepare marketplace product data for full update
        $this->prepareMarketplaceProduct($productId);

        return $this;
    }

    /**
     * 🔄 DEPRECATED: Use fullUpdate() instead
     *
     * @deprecated Use fullUpdate() for non-destructive comprehensive updates
     */
    public function recreate(int $productId): self
    {
        // Redirect to fullUpdate for backwards compatibility
        return $this->fullUpdate($productId);
    }

    /**
     * 🗑️ SET UP DELETE OPERATION
     * Deletes existing products from Shopify
     */
    public function delete(int $productId): self
    {
        $this->operationType = 'delete';
        $this->currentProductId = $productId;

        return $this;
    }

    /**
     * 🔗 SET UP LINK OPERATION
     * Links existing Shopify products to local product by SKU matching
     */
    public function link(int $productId): self
    {
        $this->operationType = 'link';
        $this->currentProductId = $productId;

        return $this;
    }

    /**
     * 💰 ADD PRICING TO UPDATE FIELDS
     */
    public function pricing(array $pricingData = []): self
    {
        if ($this->operationType !== 'update') {
            throw new \RuntimeException('pricing() can only be used with update() operations');
        }

        $this->fieldsToUpdate['pricing'] = empty($pricingData) ? true : $pricingData;

        return $this;
    }

    /**
     * 📝 ADD TITLE TO UPDATE FIELDS
     */
    public function title(string $newTitle): self
    {
        if ($this->operationType !== 'update') {
            throw new \RuntimeException('title() can only be used with update() operations');
        }

        $this->fieldsToUpdate['title'] = $newTitle;

        return $this;
    }

    /**
     * 🖼️ ADD IMAGES TO UPDATE FIELDS
     */
    public function images(array $imageData): self
    {
        if ($this->operationType !== 'update') {
            throw new \RuntimeException('images() can only be used with update() operations');
        }

        $this->fieldsToUpdate['images'] = $imageData;

        return $this;
    }

    /**
     * 🚀 EXECUTE THE OPERATION
     * Calls the appropriate action based on operation type
     */
    public function push(): SyncResult
    {
        $syncAccount = $this->requireSyncAccount();

        return match ($this->operationType) {
            'create' => $this->executeCreate($syncAccount),
            'update' => $this->executeUpdate($syncAccount),
            'fullUpdate' => $this->executeFullUpdate($syncAccount),
            'recreate' => $this->executeFullUpdate($syncAccount), // Backwards compatibility
            'delete' => $this->executeDelete($syncAccount),
            'link' => $this->executeLink($syncAccount),
            default => SyncResult::failure('No operation type set. Use create(), update(), fullUpdate(), delete(), or link() first.')
        };
    }

    /**
     * Execute create operation
     */
    protected function executeCreate(SyncAccount $syncAccount): SyncResult
    {
        $marketplaceProduct = $this->getMarketplaceProduct();

        $createAction = new CreateShopifyProductsAction;

        return $createAction->execute($marketplaceProduct, $syncAccount);
    }

    /**
     * Execute update operation
     */
    protected function executeUpdate(SyncAccount $syncAccount): SyncResult
    {
        if (empty($this->fieldsToUpdate)) {
            return SyncResult::failure('No fields specified for update. Use pricing(), title(), or images() to specify what to update.');
        }

        $updateAction = new UpdateShopifyProductsAction;

        return $updateAction->execute($this->currentProductId, $this->fieldsToUpdate, $syncAccount);
    }

    /**
     * Execute full update operation
     */
    protected function executeFullUpdate(SyncAccount $syncAccount): SyncResult
    {
        $marketplaceProduct = $this->getMarketplaceProduct();

        $fullUpdateAction = new FullUpdateShopifyProductsAction;

        return $fullUpdateAction->execute($marketplaceProduct, $syncAccount);
    }

    /**
     * Execute delete operation
     */
    protected function executeDelete(SyncAccount $syncAccount): SyncResult
    {
        $deleteAction = new DeleteShopifyProductsAction;

        return $deleteAction->execute($this->currentProductId, $syncAccount);
    }

    /**
     * Execute link operation
     */
    protected function executeLink(SyncAccount $syncAccount): SyncResult
    {
        $linkAction = new LinkShopifyProductsAction;

        return $linkAction->execute($this->currentProductId, $syncAccount);
    }

    /**
     * Prepare marketplace product data (for create and fullUpdate)
     */
    protected function prepareMarketplaceProduct(int $productId): void
    {
        $product = $this->loadProduct($productId);

        // Step 1: Split product by colors
        $colorGroups = app(SplitProductByColourAction::class)
            ->execute($product);

        // Step 2: Transform each color group to Shopify format
        $shopifyProducts = app(TransformToShopifyAction::class)
            ->execute($colorGroups, $product, $this->syncAccount);

        // Create marketplace product with metadata
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
    }

    /**
     * Test Shopify connection
     */
    public function testConnection(): SyncResult
    {
        if (! $this->hasSyncAccount()) {
            return SyncResult::failure('No Shopify sync account configured');
        }

        try {
            return app(\App\Actions\Marketplace\Shopify\TestShopifyConnectionAction::class)
                ->execute($this->syncAccount);
        } catch (\Exception $e) {
            return SyncResult::failure('Connection test failed: '.$e->getMessage());
        }
    }

    /**
     * Pull products from Shopify
     */
    public function pull(array $filters = []): SyncResult
    {
        if (! $this->hasSyncAccount()) {
            return SyncResult::failure('No Shopify sync account configured');
        }

        try {
            return app(\App\Actions\Marketplace\Shopify\PullFromShopifyAction::class)
                ->execute($this->syncAccount, $filters);
        } catch (\Exception $e) {
            return SyncResult::failure('Pull operation failed: '.$e->getMessage());
        }
    }
}
