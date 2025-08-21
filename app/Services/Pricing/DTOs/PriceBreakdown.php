<?php

namespace App\Services\Pricing\DTOs;

use App\Models\SalesChannel;
use Illuminate\Support\Number;

/**
 * 💰✨ PRICE BREAKDOWN DTO - ELEGANT PRICING DATA STRUCTURE! ✨💰
 *
 * This DTO is SERVING major data organization energy!
 * Clean, structured, and absolutely BEAUTIFUL! 💅
 */
readonly class PriceBreakdown
{
    public function __construct(
        public float $baseCostPrice,
        public float $baseRetailPrice,
        public float $channelMarkup,
        public float $discountAmount,
        public float $shippingCost,
        public float $platformFee,
        public float $paymentFee,
        public float $vatAmount,
        public string $currency,
        public ?SalesChannel $salesChannel = null,
        public bool $isOnSale = false,
        public array $metadata = []
    ) {}

    /**
     * 💎 GET FINAL PRICE - The price customers actually pay
     */
    public function getFinalPriceAttribute(): float
    {
        $price = $this->baseRetailPrice + $this->channelMarkup;
        $price -= $this->discountAmount;
        $price += $this->vatAmount; // Add VAT if not inclusive

        return max(0, $price);
    }

    /**
     * 📊 GET TOTAL COSTS - All our expenses
     */
    public function getTotalCostsAttribute(): float
    {
        return $this->baseCostPrice +
               $this->shippingCost +
               $this->platformFee +
               $this->paymentFee;
    }

    /**
     * 💰 GET PROFIT AMOUNT - The beautiful profit
     */
    public function getProfitAmountAttribute(): float
    {
        return $this->finalPrice - $this->totalCosts;
    }

    /**
     * 📈 GET PROFIT MARGIN - Percentage profit
     */
    public function getProfitMarginAttribute(): float
    {
        return $this->finalPrice > 0
            ? ($this->profitAmount / $this->finalPrice) * 100
            : 0;
    }

    /**
     * 🚀 GET ROI - Return on investment
     */
    public function getRoiAttribute(): float
    {
        return $this->totalCosts > 0
            ? ($this->profitAmount / $this->totalCosts) * 100
            : 0;
    }

    /**
     * 🎨 GET FORMATTED FINAL PRICE - Beautiful currency display
     */
    public function getFormattedFinalPriceAttribute(): string
    {
        return Number::currency($this->finalPrice, in: $this->currency);
    }

    /**
     * 🎨 GET FORMATTED COST PRICE - What we pay
     */
    public function getFormattedCostPriceAttribute(): string
    {
        return Number::currency($this->baseCostPrice, in: $this->currency);
    }

    /**
     * 🎨 GET FORMATTED PROFIT - The money display
     */
    public function getFormattedProfitAttribute(): string
    {
        return Number::currency($this->profitAmount, in: $this->currency);
    }

    /**
     * 📋 GET COST BREAKDOWN - Detailed cost analysis
     */
    public function getCostBreakdownAttribute(): array
    {
        return [
            'base_cost' => $this->baseCostPrice,
            'shipping' => $this->shippingCost,
            'platform_fee' => $this->platformFee,
            'payment_fee' => $this->paymentFee,
            'vat' => $this->vatAmount,
            'total' => $this->totalCosts,
        ];
    }

    /**
     * 📋 GET PRICE BREAKDOWN - Detailed price analysis
     */
    public function getPriceBreakdownAttribute(): array
    {
        return [
            'base_retail' => $this->baseRetailPrice,
            'channel_markup' => $this->channelMarkup,
            'gross_price' => $this->baseRetailPrice + $this->channelMarkup,
            'discount' => $this->discountAmount,
            'net_price' => ($this->baseRetailPrice + $this->channelMarkup) - $this->discountAmount,
            'vat' => $this->vatAmount,
            'final_price' => $this->finalPrice,
        ];
    }

    /**
     * ✨ WITH ADDITIONAL DISCOUNT - Add extra discount
     */
    public function withAdditionalDiscount(float $additionalDiscount): self
    {
        return new self(
            baseCostPrice: $this->baseCostPrice,
            baseRetailPrice: $this->baseRetailPrice,
            channelMarkup: $this->channelMarkup,
            discountAmount: $this->discountAmount + $additionalDiscount,
            shippingCost: $this->shippingCost,
            platformFee: $this->platformFee,
            paymentFee: $this->paymentFee,
            vatAmount: $this->vatAmount,
            currency: $this->currency,
            salesChannel: $this->salesChannel,
            isOnSale: true, // Now it's definitely on sale
            metadata: array_merge($this->metadata, ['additional_discount' => $additionalDiscount])
        );
    }

    /**
     * 🔄 WITH UPDATED CHANNEL - Change sales channel
     */
    public function withChannel(SalesChannel $channel): self
    {
        // Recalculate fees based on new channel
        $newPlatformFee = $this->finalPrice * ($channel->platform_fee_percentage / 100);
        $newPaymentFee = $this->finalPrice * ($channel->payment_fee_percentage / 100);

        return new self(
            baseCostPrice: $this->baseCostPrice,
            baseRetailPrice: $this->baseRetailPrice,
            channelMarkup: $this->channelMarkup,
            discountAmount: $this->discountAmount,
            shippingCost: $channel->base_shipping_cost,
            platformFee: $newPlatformFee,
            paymentFee: $newPaymentFee,
            vatAmount: $this->vatAmount,
            currency: $channel->default_currency,
            salesChannel: $channel,
            isOnSale: $this->isOnSale,
            metadata: $this->metadata
        );
    }

    /**
     * 📊 TO ARRAY - Convert to array for JSON/API responses
     */
    public function toArray(): array
    {
        return [
            'base_cost_price' => $this->baseCostPrice,
            'base_retail_price' => $this->baseRetailPrice,
            'channel_markup' => $this->channelMarkup,
            'discount_amount' => $this->discountAmount,
            'shipping_cost' => $this->shippingCost,
            'platform_fee' => $this->platformFee,
            'payment_fee' => $this->paymentFee,
            'vat_amount' => $this->vatAmount,
            'final_price' => $this->finalPrice,
            'total_costs' => $this->totalCosts,
            'profit_amount' => $this->profitAmount,
            'profit_margin' => $this->profitMargin,
            'roi' => $this->roi,
            'currency' => $this->currency,
            'sales_channel' => $this->salesChannel?->name,
            'is_on_sale' => $this->isOnSale,
            'formatted' => [
                'final_price' => $this->formattedFinalPrice,
                'cost_price' => $this->formattedCostPrice,
                'profit' => $this->formattedProfit,
            ],
            'breakdown' => [
                'costs' => $this->costBreakdown,
                'pricing' => $this->priceBreakdown,
            ],
            'metadata' => $this->metadata,
        ];
    }

    /**
     * 🎭 MAGIC GETTERS - Access calculated properties
     */
    public function __get(string $property): mixed
    {
        return match ($property) {
            'finalPrice' => $this->getFinalPriceAttribute(),
            'totalCosts' => $this->getTotalCostsAttribute(),
            'profitAmount' => $this->getProfitAmountAttribute(),
            'profitMargin' => $this->getProfitMarginAttribute(),
            'roi' => $this->getRoiAttribute(),
            'formattedFinalPrice' => $this->getFormattedFinalPriceAttribute(),
            'formattedCostPrice' => $this->getFormattedCostPriceAttribute(),
            'formattedProfit' => $this->getFormattedProfitAttribute(),
            'costBreakdown' => $this->getCostBreakdownAttribute(),
            'priceBreakdown' => $this->getPriceBreakdownAttribute(),
            default => null,
        };
    }
}
