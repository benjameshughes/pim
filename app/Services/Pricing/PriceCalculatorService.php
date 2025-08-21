<?php

namespace App\Services\Pricing;

use App\Models\Pricing;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use App\Services\Pricing\DTOs\PriceBreakdown;
use App\Services\Pricing\DTOs\ProfitAnalysis;

/**
 * 🧮💅 PRICE CALCULATOR SERVICE - THE FINANCIAL GENIUS! 💅🧮
 *
 * Honey, this service is SERVING major mathematical excellence!
 * Complex pricing calculations made simple and SASSY! ✨
 */
class PriceCalculatorService
{
    /**
     * 🎯 CALCULATE FINAL PRICE - The ultimate pricing calculation
     */
    public function calculate(ProductVariant $variant, string|int|SalesChannel|null $channel = null): PriceBreakdown
    {
        $salesChannel = $this->resolveChannel($channel);
        $pricing = $this->getPricing($variant, $salesChannel);

        // If no pricing exists, create a default one
        if (! $pricing) {
            $pricing = $this->createDefaultPricing($variant, $salesChannel);
        }

        $breakdown = new PriceBreakdown(
            baseCostPrice: $pricing->cost_price,
            baseRetailPrice: $pricing->base_price,
            channelMarkup: $this->calculateChannelMarkup($pricing, $salesChannel),
            discountAmount: $this->calculateDiscount($pricing),
            shippingCost: $pricing->shipping_cost,
            platformFee: $this->calculatePlatformFee($pricing, $salesChannel),
            paymentFee: $this->calculatePaymentFee($pricing, $salesChannel),
            vatAmount: $this->calculateVAT($pricing),
            currency: $pricing->currency,
            salesChannel: $salesChannel,
            isOnSale: $pricing->isOnSale()
        );

        return $breakdown;
    }

    /**
     * 💸 APPLY DISCOUNT RULES - Smart discount application
     */
    public function applyDiscount(PriceBreakdown $priceBreakdown, array $discountRules): PriceBreakdown
    {
        $finalPrice = $priceBreakdown->finalPrice;
        $totalDiscount = 0;

        foreach ($discountRules as $rule) {
            $discount = $this->calculateRuleDiscount($finalPrice, $rule);
            $totalDiscount += $discount;
        }

        return $priceBreakdown->withAdditionalDiscount($totalDiscount);
    }

    /**
     * 📊 CALCULATE PROFITABILITY - Complete profit analysis
     */
    public function calculateProfitability(PriceBreakdown $priceBreakdown): ProfitAnalysis
    {
        $revenue = $priceBreakdown->finalPrice;
        $totalCosts = $priceBreakdown->totalCosts;
        $profit = $revenue - $totalCosts;

        $profitMargin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
        $roi = $totalCosts > 0 ? ($profit / $totalCosts) * 100 : 0;

        return new ProfitAnalysis(
            revenue: $revenue,
            totalCosts: $totalCosts,
            profit: $profit,
            profitMargin: $profitMargin,
            roi: $roi,
            costBreakdown: $priceBreakdown->costBreakdown,
            currency: $priceBreakdown->currency
        );
    }

    /**
     * 🎪 BULK PRICE UPDATE - Update multiple variants at once
     */
    public function bulkUpdatePricing(array $variantIds, array $updateData): array
    {
        $results = [
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($variantIds as $variantId) {
            try {
                $variant = ProductVariant::findOrFail($variantId);
                $this->updateVariantPricing($variant, $updateData);
                $results['updated']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Variant {$variantId}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * 🎯 OPTIMIZE PRICING - AI-powered pricing optimization
     */
    public function optimizePricing(ProductVariant $variant, array $options = []): array
    {
        $targetMargin = $options['target_margin'] ?? 25.0;
        $competitorPrice = $options['competitor_price'] ?? null;
        $maxIncrease = $options['max_increase_percent'] ?? 20.0;

        $currentPricing = $this->getPricing($variant);
        $currentBreakdown = $this->calculate($variant);

        $suggestions = [];

        // Cost-plus pricing suggestion
        $costPlusPrice = $this->calculateCostPlusPrice($currentPricing->cost_price, $targetMargin);
        if ($costPlusPrice !== $currentBreakdown->finalPrice) {
            $suggestions[] = [
                'type' => 'cost_plus',
                'current_price' => $currentBreakdown->finalPrice,
                'suggested_price' => $costPlusPrice,
                'reason' => "Achieve {$targetMargin}% profit margin",
                'impact' => $this->calculatePriceChangeImpact($currentBreakdown->finalPrice, $costPlusPrice),
            ];
        }

        // Competitive pricing suggestion
        if ($competitorPrice) {
            $competitivePrice = $competitorPrice * 0.95; // 5% below competitor
            if (abs($competitivePrice - $currentBreakdown->finalPrice) > 1) {
                $suggestions[] = [
                    'type' => 'competitive',
                    'current_price' => $currentBreakdown->finalPrice,
                    'suggested_price' => $competitivePrice,
                    'reason' => 'Stay competitive with market pricing',
                    'impact' => $this->calculatePriceChangeImpact($currentBreakdown->finalPrice, $competitivePrice),
                ];
            }
        }

        return $suggestions;
    }

    /**
     * 🔄 CALCULATE SEASONAL PRICING - Time-based pricing adjustments
     */
    public function calculateSeasonalPricing(ProductVariant $variant, string $season): array
    {
        $basePricing = $this->getPricing($variant);
        $seasonalMultipliers = [
            'spring' => 1.0,
            'summer' => 1.1,  // 10% increase for peak season
            'autumn' => 0.95, // 5% decrease
            'winter' => 0.9,  // 10% decrease for off-season
        ];

        $multiplier = $seasonalMultipliers[$season] ?? 1.0;
        $seasonalPrice = $basePricing->base_price * $multiplier;

        return [
            'base_price' => $basePricing->base_price,
            'seasonal_price' => $seasonalPrice,
            'multiplier' => $multiplier,
            'season' => $season,
            'price_change' => $seasonalPrice - $basePricing->base_price,
            'percentage_change' => (($seasonalPrice - $basePricing->base_price) / $basePricing->base_price) * 100,
        ];
    }

    /**
     * 🔍 RESOLVE CHANNEL - Get channel instance
     */
    protected function resolveChannel(string|int|SalesChannel|null $channel = null): ?SalesChannel
    {
        if ($channel instanceof SalesChannel) {
            return $channel;
        }

        if (is_string($channel)) {
            return SalesChannel::where('name', $channel)->first();
        }

        if (is_int($channel)) {
            return SalesChannel::find($channel);
        }

        return SalesChannel::getDefault();
    }

    /**
     * 💰 GET PRICING - Find or create pricing record
     */
    protected function getPricing(ProductVariant $variant, ?SalesChannel $channel = null): ?Pricing
    {
        return Pricing::where('product_variant_id', $variant->id)
            ->where('sales_channel_id', $channel?->id)
            ->active()
            ->first();
    }

    /**
     * ✨ CREATE DEFAULT PRICING - Bootstrap pricing for new variants
     */
    protected function createDefaultPricing(ProductVariant $variant, ?SalesChannel $channel = null): Pricing
    {
        $basePrice = $variant->price ?? 50.00; // Fallback price
        $costPrice = $basePrice * 0.6; // Assume 40% markup by default

        return Pricing::create([
            'product_variant_id' => $variant->id,
            'sales_channel_id' => $channel?->id,
            'cost_price' => $costPrice,
            'base_price' => $basePrice,
            'currency' => $channel?->default_currency ?? 'GBP',
            'platform_fee_percentage' => $channel?->platform_fee_percentage ?? 0,
            'payment_fee_percentage' => $channel?->payment_fee_percentage ?? 2.9,
            'vat_rate' => 20.0,
            'vat_inclusive' => true,
            'shipping_cost' => $channel?->base_shipping_cost ?? 5.99,
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    /**
     * 📈 CALCULATE CHANNEL MARKUP
     */
    protected function calculateChannelMarkup(Pricing $pricing, ?SalesChannel $channel): float
    {
        if ($pricing->channel_price) {
            return $pricing->channel_price - $pricing->base_price;
        }

        if ($channel && $channel->default_markup_percentage > 0) {
            return $pricing->base_price * ($channel->default_markup_percentage / 100);
        }

        return 0;
    }

    /**
     * 💸 CALCULATE DISCOUNT
     */
    protected function calculateDiscount(Pricing $pricing): float
    {
        if (! $pricing->isOnSale()) {
            return 0;
        }

        $basePrice = $pricing->channel_price ?? $pricing->base_price;

        if ($pricing->discount_percentage) {
            return $basePrice * ($pricing->discount_percentage / 100);
        }

        if ($pricing->discount_amount) {
            return min($pricing->discount_amount, $basePrice);
        }

        return 0;
    }

    /**
     * 🏢 CALCULATE PLATFORM FEE
     */
    protected function calculatePlatformFee(Pricing $pricing, ?SalesChannel $channel): float
    {
        $feePercentage = $pricing->platform_fee_percentage ?: ($channel?->platform_fee_percentage ?? 0);
        $price = $pricing->channel_price ?? $pricing->base_price;

        return $price * ($feePercentage / 100);
    }

    /**
     * 💳 CALCULATE PAYMENT FEE
     */
    protected function calculatePaymentFee(Pricing $pricing, ?SalesChannel $channel): float
    {
        $feePercentage = $pricing->payment_fee_percentage ?: ($channel?->payment_fee_percentage ?? 2.9);
        $price = $pricing->channel_price ?? $pricing->base_price;

        return $price * ($feePercentage / 100);
    }

    /**
     * 🧾 CALCULATE VAT
     */
    protected function calculateVAT(Pricing $pricing): float
    {
        if ($pricing->vat_inclusive) {
            return 0; // VAT already included in price
        }

        $price = $pricing->channel_price ?? $pricing->base_price;

        return $price * ($pricing->vat_rate / 100);
    }

    /**
     * 📊 CALCULATE COST-PLUS PRICE
     */
    protected function calculateCostPlusPrice(float $costPrice, float $targetMargin): float
    {
        return $costPrice / (1 - ($targetMargin / 100));
    }

    /**
     * 📈 CALCULATE PRICE CHANGE IMPACT
     */
    protected function calculatePriceChangeImpact(float $currentPrice, float $newPrice): array
    {
        $change = $newPrice - $currentPrice;
        $percentageChange = $currentPrice > 0 ? ($change / $currentPrice) * 100 : 0;

        return [
            'price_change' => $change,
            'percentage_change' => $percentageChange,
            'direction' => $change > 0 ? 'increase' : 'decrease',
        ];
    }

    /**
     * 🎯 CALCULATE RULE DISCOUNT
     */
    protected function calculateRuleDiscount(float $price, array $rule): float
    {
        return match ($rule['type']) {
            'percentage' => $price * ($rule['value'] / 100),
            'fixed' => $rule['value'],
            'buy_x_get_y' => $this->calculateBuyXGetYDiscount($price, $rule),
            default => 0,
        };
    }

    /**
     * 🛍️ CALCULATE BUY X GET Y DISCOUNT
     */
    protected function calculateBuyXGetYDiscount(float $price, array $rule): float
    {
        // This would be more complex in a real scenario with cart quantities
        return 0;
    }

    /**
     * 🔄 UPDATE VARIANT PRICING
     */
    protected function updateVariantPricing(ProductVariant $variant, array $updateData): void
    {
        $pricing = $this->getPricing($variant) ?? $this->createDefaultPricing($variant);

        $pricing->update($updateData);
        $pricing->updateCalculatedFields();
    }
}
