<?php

namespace App\Services\Marketplace;

use App\Services\Marketplace\Contracts\MarketplaceInterface;

/**
 * Fluent marketplace sync facade
 *
 * Provides the beautiful API you wanted:
 * - Sync::shopify()->push($products)
 * - Sync::shopify()->pushWithColors($products)
 * - Sync::ebay()->push($products) (when you add it)
 * - Sync::amazon()->push($products) (when you add it)
 */
class MarketplaceSync
{
    protected MarketplaceInterface $marketplace;

    public function __construct(MarketplaceInterface $marketplace)
    {
        $this->marketplace = $marketplace;
    }

    /**
     * Create Shopify sync instance
     */
    public static function shopify(): static
    {
        return new static(app(ShopifyAPI::class));
    }

    /**
     * Create eBay sync instance (when you implement it)
     */
    public static function ebay(): static
    {
        // return new static(app(EbayAPI::class));
        throw new \Exception('eBay integration not implemented yet');
    }

    /**
     * Create Amazon sync instance (when you implement it)
     */
    public static function amazon(): static
    {
        // return new static(app(AmazonAPI::class));
        throw new \Exception('Amazon integration not implemented yet');
    }

    /**
     * Push products normally
     */
    public function push(array $products): array
    {
        return $this->marketplace->push($products);
    }

    /**
     * Push products with color splitting
     */
    public function pushWithColors(array $products): array
    {
        return $this->marketplace->pushWithColors($products);
    }

    /**
     * Pull products from marketplace
     */
    public function pull(array $filters = []): \Illuminate\Support\Collection
    {
        return $this->marketplace->pull($filters);
    }

    /**
     * Test connection
     */
    public function testConnection(): array
    {
        return $this->marketplace->testConnection();
    }

    /**
     * Get the underlying marketplace instance
     */
    public function getMarketplace(): MarketplaceInterface
    {
        return $this->marketplace;
    }
}
