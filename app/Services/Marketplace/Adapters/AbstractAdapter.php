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
    
    public function __construct(
        protected ?SyncAccount $syncAccount = null
    ) {}

    /**
     * Load a product and validate it exists
     */
    protected function loadProduct(int $productId): Product
    {
        $product = Product::with(['variants', 'variants.pricingRecords', 'images'])->find($productId);
        
        if (!$product) {
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
        if (!$this->hasSyncAccount()) {
            throw new \RuntimeException("No valid sync account configured for marketplace");
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
        if (!$this->hasSyncAccount()) {
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
        if (!$this->marketplaceProduct) {
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
}