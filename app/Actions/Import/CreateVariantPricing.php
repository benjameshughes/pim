<?php

namespace App\Actions\Import;

use App\Models\ProductVariant;
use App\Models\VariantPricing;

class CreateVariantPricing
{
    public function execute(ProductVariant $variant, array $pricingData): void
    {
        $retailPrice = (float) ($pricingData['retail_price'] ?? 0);

        if ($retailPrice > 0) {
            // Calculate VAT (20% inclusive by default)
            $vatRate = 0.20;
            $priceExcludingVat = $retailPrice / (1 + $vatRate);
            $vatAmount = $retailPrice - $priceExcludingVat;

            VariantPricing::create([
                'variant_id' => $variant->id,
                'marketplace' => 'default',
                'price_excluding_vat' => round($priceExcludingVat, 2),
                'vat_rate' => $vatRate,
                'vat_amount' => round($vatAmount, 2),
                'price_including_vat' => $retailPrice,
                'currency' => 'GBP',
                'is_active' => true,
            ]);
        }
    }
}
