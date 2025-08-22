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
        'base_price',
        'currency',
        'channel_price',
        'markup_percentage',
        'sale_price',
        'discount_percentage',
        'discount_amount',
        'sale_starts_at',
        'sale_ends_at',
        'shipping_cost',
        'platform_fee_percentage',
        'payment_fee_percentage',
        'vat_rate',
        'vat_inclusive',
        'profit_amount',
        'profit_margin',
        'roi_percentage',
        'is_active',
        'status',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'base_price' => 'decimal:2',
        'channel_price' => 'decimal:2',
        'markup_percentage' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'platform_fee_percentage' => 'decimal:2',
        'payment_fee_percentage' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_inclusive' => 'boolean',
        'profit_amount' => 'decimal:2',
        'profit_margin' => 'decimal:2',
        'roi_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'sale_starts_at' => 'datetime',
        'sale_ends_at' => 'datetime',
        'metadata' => 'array',
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
        $basePrice = $this->channel_price ?? $this->base_price;

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
     * 🎯 SCOPE: Active pricing only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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

    /**
     * 🔥 MODEL EVENTS - Auto-calculate on save
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-calculate profit fields when saving
        static::saving(function (Pricing $pricing) {
            $profit = $pricing->calculateProfit();
            $pricing->profit_amount = $profit['profit_amount'];
            $pricing->profit_margin = $profit['profit_margin'];
            $pricing->roi_percentage = $profit['roi'];
        });
    }
}
