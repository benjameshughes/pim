<?php

namespace App\Services\Helpers\Marketplace;

use App\Models\Product;

/**
 * MarketplaceAttributeHelper
 *
 * Prepare and transform attributes for marketplace-specific usage.
 */
class MarketplaceAttributeHelper
{
    /**
     * Get transformed attributes for a marketplace: key => transformed value.
     */
    public function transformedFor(Product $product, string $marketplace): array
    {
        $result = [];
        foreach ($product->validAttributes as $attribute) {
            $result[$attribute->getAttributeKey()] = $attribute->getValueForMarketplace($marketplace);
        }
        return $result;
    }
}

