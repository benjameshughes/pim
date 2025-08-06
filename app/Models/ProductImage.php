<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'image_path',
        'image_type',
        'sort_order',
        'alt_text',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->image_path);
    }

    public function getFullPathAttribute(): string
    {
        return Storage::disk('public')->path($this->image_path);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('image_type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId)->whereNull('variant_id');
    }

    public function scopeForVariant($query, int $variantId)
    {
        return $query->where('variant_id', $variantId)->whereNull('product_id');
    }

    public function scopeMain($query)
    {
        return $query->where('image_type', 'main');
    }

    public function isMain(): bool
    {
        return $this->image_type === 'main';
    }

    public function isSwatch(): bool
    {
        return $this->image_type === 'swatch';
    }

    public function delete(): ?bool
    {
        // Delete the actual file when model is deleted
        if (Storage::disk('public')->exists($this->image_path)) {
            Storage::disk('public')->delete($this->image_path);
        }

        return parent::delete();
    }
}
