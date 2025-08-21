<?php

namespace App\Services\Marketplace\Facades;

use App\Services\Marketplace\MarketplaceSync;
use Illuminate\Support\Facades\Facade;

/**
 * Sync facade for beautiful marketplace integration
 *
 * Usage:
 * - Sync::shopify()->push($products)
 * - Sync::shopify()->pushWithColors($products)
 * - Sync::shopify()->pull(['limit' => 10])
 * - Sync::shopify()->testConnection()
 *
 * Future:
 * - Sync::ebay()->push($products)
 * - Sync::amazon()->push($products)
 */
class Sync extends Facade
{
    /**
     * Get Shopify sync instance
     */
    public static function shopify(): MarketplaceSync
    {
        return MarketplaceSync::shopify();
    }

    /**
     * Get eBay sync instance (when implemented)
     */
    public static function ebay(): MarketplaceSync
    {
        return MarketplaceSync::ebay();
    }

    /**
     * Get Amazon sync instance (when implemented)
     */
    public static function amazon(): MarketplaceSync
    {
        return MarketplaceSync::amazon();
    }

    /**
     * This facade doesn't need a typical getFacadeAccessor
     * since we're using static factory methods
     */
    protected static function getFacadeAccessor(): string
    {
        return 'marketplace.sync';
    }
}
