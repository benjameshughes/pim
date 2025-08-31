<?php

namespace App\Services\Marketplace\Adapters;

use App\Services\Marketplace\ValueObjects\MarketplaceProduct;
use App\Services\Marketplace\ValueObjects\SyncResult;

/**
 * 🏪 EBAY MARKETPLACE ADAPTER
 *
 * Handles eBay-specific product transformations and API operations.
 * eBay keeps all variants as options within a single listing.
 */
class EbayAdapter extends AbstractAdapter
{
    /**
     * Get the marketplace name
     */
    protected function getMarketplaceName(): string
    {
        return 'ebay';
    }

    /**
     * Create eBay-specific product data
     *
     * eBay's behavior: Keep all variants as options in single listing
     */
    public function create(int $productId): MarketplaceProduct
    {
        $product = $this->loadProduct($productId);

        // TODO: Implement eBay transformation actions
        // - TransformToEbayListingAction
        // - eBay keeps color, width/drop as variations in same listing

        $marketplaceProduct = new MarketplaceProduct(
            data: [], // TODO: eBay listing data
            metadata: [
                'original_product_id' => $product->id,
                'transformation_type' => 'single_listing_with_variations',
            ]
        );

        $this->setMarketplaceProduct($marketplaceProduct);

        return $marketplaceProduct;
    }

    /**
     * Push to eBay via REST API
     */
    public function push(): SyncResult
    {
        // TODO: Implement eBay push logic
        return SyncResult::failure('eBay integration not yet implemented');
    }

    /**
     * Test eBay connection
     */
    public function testConnection(): SyncResult
    {
        // TODO: Implement eBay connection test
        return SyncResult::failure('eBay connection test not yet implemented');
    }
}
