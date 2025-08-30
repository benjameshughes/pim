<?php

namespace App\Services\Marketplace\Contracts;

use App\Services\Marketplace\ValueObjects\MarketplaceProduct;
use App\Services\Marketplace\ValueObjects\SyncResult;

/**
 * 🤝 MARKETPLACE ADAPTER CONTRACT
 *
 * The core interface that all marketplace integrations must implement.
 * This ensures consistent behavior across all marketplaces while allowing
 * each to handle their specific transformation and push logic.
 */
interface MarketplaceAdapter
{
    /**
     * Create marketplace-specific product data from a local product
     *
     * This is where marketplace-specific transformations happen:
     * - Shopify: Split product by colors
     * - eBay: Keep variants as options
     * - Freemans: Generate CSV row
     *
     * @param int $productId Local product ID
     * @return self Returns the adapter to allow fluent chaining
     */
    public function create(int $productId): self;

    /**
     * Push the prepared product data to the marketplace
     *
     * Transport mechanism varies by marketplace:
     * - Shopify: GraphQL mutations
     * - eBay: REST API calls
     * - Freemans: CSV upload
     *
     * @return SyncResult Success/failure result with details
     */
    public function push(): SyncResult;

    /**
     * Test the connection to the marketplace
     *
     * @return SyncResult Connection test result
     */
    public function testConnection(): SyncResult;

    /**
     * Pull data from marketplace (optional - not all marketplaces support this)
     *
     * @param array $filters Optional filters for the pull operation
     * @return SyncResult Pull result with data
     */
    public function pull(array $filters = []): SyncResult;
}