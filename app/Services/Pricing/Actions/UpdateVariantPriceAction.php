<?php

namespace App\Services\Pricing\Actions;

use App\Models\Pricing as PricingModel;

/**
 * UpdateVariantPriceAction
 *
 * Persists pricing changes for a single variant in a specific sales channel.
 * Expected fields: price, discount_price, cost_price.
 */
class UpdateVariantPriceAction
{
    /**
     * Persist changes for a single variant.
     * $fields = ['price' => 19.99, 'discount_price' => null, 'cost_price' => 8.50]
     */
    public function execute(int $variantId, ?int $salesChannelId, array $fields): void
    {
        if (! $salesChannelId) {
            return; // channel not resolved; skip
        }

        $payload = [];
        foreach (['price', 'discount_price', 'cost_price', 'currency'] as $k) {
            if (array_key_exists($k, $fields)) {
                $payload[$k] = $fields[$k];
            }
        }

        if (empty($payload)) {
            return; // nothing to update
        }

        PricingModel::updateOrCreate(
            [
                'product_variant_id' => $variantId,
                'sales_channel_id' => $salesChannelId,
            ],
            $payload
        );
    }
}
