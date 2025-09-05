<?php

namespace App\Services\Pricing\Actions;

use App\Models\ProductVariant;

/**
 * UpdateVariantBasePriceAction
 *
 * Writes base price (and optional discount_price placeholder if you use one)
 * directly to the product_variants table.
 */
class UpdateVariantBasePriceAction
{
    /**
     * Update base price for a variant. Discount price is optional and only
     * applied if you also track it at the base level (many stores do not).
     */
    public function update(int $variantId, ?float $price, ?float $discountPrice = null): void
    {
        $variant = ProductVariant::find($variantId);
        if (! $variant) {
            return;
        }

        $payload = [];
        if ($price !== null) {
            $payload['price'] = $price;
        }
        // If your schema tracks discount at base level, uncomment below
        // if ($discountPrice !== null) {
        //     $payload['discount_price'] = $discountPrice;
        // }

        if (!empty($payload)) {
            $variant->update($payload);
        }
    }
}

