<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pricing extends Model
{
    protected $table = 'pricing';

    protected $fillable = [
        'product_variant_id',
        'marketplace',
        'currency',
        'retail_price',
        'cost_price',
        'vat_percentage',
        'vat_amount',
        'vat_inclusive',
        'shipping_cost',
        'channel_fee_percentage',
        'channel_fee_amount',
        'profit_amount',
        'profit_margin_percentage',
        'total_cost',
        'final_price',
    ];

    protected $casts = [
        'retail_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'vat_inclusive' => 'boolean',
        'shipping_cost' => 'decimal:2',
        'channel_fee_percentage' => 'decimal:2',
        'channel_fee_amount' => 'decimal:2',
        'profit_amount' => 'decimal:2',
        'profit_margin_percentage' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'final_price' => 'decimal:2',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class, 'marketplace', 'slug');
    }

    /**
     * Calculate all pricing fields automatically
     */
    public function calculatePricing(): self
    {
        // Start with retail price
        $retailPrice = (float) $this->retail_price;
        $costPrice = (float) ($this->cost_price ?? 0);

        if ($retailPrice <= 0) {
            return $this;
        }

        // Calculate VAT
        $this->calculateVAT();

        // Calculate channel fees
        $this->calculateChannelFees();

        // Calculate total costs and profit
        $this->calculateProfitAndCosts();

        return $this;
    }

    /**
     * Calculate VAT amount based on percentage and inclusive/exclusive setting
     */
    protected function calculateVAT(): void
    {
        $retailPrice = (float) $this->retail_price;
        $vatPercentage = (float) $this->vat_percentage;

        if ($vatPercentage > 0) {
            if ($this->vat_inclusive) {
                // VAT is included in retail price
                $this->vat_amount = $retailPrice - ($retailPrice / (1 + ($vatPercentage / 100)));
            } else {
                // VAT is additional to retail price
                $this->vat_amount = $retailPrice * ($vatPercentage / 100);
            }
        } else {
            $this->vat_amount = 0;
        }
    }

    /**
     * Calculate channel fees
     */
    protected function calculateChannelFees(): void
    {
        $retailPrice = (float) $this->retail_price;
        $feePercentage = (float) $this->channel_fee_percentage;

        if ($feePercentage > 0) {
            $this->channel_fee_amount = $retailPrice * ($feePercentage / 100);
        } else {
            // Try to get fee from sales channel
            $channel = $this->salesChannel;
            if ($channel) {
                $this->channel_fee_percentage = $channel->default_fee_percentage;
                $this->channel_fee_amount = $channel->calculateFee($retailPrice);
            } else {
                $this->channel_fee_amount = 0;
            }
        }
    }

    /**
     * Calculate profit and total costs
     */
    protected function calculateProfitAndCosts(): void
    {
        $retailPrice = (float) $this->retail_price;
        $costPrice = (float) ($this->cost_price ?? 0);
        $vatAmount = (float) ($this->vat_amount ?? 0);
        $shippingCost = (float) ($this->shipping_cost ?? 0);
        $channelFeeAmount = (float) ($this->channel_fee_amount ?? 0);

        // Total cost (all expenses)
        $this->total_cost = $costPrice + $shippingCost + $channelFeeAmount;

        // Final price (what we actually receive)
        if ($this->vat_inclusive) {
            $this->final_price = $retailPrice - $channelFeeAmount;
        } else {
            $this->final_price = ($retailPrice + $vatAmount) - $channelFeeAmount;
        }

        // Profit calculation
        $this->profit_amount = $this->final_price - $this->total_cost;

        // Profit margin percentage
        if ($this->final_price > 0) {
            $this->profit_margin_percentage = ($this->profit_amount / $this->final_price) * 100;
        } else {
            $this->profit_margin_percentage = 0;
        }
    }

    /**
     * Get net price (price without VAT)
     */
    public function getNetPriceAttribute(): float
    {
        if ($this->vat_inclusive) {
            return $this->retail_price - ($this->vat_amount ?? 0);
        }

        return $this->retail_price;
    }

    /**
     * Get gross price (price including VAT)
     */
    public function getGrossPriceAttribute(): float
    {
        if ($this->vat_inclusive) {
            return $this->retail_price;
        }

        return $this->retail_price + ($this->vat_amount ?? 0);
    }

    /**
     * Check if this pricing is profitable
     */
    public function isProfitable(): bool
    {
        return ($this->profit_amount ?? 0) > 0;
    }

    /**
     * Get profit margin as formatted percentage
     */
    public function getFormattedProfitMargin(): string
    {
        return number_format($this->profit_margin_percentage ?? 0, 1).'%';
    }

    /**
     * Auto-calculate and save pricing
     */
    public function recalculateAndSave(): bool
    {
        $this->calculatePricing();

        return $this->save();
    }

    /**
     * Scope for specific marketplace
     */
    public function scopeForMarketplace($query, string $marketplace)
    {
        return $query->where('marketplace', $marketplace);
    }

    /**
     * Scope for profitable items only
     */
    public function scopeProfitable($query)
    {
        return $query->where('profit_amount', '>', 0);
    }
}
