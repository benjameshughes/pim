<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marketplace extends Model
{
    protected $fillable = [
        'name',
        'platform',
        'code',
        'status',
        'default_settings',
    ];

    protected $casts = [
        'default_settings' => 'array',
    ];

    public function marketplaceVariants(): HasMany
    {
        return $this->hasMany(MarketplaceVariant::class);
    }

    public function marketplaceBarcodes(): HasMany
    {
        return $this->hasMany(MarketplaceBarcode::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }
}
