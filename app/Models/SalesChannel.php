<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 🛍️✨ SALES CHANNEL MODEL - WHERE THE MAGIC HAPPENS! ✨🛍️
 *
 * Every diva needs multiple stages to perform on!
 * This model manages all our sales channels with STYLE! 💅
 */
class SalesChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'config',
        'status',
    ];

    protected $casts = [
        'config' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 💰 PRICING RELATIONSHIP
     */
    public function pricing(): HasMany
    {
        return $this->hasMany(Pricing::class);
    }

    /**
     * 💎 GET ACTIVE PRICING
     */
    public function activePricing(): HasMany
    {
        return $this->pricing()->active();
    }

    /**
     * 🎨 GET DISPLAY COLOR - For UI elements
     */
    public function getDisplayColorAttribute(): string
    {
        return $this->color ?: '#3B82F6';
    }

    /**
     * 🔗 GET ICON NAME - For UI display
     */
    public function getIconNameAttribute(): string
    {
        return $this->icon ?: match ($this->name) {
            'shopify' => 'shopping-bag',
            'ebay' => 'building-storefront',
            'amazon' => 'shopping-cart',
            'direct' => 'home',
            'wholesale' => 'building-office',
            default => 'globe-alt'
        };
    }

    /**
     * 📊 GET CHANNEL STATISTICS
     */
    public function getStatsAttribute(): array
    {
        $activePricing = $this->activePricing;

        return [
            'total_products' => $activePricing->count(),
            'average_price' => $activePricing->avg('base_price'),
            'total_value' => $activePricing->sum('base_price'),
            'average_margin' => $activePricing->avg('profit_margin'),
            'profitable_products' => $activePricing->where('profit_margin', '>', 0)->count(),
        ];
    }

    /**
     * 💰 CALCULATE MARKUP PRICE
     */
    public function calculateMarkupPrice(float $basePrice): float
    {
        return $basePrice * (1 + ($this->default_markup_percentage / 100));
    }

    /**
     * 🚚 CALCULATE SHIPPING COST
     */
    public function calculateShipping(float $orderValue): float
    {
        if ($this->free_shipping_available &&
            $this->free_shipping_threshold &&
            $orderValue >= $this->free_shipping_threshold) {
            return 0;
        }

        return $this->base_shipping_cost;
    }

    /**
     * 🎯 SCOPE: Active channels only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * 🎯 SCOPE: Auto-sync enabled
     */
    public function scopeAutoSync($query)
    {
        return $query->where('config->auto_sync', true);
    }

    /**
     * 🎯 SCOPE: By priority order
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('config->priority');
    }

    /**
     * 🌟 STATIC: Get default channel
     */
    public static function getDefault(): ?self
    {
        return static::active()->byPriority()->first();
    }

    /**
     * 🌟 STATIC: Get popular channels
     */
    public static function getPopular(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->byPriority()
            ->whereIn('code', ['shopify', 'ebay', 'amazon', 'direct'])
            ->get();
    }
}
