<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SalesChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'default_fee_percentage',
        'fixed_fee_amount',
        'fee_structure',
        'currency',
        'is_active',
        'description',
    ];

    protected $casts = [
        'default_fee_percentage' => 'decimal:2',
        'fixed_fee_amount' => 'decimal:2',
        'fee_structure' => 'array',
        'is_active' => 'boolean',
    ];

    public const TYPES = [
        'marketplace' => 'Marketplace',
        'website' => 'Website',
        'retail' => 'Retail Store',
        'wholesale' => 'Wholesale',
        'other' => 'Other',
    ];

    public const COMMON_CHANNELS = [
        'amazon' => ['name' => 'Amazon', 'fee' => 15.00],
        'ebay' => ['name' => 'eBay', 'fee' => 12.80],
        'etsy' => ['name' => 'Etsy', 'fee' => 6.50],
        'shopify' => ['name' => 'Shopify Store', 'fee' => 2.90],
        'website' => ['name' => 'Own Website', 'fee' => 0.00],
        'retail' => ['name' => 'Retail Store', 'fee' => 0.00],
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($channel) {
            if (empty($channel->slug)) {
                $channel->slug = Str::slug($channel->name);
            }
        });

        static::updating(function ($channel) {
            if ($channel->isDirty('name') && empty($channel->slug)) {
                $channel->slug = Str::slug($channel->name);
            }
        });
    }

    public function pricing(): HasMany
    {
        return $this->hasMany(Pricing::class, 'marketplace', 'slug');
    }

    /**
     * Calculate fee for a given amount
     */
    public function calculateFee(float $amount): float
    {
        $fee = 0;

        // Add percentage fee
        if ($this->default_fee_percentage > 0) {
            $fee += ($amount * $this->default_fee_percentage / 100);
        }

        // Add fixed fee
        if ($this->fixed_fee_amount > 0) {
            $fee += $this->fixed_fee_amount;
        }

        // Handle complex fee structures if needed
        if ($this->fee_structure) {
            // This could handle tiered fees, category-specific fees, etc.
            // For now, we'll keep it simple
        }

        return round($fee, 2);
    }

    /**
     * Get the type label
     */
    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Seed common sales channels
     */
    public static function seedCommonChannels(): void
    {
        foreach (self::COMMON_CHANNELS as $slug => $data) {
            self::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'default_fee_percentage' => $data['fee'],
                    'type' => $slug === 'website' || $slug === 'retail' ? $slug : 'marketplace',
                ]
            );
        }
    }
}
