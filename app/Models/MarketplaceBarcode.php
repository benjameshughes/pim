<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceBarcode extends Model
{
    protected $fillable = [
        'variant_id',
        'marketplace_id',
        'identifier_type',
        'identifier_value',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('identifier_type', $type);
    }

    public function scopeForMarketplace($query, string $marketplaceCode)
    {
        return $query->whereHas('marketplace', function ($q) use ($marketplaceCode) {
            $q->where('code', $marketplaceCode);
        });
    }

    public function getDisplayNameAttribute(): string
    {
        return strtoupper($this->identifier_type) . ': ' . $this->identifier_value;
    }
}
