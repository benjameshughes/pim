<?php

namespace App\Services;

use App\Models\Pricing;
use App\Models\ProductVariant;
use App\Models\SalesChannel;

/**
 * 💰 PRICING SERVICE - Independent Pricing Management
 * 
 * Pricing operates independently from variants and manages its own lifecycle
 */
class PricingService
{
    /**
     * 📊 GET PRICING FOR VARIANT
     * Get all pricing records for a specific variant
     */
    public function getPricingForVariant(int $variantId): \Illuminate\Support\Collection
    {
        return Pricing::where('product_variant_id', $variantId)->get();
    }

    /**
     * 💎 GET ACTIVE PRICING FOR VARIANT
     * Get only active pricing records for a variant
     */
    public function getActivePricingForVariant(int $variantId): \Illuminate\Support\Collection
    {
        return Pricing::where('product_variant_id', $variantId)->active()->get();
    }

    /**
     * 🎯 GET PRICE FOR VARIANT AND CHANNEL
     * Get specific pricing for variant + sales channel combination
     */
    public function getPriceForVariantAndChannel(int $variantId, int $channelId): ?float
    {
        $pricing = Pricing::where('product_variant_id', $variantId)
            ->where('sales_channel_id', $channelId)
            ->active()
            ->first();

        return $pricing ? $pricing->calculateSalePrice() : null;
    }

    /**
     * 🏗️ CREATE PRICING RECORD
     * Create new pricing record (independent operation)
     */
    public function createPricing(array $data): Pricing
    {
        return Pricing::create([
            'product_variant_id' => $data['product_variant_id'],
            'sales_channel_id' => $data['sales_channel_id'],
            'price' => $data['price'],
            'cost_price' => $data['cost_price'] ?? null,
            'discount_price' => $data['discount_price'] ?? null,
            'margin_percentage' => $data['margin_percentage'] ?? null,
            'currency' => $data['currency'] ?? 'GBP',
        ]);
    }

    /**
     * ✏️ UPDATE PRICING RECORD
     * Update existing pricing record
     */
    public function updatePricing(Pricing $pricing, array $data): Pricing
    {
        $pricing->update($data);
        return $pricing->fresh();
    }

    /**
     * 🗑️ DELETE PRICING RECORD
     * Remove pricing record (independent operation)
     */
    public function deletePricing(Pricing $pricing): bool
    {
        return $pricing->delete();
    }

    /**
     * 📈 BULK PRICING OPERATIONS
     * Create multiple pricing records at once
     */
    public function bulkCreatePricing(int $variantId, array $channelPrices): \Illuminate\Support\Collection
    {
        $pricingRecords = collect();

        foreach ($channelPrices as $channelId => $priceData) {
            $pricing = $this->createPricing([
                'product_variant_id' => $variantId,
                'sales_channel_id' => $channelId,
                ...$priceData
            ]);
            
            $pricingRecords->push($pricing);
        }

        return $pricingRecords;
    }

    /**
     * 🧮 CALCULATE VARIANT STATISTICS
     * Get pricing statistics for a variant across all channels
     */
    public function getVariantPricingStats(int $variantId): array
    {
        $pricingRecords = $this->getActivePricingForVariant($variantId);
        
        if ($pricingRecords->isEmpty()) {
            return [
                'min_price' => 0,
                'max_price' => 0,
                'avg_price' => 0,
                'channel_count' => 0
            ];
        }

        $prices = $pricingRecords->map(fn($pricing) => $pricing->calculateSalePrice());

        return [
            'min_price' => $prices->min(),
            'max_price' => $prices->max(),
            'avg_price' => $prices->avg(),
            'channel_count' => $pricingRecords->count()
        ];
    }

    /**
     * 🎨 GET DEFAULT PRICE FOR VARIANT
     * Get the primary/default price for a variant (used by $variant->price accessor)
     */
    public function getDefaultPriceForVariant(int $variantId): float
    {
        // Priority: Default channel -> First active -> 0.0
        $defaultChannel = SalesChannel::getDefault();
        
        if ($defaultChannel) {
            $price = $this->getPriceForVariantAndChannel($variantId, $defaultChannel->id);
            if ($price !== null) return $price;
        }

        // Fallback to first active pricing
        $pricing = Pricing::where('product_variant_id', $variantId)->active()->first();
        return $pricing ? $pricing->calculateSalePrice() : 0.0;
    }
}