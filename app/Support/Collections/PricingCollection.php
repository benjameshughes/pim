<?php

namespace App\Support\Collections;

use App\Models\Pricing;
use App\Models\SalesChannel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Number;

/**
 * 💰🎭 PRICING COLLECTION - THE MATHEMATICAL DIVA! 🎭💰
 *
 * Honey, this collection is SERVING major financial calculation energy!
 * Profit margins, currency conversions, channel comparisons - we do MATH with SASS! ✨
 */
class PricingCollection extends Collection
{
    /**
     * 🛍️ FOR CHANNEL - Filter by specific sales channel
     */
    public function forChannel(string|int|SalesChannel $channel): self
    {
        $channelId = $channel instanceof SalesChannel ? $channel->id : $channel;

        return $this->filter(function (Pricing $pricing) use ($channelId) {
            return $pricing->sales_channel_id === $channelId;
        });
    }

    /**
     * 🌍 BY CURRENCY - Filter by currency
     */
    public function byCurrency(string $currency): self
    {
        return $this->filter(fn (Pricing $pricing) => $pricing->currency === $currency);
    }

    /**
     * 💸 ON SALE - Only items currently on sale
     */
    public function onSale(): self
    {
        return $this->filter(fn (Pricing $pricing) => $pricing->isOnSale());
    }

    /**
     * 💰 PROFITABLE - Only items with positive profit margins
     */
    public function profitable(float $minimumMargin = 0): self
    {
        return $this->filter(function (Pricing $pricing) use ($minimumMargin) {
            $profit = $pricing->calculateProfit();

            return $profit['profit_margin'] > $minimumMargin;
        });
    }

    /**
     * 📊 CALCULATE TOTAL REVENUE - Sum all selling prices
     */
    public function totalRevenue(?string $currency = null): float
    {
        $items = $currency ? $this->byCurrency($currency) : $this;

        return $items->sum(function (Pricing $pricing) {
            return $pricing->current_price;
        });
    }

    /**
     * 💎 CALCULATE TOTAL PROFIT - Sum all profit amounts
     */
    public function totalProfit(?string $currency = null): float
    {
        $items = $currency ? $this->byCurrency($currency) : $this;

        return $items->sum(function (Pricing $pricing) {
            $profit = $pricing->calculateProfit();

            return $profit['profit_amount'];
        });
    }

    /**
     * 📈 CALCULATE TOTAL COSTS - Sum all cost prices
     */
    public function totalCosts(?string $currency = null): float
    {
        $items = $currency ? $this->byCurrency($currency) : $this;

        return $items->sum(function (Pricing $pricing) {
            $costs = $pricing->calculateTotalCosts();

            return $costs['total'];
        });
    }

    /**
     * 🎯 AVERAGE PROFIT MARGIN - Calculate average margin across all items
     */
    public function averageProfitMargin(): float
    {
        if ($this->isEmpty()) {
            return 0;
        }

        $totalMargin = $this->sum(function (Pricing $pricing) {
            $profit = $pricing->calculateProfit();

            return $profit['profit_margin'];
        });

        return $totalMargin / $this->count();
    }

    /**
     * 🚀 CALCULATE ROI - Return on investment
     */
    public function averageROI(): float
    {
        if ($this->isEmpty()) {
            return 0;
        }

        $totalROI = $this->sum(function (Pricing $pricing) {
            $profit = $pricing->calculateProfit();

            return $profit['roi'];
        });

        return $totalROI / $this->count();
    }

    /**
     * 📊 PROFIT ANALYSIS - Comprehensive profit breakdown
     */
    public function profitAnalysis(?string $currency = null): array
    {
        $items = $currency ? $this->byCurrency($currency) : $this;

        if ($items->isEmpty()) {
            return [
                'total_revenue' => 0,
                'total_costs' => 0,
                'total_profit' => 0,
                'average_margin' => 0,
                'average_roi' => 0,
                'profitable_items' => 0,
                'loss_making_items' => 0,
                'currency' => $currency ?: 'GBP',
            ];
        }

        $profitableItems = $items->profitable();
        $lossMakingItems = $items->filter(function (Pricing $pricing) {
            $profit = $pricing->calculateProfit();

            return $profit['profit_amount'] < 0;
        });

        return [
            'total_revenue' => $items->totalRevenue(),
            'total_costs' => $items->totalCosts(),
            'total_profit' => $items->totalProfit(),
            'average_margin' => $items->averageProfitMargin(),
            'average_roi' => $items->averageROI(),
            'profitable_items' => $profitableItems->count(),
            'loss_making_items' => $lossMakingItems->count(),
            'currency' => $currency ?: 'GBP',
        ];
    }

    /**
     * 🎪 CHANNEL COMPARISON - Compare performance across channels
     */
    public function channelComparison(): array
    {
        $channels = [];

        $this->groupBy('sales_channel_id')->each(function ($channelPricing, $channelId) {
            $channel = $channelPricing->first()->salesChannel;

            if ($channel) {
                $analysis = $channelPricing->profitAnalysis();
                $channels[$channel->display_name] = array_merge($analysis, [
                    'channel_id' => $channelId,
                    'channel_name' => $channel->name,
                    'item_count' => $channelPricing->count(),
                    'average_price' => $channelPricing->avg(fn ($p) => $p->current_price),
                ]);
            }
        });

        return $channels;
    }

    /**
     * 💸 DISCOUNT ANALYSIS - Analyze discount effectiveness
     */
    public function discountAnalysis(): array
    {
        $onSaleItems = $this->onSale();
        $regularItems = $this->reject(fn ($p) => $p->isOnSale());

        return [
            'total_items' => $this->count(),
            'on_sale_items' => $onSaleItems->count(),
            'regular_items' => $regularItems->count(),
            'sale_percentage' => $this->count() > 0 ? ($onSaleItems->count() / $this->count()) * 100 : 0,
            'average_discount' => $onSaleItems->avg(function (Pricing $pricing) {
                $basePrice = $pricing->channel_price ?? $pricing->base_price;
                $salePrice = $pricing->calculateSalePrice();

                return $basePrice > 0 ? (($basePrice - $salePrice) / $basePrice) * 100 : 0;
            }),
            'revenue_impact' => [
                'with_discounts' => $this->totalRevenue(),
                'without_discounts' => $this->sum(fn ($p) => $p->channel_price ?? $p->base_price),
            ],
        ];
    }

    /**
     * 🎨 APPLY BULK DISCOUNT - Apply discount to all items
     */
    public function applyBulkDiscount(float $discountPercentage, ?\DateTime $startDate = null, ?\DateTime $endDate = null): self
    {
        return $this->map(function (Pricing $pricing) use ($discountPercentage, $startDate, $endDate) {
            $pricing->update([
                'discount_percentage' => $discountPercentage,
                'sale_starts_at' => $startDate,
                'sale_ends_at' => $endDate,
            ]);

            return $pricing->fresh();
        });
    }

    /**
     * 🔄 APPLY CHANNEL MARKUP - Apply markup for specific channel
     */
    public function applyChannelMarkup(SalesChannel $channel): self
    {
        return $this->map(function (Pricing $pricing) use ($channel) {
            $markupPrice = $channel->calculateMarkupPrice($pricing->base_price);

            $pricing->update([
                'channel_price' => $markupPrice,
                'markup_percentage' => $channel->default_markup_percentage,
            ]);

            return $pricing->fresh();
        });
    }

    /**
     * 💱 CURRENCY CONVERSION - Convert prices to different currency
     * (This would integrate with a real currency API in production)
     */
    public function convertCurrency(string $targetCurrency, float $exchangeRate): array
    {
        return $this->map(function (Pricing $pricing) use ($targetCurrency, $exchangeRate) {
            return [
                'product_variant_id' => $pricing->product_variant_id,
                'original_price' => $pricing->current_price,
                'original_currency' => $pricing->currency,
                'converted_price' => $pricing->current_price * $exchangeRate,
                'target_currency' => $targetCurrency,
                'exchange_rate' => $exchangeRate,
            ];
        })->toArray();
    }

    /**
     * 📊 PRICE RANGE ANALYSIS - Analyze price distribution
     */
    public function priceRangeAnalysis(): array
    {
        $prices = $this->map(fn ($p) => $p->current_price)->sort();

        if ($prices->isEmpty()) {
            return [
                'min' => 0,
                'max' => 0,
                'average' => 0,
                'median' => 0,
                'quartiles' => [0, 0, 0],
                'distribution' => [],
            ];
        }

        $count = $prices->count();
        $median = $count % 2 === 0
            ? ($prices[$count / 2 - 1] + $prices[$count / 2]) / 2
            : $prices[floor($count / 2)];

        // Create price distribution buckets
        $min = $prices->min();
        $max = $prices->max();
        $bucketSize = ($max - $min) / 5;
        $distribution = [];

        for ($i = 0; $i < 5; $i++) {
            $bucketMin = $min + ($i * $bucketSize);
            $bucketMax = $min + (($i + 1) * $bucketSize);
            $count = $prices->filter(fn ($p) => $p >= $bucketMin && $p <= $bucketMax)->count();

            $distribution[] = [
                'range' => Number::currency($bucketMin).' - '.Number::currency($bucketMax),
                'count' => $count,
                'percentage' => $this->count() > 0 ? ($count / $this->count()) * 100 : 0,
            ];
        }

        return [
            'min' => $prices->min(),
            'max' => $prices->max(),
            'average' => $prices->avg(),
            'median' => $median,
            'quartiles' => [
                $prices[floor($count * 0.25)],
                $median,
                $prices[floor($count * 0.75)],
            ],
            'distribution' => $distribution,
        ];
    }

    /**
     * 🎯 OPTIMIZATION SUGGESTIONS - AI-powered pricing suggestions
     */
    public function optimizationSuggestions(): array
    {
        $analysis = $this->profitAnalysis();
        $suggestions = [];

        // Low margin suggestions
        $lowMarginItems = $this->filter(function (Pricing $pricing) {
            $profit = $pricing->calculateProfit();

            return $profit['profit_margin'] < 10;
        });

        if ($lowMarginItems->count() > 0) {
            $suggestions[] = [
                'type' => 'low_margin',
                'title' => 'Low Profit Margin Items',
                'description' => "You have {$lowMarginItems->count()} items with profit margins below 10%",
                'action' => 'Consider increasing prices or reducing costs',
                'impact' => 'Could increase total profit by '.Number::currency($lowMarginItems->count() * 5),
            ];
        }

        // Overpriced suggestions
        $expensiveItems = $this->filter(function (Pricing $pricing) {
            $profit = $pricing->calculateProfit();

            return $profit['profit_margin'] > 70;
        });

        if ($expensiveItems->count() > 0) {
            $suggestions[] = [
                'type' => 'high_margin',
                'title' => 'High Margin Opportunities',
                'description' => "You have {$expensiveItems->count()} items with very high margins",
                'action' => 'Consider reducing prices to increase volume',
                'impact' => 'Could increase sales volume significantly',
            ];
        }

        // Sale items suggestion
        $regularItems = $this->reject(fn ($p) => $p->isOnSale());
        if ($regularItems->count() > $this->count() * 0.8) {
            $suggestions[] = [
                'type' => 'sales_opportunity',
                'title' => 'Sales Event Opportunity',
                'description' => 'Most items are at regular price',
                'action' => 'Consider running a promotional sale',
                'impact' => 'Could increase sales volume by 20-40%',
            ];
        }

        return $suggestions;
    }

    /**
     * 🎨 FORMAT FOR DISPLAY - Beautiful formatting for UI
     */
    public function formatForDisplay(string $currency = 'GBP'): array
    {
        return $this->map(function (Pricing $pricing) use ($currency) {
            $profit = $pricing->calculateProfit();

            return [
                'id' => $pricing->id,
                'product_name' => $pricing->productVariant->product->name ?? 'Unknown Product',
                'variant_sku' => $pricing->productVariant->sku ?? 'No SKU',
                'channel' => $pricing->salesChannel->display_name ?? 'Default',
                'current_price' => Number::currency($pricing->current_price, in: $currency),
                'cost_price' => Number::currency($pricing->cost_price, in: $currency),
                'profit_amount' => Number::currency($profit['profit_amount'], in: $currency),
                'profit_margin' => number_format($profit['profit_margin'], 1).'%',
                'roi' => number_format($profit['roi'], 1).'%',
                'is_on_sale' => $pricing->isOnSale(),
                'status' => $pricing->status,
            ];
        })->toArray();
    }
}
