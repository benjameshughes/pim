<?php

namespace App\Services\Marketplace;

use App\Models\SyncAccount;
use App\Services\Marketplace\Adapters\ShopifyAdapter;
use App\Services\Marketplace\Adapters\EbayAdapter;
use App\Services\Marketplace\Adapters\FreemansAdapter;
use App\Services\Marketplace\Contracts\MarketplaceAdapter;
use InvalidArgumentException;

/**
 * 🎛️ MARKETPLACE MANAGER
 *
 * Factory for creating marketplace adapters with proper account binding.
 * Handles the creation and configuration of marketplace-specific adapters.
 */
class MarketplaceManager
{
    /**
     * Registry of available marketplace adapters
     */
    protected array $adapters = [
        'shopify' => ShopifyAdapter::class,
        'ebay' => EbayAdapter::class,
        'freemans' => FreemansAdapter::class,
    ];

    /**
     * Create a marketplace adapter instance
     *
     * @param string $marketplace The marketplace name
     * @param string|null $account Optional account name
     * @return MarketplaceAdapter
     */
    public function make(string $marketplace, string $account = null): MarketplaceAdapter
    {
        if (!isset($this->adapters[$marketplace])) {
            throw new InvalidArgumentException("Marketplace '{$marketplace}' is not supported. Available: " . implode(', ', array_keys($this->adapters)));
        }

        // Get the appropriate sync account
        $syncAccount = $this->resolveSyncAccount($marketplace, $account);
        
        // Create the adapter instance
        $adapterClass = $this->adapters[$marketplace];
        
        return new $adapterClass($syncAccount);
    }

    /**
     * Register a new marketplace adapter
     *
     * @param string $marketplace
     * @param string $adapterClass
     * @return void
     */
    public function extend(string $marketplace, string $adapterClass): void
    {
        $this->adapters[$marketplace] = $adapterClass;
    }

    /**
     * Get all registered marketplace names
     *
     * @return array
     */
    public function getRegisteredMarketplaces(): array
    {
        return array_keys($this->adapters);
    }

    /**
     * Resolve the sync account for a marketplace
     *
     * @param string $marketplace
     * @param string|null $account
     * @return SyncAccount|null
     */
    protected function resolveSyncAccount(string $marketplace, string $account = null): ?SyncAccount
    {
        if ($account) {
            // Find specific account by name
            return SyncAccount::findByChannelAndName($marketplace, $account);
        }

        // Get default account for marketplace
        return SyncAccount::getDefaultForChannel($marketplace);
    }
}