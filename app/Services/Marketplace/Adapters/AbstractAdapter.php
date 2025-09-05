<?php

namespace App\Services\Marketplace\Adapters;

use App\Models\Product;
use App\Models\SyncAccount;
use App\Services\Marketplace\Contracts\MarketplaceAdapter;
use App\Services\Marketplace\ValueObjects\MarketplaceProduct;
use App\Services\Marketplace\ValueObjects\SyncResult;

/**
 * 🏗️ ABSTRACT MARKETPLACE ADAPTER
 *
 * Base functionality shared across all marketplace adapters.
 * Handles account management, product loading, and common operations.
 */
abstract class AbstractAdapter implements MarketplaceAdapter
{
    protected ?MarketplaceProduct $marketplaceProduct = null;

    protected string $mode = 'create'; // 'create', 'update', or 'recreate'

    protected array $fieldsToUpdate = [];

    protected ?int $currentProductId = null;

    public function __construct(
        protected ?SyncAccount $syncAccount = null
    ) {}

    /**
     * Load a product and validate it exists
     */
    protected function loadProduct(int $productId): Product
    {
        $product = Product::with(['variants', 'variants.pricingRecords', 'images'])->find($productId);

        if (! $product) {
            throw new \InvalidArgumentException("Product with ID {$productId} not found");
        }

        return $product;
    }

    /**
     * Check if we have a valid sync account
     */
    protected function hasSyncAccount(): bool
    {
        return $this->syncAccount && $this->syncAccount->isConfigured();
    }

    /**
     * Get sync account or throw exception
     */
    protected function requireSyncAccount(): SyncAccount
    {
        if (! $this->hasSyncAccount()) {
            throw new \RuntimeException('No valid sync account configured for marketplace');
        }

        return $this->syncAccount;
    }

    /**
     * Get the marketplace name for this adapter
     */
    abstract protected function getMarketplaceName(): string;

    /**
     * Implementation of create method - must be implemented by each adapter
     * Returns self to allow fluent chaining
     */
    abstract public function create(int $productId): self;

    /**
     * Implementation of push method - must be implemented by each adapter
     */
    abstract public function push(): SyncResult;

    /**
     * Default test connection implementation - can be overridden
     */
    public function testConnection(): SyncResult
    {
        if (! $this->hasSyncAccount()) {
            return SyncResult::failure('No sync account configured');
        }

        return SyncResult::success('Sync account is configured', [
            'marketplace' => $this->getMarketplaceName(),
            'account' => $this->syncAccount->name,
        ]);
    }

    /**
     * Default pull implementation - not all marketplaces support this
     */
    public function pull(array $filters = []): SyncResult
    {
        return SyncResult::failure('Pull operation not supported by this marketplace');
    }

    /**
     * Get the prepared marketplace product
     */
    protected function getMarketplaceProduct(): MarketplaceProduct
    {
        if (! $this->marketplaceProduct) {
            throw new \RuntimeException('No product has been created. Call create() method first.');
        }

        return $this->marketplaceProduct;
    }

    /**
     * Public access to the prepared marketplace product (for testing/debugging)
     */
    public function getCreatedProduct(): ?MarketplaceProduct
    {
        return $this->marketplaceProduct;
    }

    /**
     * Set the marketplace product (used by create() implementations)
     */
    protected function setMarketplaceProduct(MarketplaceProduct $marketplaceProduct): void
    {
        $this->marketplaceProduct = $marketplaceProduct;
    }

    /**
     * Default update implementation - prepare for update mode
     */
    public function update(int $productId): self
    {
        $this->mode = 'update';
        $this->currentProductId = $productId;
        $this->fieldsToUpdate = [];

        return $this;
    }

    /**
     * Set title for update
     */
    public function title(string $title): self
    {
        $this->fieldsToUpdate['title'] = $title;

        return $this;
    }

    /**
     * Set images for update
     */
    public function images(array $images): self
    {
        $this->fieldsToUpdate['images'] = $images;

        return $this;
    }

    /**
     * Mark pricing for update
     */
    public function pricing(): self
    {
        $this->fieldsToUpdate['pricing'] = true;

        return $this;
    }

    /**
     * Recreate mode - clear stale marketplace data and create fresh
     */
    public function recreate(int $productId): self
    {
        $this->mode = 'recreate';
        $this->currentProductId = $productId;
        $this->fieldsToUpdate = [];
        $this->clearStaleMarketplaceAttributes($productId);

        return $this;
    }

    /**
     * Clear stale marketplace attributes when products no longer exist
     */
    protected function clearStaleMarketplaceAttributes(int $productId): void
    {
        $product = Product::find($productId);
        if (! $product) {
            return;
        }

        $marketplace = $this->getMarketplaceName();

        // Clear all marketplace-specific attributes
        $product->setAttributeValue("{$marketplace}_product_ids", null);
        $product->setAttributeValue("{$marketplace}_sync_account_id", null);
        $product->setAttributeValue("{$marketplace}_synced_at", null);
        $product->setAttributeValue("{$marketplace}_status", null);
        $product->setAttributeValue("{$marketplace}_metadata", null);
    }

    /**
     * Check if we're in update mode
     */
    protected function isUpdateMode(): bool
    {
        return $this->mode === 'update';
    }

    /**
     * Check if we're in recreate mode
     */
    protected function isRecreateMode(): bool
    {
        return $this->mode === 'recreate';
    }

    /**
     * Get fields marked for update
     */
    protected function getFieldsToUpdate(): array
    {
        return $this->fieldsToUpdate;
    }

    /**
     * Dispatch this operation as a background job
     */
    public function dispatch(): void
    {
        if (!$this->currentProductId) {
            throw new \RuntimeException('No product ID set. Call create(), update(), or other operation methods first.');
        }

        $job = new \App\Jobs\SyncProductToMarketplaceJob(
            product: $this->loadProduct($this->currentProductId),
            syncAccount: $this->requireSyncAccount(),
            operationType: $this->getOperationType(),
            operationData: $this->getOperationData()
        );

        dispatch($job);
    }

    /**
     * Get the current operation type
     */
    protected function getOperationType(): string
    {
        return $this->mode ?? 'create';
    }

    /**
     * Get operation-specific data for the job
     */
    protected function getOperationData(): array
    {
        return $this->fieldsToUpdate;
    }

    /**
     * Access account-level operations (info, identifiers, etc.)
     */
    public function account(?string $name = null): \App\Services\Marketplace\AccountOperations
    {
        if ($name) {
            $resolved = \App\Models\SyncAccount::findByChannelAndName($this->getMarketplaceName(), $name);
            if (! $resolved) {
                throw new \RuntimeException("Sync account '{$name}' not found for {$this->getMarketplaceName()}");
            }
            $this->syncAccount = $resolved;
        }

        return new \App\Services\Marketplace\AccountOperations(
            syncAccount: $this->requireSyncAccount(),
            marketplace: $this->getMarketplaceName()
        );
    }
}
