<?php

namespace App\Services\Marketplace\Facades;

use App\Services\Marketplace\MarketplaceManager;
use Illuminate\Support\Facades\Facade;

/**
 * 🚀 SYNC FACADE - Clean API Wrapper for Marketplace Integrations
 *
 * Your vision:
 * - Sync::marketplace('shopify')->create($productId)->push()
 * - Sync::marketplace('ebay')->create($productId)->push()
 * - Sync::marketplace('freemans')->create($productId)->push()
 *
 * Each marketplace handles its own transformation and transport logic
 * through dedicated adapters and actions.
 */
class Sync extends Facade
{
    /**
     * Get marketplace adapter instance
     *
     * @param string $marketplace The marketplace name (shopify, ebay, freemans, etc.)
     * @param string|null $account Optional account name for multi-account setups
     */
    public static function marketplace(string $marketplace, string $account = null)
    {
        return app(MarketplaceManager::class)->make($marketplace, $account);
    }

    /**
     * Convenience methods for common marketplaces
     */
    public static function shopify(string $account = null)
    {
        return static::marketplace('shopify', $account);
    }

    public static function ebay(string $account = null)
    {
        return static::marketplace('ebay', $account);
    }

    public static function amazon(string $account = null)
    {
        return static::marketplace('amazon', $account);
    }

    public static function freemans(string $account = null)
    {
        return static::marketplace('freemans', $account);
    }

    /**
     * Get the registered name of the component
     */
    protected static function getFacadeAccessor(): string
    {
        return MarketplaceManager::class;
    }
}
