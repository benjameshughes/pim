<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceVariant extends Model
{
    protected $fillable = [
        'variant_id',
        'marketplace_id',
        'marketplace_sku',
        'title',
        'description',
        'price_override',
        'status',
        'marketplace_data',
        'last_synced_at',
    ];

    protected $casts = [
        'marketplace_data' => 'array',
        'last_synced_at' => 'datetime',
        'price_override' => 'decimal:2',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace::class);
    }

    public function getEffectivePriceAttribute(): ?float
    {
        return $this->price_override ?? $this->variant->retail_price ?? null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeMarketplace($query, string $marketplaceCode)
    {
        return $query->whereHas('marketplace', function ($q) use ($marketplaceCode) {
            $q->where('code', $marketplaceCode);
        });
    }

    public function markSynced(): void
    {
        $this->update(['last_synced_at' => now()]);
    }
}
