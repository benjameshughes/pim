<?php

namespace App\Models;

use App\Support\Collections\PricingCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

/**
 * 💰🎭 PRICING MODEL - THE FINANCIAL DIVA OF OUR SYSTEM! 🎭💰
 *
 * Honey, this model is SERVING major financial energy!
 * Multi-channel pricing, discounts, profitability - we do it ALL with SASS! ✨
 */
class Pricing extends Model
{
    use HasFactory;

    protected $table = 'pricing';

    protected $fillable = [
        'product_variant_id',
        'sales_channel_id',
        'cost_price',
        'price',
        'discount_price',
        'margin_percentage',
        'currency',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 💅✨ CUSTOM PRICING COLLECTION - BECAUSE WE'RE EXTRA LIKE THAT! ✨💅
     */
    public function newCollection(array $models = []): Collection
    {
        return new PricingCollection($models);
    }

    /**
     * 🏠 PRODUCT VARIANT RELATIONSHIP
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * 🛍️ SALES CHANNEL RELATIONSHIP
     */
    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    /**
     * 💎 GET CURRENT SELLING PRICE - The final price customers see
     */
    public function getCurrentPriceAttribute(): float
    {
        // If we have a discount price, return that
        if ($this->discount_price) {
            return (float) $this->discount_price;
        }

        // Otherwise return the regular price
        return (float) $this->price;
    }

    /**
     * 🎯 IS ON SALE - Check if currently in sale period
     */
    public function isOnSale(): bool
    {
        return $this->discount_price !== null;
    }

    /**
     * 💸 CALCULATE SALE PRICE - Apply discounts dynamically
     */
    public function calculateSalePrice(): float
    {
        $basePrice = $this->channel_price ?? $this->base_price ?? 0.0;

        if ($this->discount_percentage) {
            return $basePrice * (1 - ($this->discount_percentage / 100));
        }

        if ($this->discount_amount) {
            return max(0, $basePrice - $this->discount_amount);
        }

        return $basePrice;
    }

    /**
     * 📊 CALCULATE PROFIT - The money that makes us RICH!
     */
    public function calculateProfit(): array
    {
        $sellingPrice = $this->current_price;
        $totalCosts = $this->calculateTotalCosts();

        $profitAmount = $sellingPrice - $totalCosts['total'];
        $profitMargin = $sellingPrice > 0 ? ($profitAmount / $sellingPrice) * 100 : 0;
        $roi = $totalCosts['total'] > 0 ? ($profitAmount / $totalCosts['total']) * 100 : 0;

        return [
            'selling_price' => $sellingPrice,
            'total_costs' => $totalCosts['total'],
            'profit_amount' => $profitAmount,
            'profit_margin' => $profitMargin,
            'roi' => $roi,
            'cost_breakdown' => $totalCosts['breakdown'],
        ];
    }

    /**
     * 💰 CALCULATE TOTAL COSTS - All the expenses
     */
    public function calculateTotalCosts(): array
    {
        $sellingPrice = $this->current_price;

        // Base cost
        $costPrice = $this->cost_price;

        // Shipping cost
        $shippingCost = $this->shipping_cost;

        // Platform fees (% of selling price)
        $platformFee = $sellingPrice * ($this->platform_fee_percentage / 100);

        // Payment processing fees (% of selling price)
        $paymentFee = $sellingPrice * ($this->payment_fee_percentage / 100);

        // VAT calculation
        $vatAmount = 0;
        if (! $this->vat_inclusive && $this->vat_rate > 0) {
            $vatAmount = $sellingPrice * ($this->vat_rate / 100);
        }

        $totalCosts = $costPrice + $shippingCost + $platformFee + $paymentFee + $vatAmount;

        return [
            'total' => $totalCosts,
            'breakdown' => [
                'cost_price' => $costPrice,
                'shipping_cost' => $shippingCost,
                'platform_fee' => $platformFee,
                'payment_fee' => $paymentFee,
                'vat_amount' => $vatAmount,
            ],
        ];
    }

    /**
     * 🎨 FORMATTED PRICE - Beautiful currency display
     */
    public function getFormattedPriceAttribute(): string
    {
        return Number::currency($this->current_price, in: $this->currency);
    }

    /**
     * 🎨 FORMATTED COST PRICE - What we pay
     */
    public function getFormattedCostPriceAttribute(): string
    {
        return Number::currency($this->cost_price, in: $this->currency);
    }

    /**
     * 🎨 FORMATTED PROFIT - The beautiful profit display
     */
    public function getFormattedProfitAttribute(): string
    {
        $profit = $this->calculateProfit();

        return Number::currency($profit['profit_amount'], in: $this->currency);
    }

    /**
     * 🔄 UPDATE CALCULATED FIELDS - Refresh the calculated values
     */
    public function updateCalculatedFields(): self
    {
        $profit = $this->calculateProfit();

        $this->update([
            'profit_amount' => $profit['profit_amount'],
            'profit_margin' => $profit['profit_margin'],
            'roi_percentage' => $profit['roi'],
        ]);

        return $this;
    }

    /**
     * 🎯 SCOPE: Active pricing only - TEMPORARILY DISABLED
     * Will be redesigned when pricing/stock are decoupled from products
     */
    public function scopeActive($query)
    {
        return $query; // Removed is_active filter - will be redesigned later
    }

    /**
     * 🎯 SCOPE: For specific channel
     */
    public function scopeForChannel($query, $channelId)
    {
        return $query->where('sales_channel_id', $channelId);
    }

    /**
     * 🎯 SCOPE: Currently on sale
     */
    public function scopeOnSale($query)
    {
        $now = now();

        return $query->where(function ($q) use ($now) {
            $q->where('sale_starts_at', '<=', $now)
                ->where('sale_ends_at', '>=', $now);
        })->orWhere(function ($q) {
            $q->whereNotNull('discount_percentage')
                ->orWhereNotNull('discount_amount');
        });
    }

    /**
     * 🎯 SCOPE: Profitable items
     */
    public function scopeProfitable($query, $minimumMargin = 0)
    {
        return $query->where('profit_margin', '>', $minimumMargin);
    }

    /**
     * 🎯 SCOPE: By currency
     */
    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }
}
