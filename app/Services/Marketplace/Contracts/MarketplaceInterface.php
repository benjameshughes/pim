<?php

namespace App\Services\Marketplace\Contracts;

use Illuminate\Support\Collection;

/**
 * Simple marketplace interface for consistent API across platforms
 */
interface MarketplaceInterface
{
    /**
     * Push products to the marketplace
     */
    public function push(array $products): array;

    /**
     * Push products with color splitting (Shopify-specific but extensible)
     */
    public function pushWithColors(array $products): array;

    /**
     * Pull products from the marketplace
     */
    public function pull(array $filters = []): Collection;

    /**
     * Test connection to the marketplace
     */
    public function testConnection(): array;
}
