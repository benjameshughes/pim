<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'parent_sku',
        'description',
        'status',
        'auto_generated',
        'images',
        'product_features_1',
        'product_features_2',
        'product_features_3',
        'product_features_4',
        'product_features_5',
        'product_details_1',
        'product_details_2',
        'product_details_3',
        'product_details_4',
        'product_details_5',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function metadata(): HasMany
    {
        return $this->hasMany(ProductMetadata::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)->whereNull('variant_id');
    }

    public function allImages(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function primaryCategory()
    {
        return $this->categories()->wherePivot('is_primary', true)->first();
    }

    public function mainImage()
    {
        return $this->productImages()->where('image_type', 'main')->first();
    }

    public function swatchImage()
    {
        return $this->productImages()->where('image_type', 'swatch')->first();
    }

    public function getMetaAttribute()
    {
        return new ProductMetaAccessor($this);
    }

    /**
     * Get Shopify sync status for this product
     */
    public function getShopifySyncStatus(): array
    {
        $syncRecords = ShopifyProductSync::where('product_id', $this->id)->get();
        
        if ($syncRecords->isEmpty()) {
            return [
                'status' => 'not_synced',
                'colors_synced' => 0,
                'total_colors' => $this->variants->pluck('color')->filter()->unique()->count(),
                'last_synced_at' => null,
                'has_failures' => false
            ];
        }
        
        $successfulSyncs = $syncRecords->where('sync_status', 'synced');
        $failedSyncs = $syncRecords->where('sync_status', 'failed');
        $pendingSyncs = $syncRecords->where('sync_status', 'pending');
        
        // Determine overall status
        if ($pendingSyncs->isNotEmpty()) {
            $status = 'pending';
        } elseif ($failedSyncs->isNotEmpty()) {
            $status = 'failed';
        } elseif ($successfulSyncs->isNotEmpty()) {
            $status = 'synced';
        } else {
            $status = 'not_synced';
        }
        
        return [
            'status' => $status,
            'colors_synced' => $successfulSyncs->count(),
            'total_colors' => $this->variants->pluck('color')->filter()->unique()->count(),
            'last_synced_at' => $successfulSyncs->max('last_synced_at'),
            'has_failures' => $failedSyncs->isNotEmpty(),
            'sync_records' => $syncRecords
        ];
    }

    /**
     * Relation to Shopify sync records
     */
    public function shopifySyncs(): HasMany
    {
        return $this->hasMany(ShopifyProductSync::class);
    }

}
