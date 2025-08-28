<?php

namespace App\Actions\Pricing;

use App\Models\Pricing;
use App\Models\ProductVariant;
use Exception;

class AssignPricing
{
    public function execute(ProductVariant $variant, ?float $price = null, ?int $salesChannelId = null): ?Pricing
    {
        try {
            // Skip if no price provided
            if ($price === null || $price <= 0) {
                return null;
            }

            // Use default sales channel if none provided
            if ($salesChannelId === null) {
                $salesChannelId = 1; // Default sales channel ID
            }

            // Create or update pricing record for this variant
            $pricing = Pricing::updateOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'sales_channel_id' => $salesChannelId,
                ],
                [
                    'price' => $price,
                    'currency' => 'GBP', // Default currency
                    'updated_at' => now()
                ]
            );

            return $pricing;

        } catch (Exception $e) {
            throw new Exception('Failed to assign pricing: ' . $e->getMessage());
        }
    }
}