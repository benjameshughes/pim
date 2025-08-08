<?php

namespace App\Models;

use App\Builders\Products\ProductBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Product Model
 * 
 * Enhanced for Builder Pattern compatibility with additional scopes and methods.
 * Supports fluent API for creation and updates.
 */
class Product extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     * 
     * @var array<string>
     */
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

    /**
     * The attributes that should be cast.
     * 
     * @var array<string, string>
     */
    protected $casts = [
        'images' => 'array',
        'auto_generated' => 'boolean',
    ];
    
    /**
     * Status constants for better type safety
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_ARCHIVED = 'archived';
    
    /**
     * Get all available status options
     * 
     * @return array
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_ARCHIVED => 'Archived',
        ];
    }
    
    /**
     * Create a new ProductBuilder instance for this model
     * 
     * @return ProductBuilder
     */
    public static function build(): ProductBuilder
    {
        return ProductBuilder::create();
    }
    
    /**
     * Create a ProductBuilder instance for updating this product
     * 
     * @return ProductBuilder
     */
    public function edit(): ProductBuilder
    {
        return ProductBuilder::update($this);
    }

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
    
    // =====================================
    // Builder Pattern Helper Methods
    // =====================================
    
    /**
     * Get product features as array
     * 
     * @return array
     */
    public function getFeaturesArray(): array
    {
        return array_filter([
            $this->product_features_1,
            $this->product_features_2,
            $this->product_features_3,
            $this->product_features_4,
            $this->product_features_5,
        ]);
    }
    
    /**
     * Get product details as array
     * 
     * @return array
     */
    public function getDetailsArray(): array
    {
        return array_filter([
            $this->product_details_1,
            $this->product_details_2,
            $this->product_details_3,
            $this->product_details_4,
            $this->product_details_5,
        ]);
    }
    
    /**
     * Check if product has any features
     * 
     * @return bool
     */
    public function hasFeatures(): bool
    {
        return !empty($this->getFeaturesArray());
    }
    
    /**
     * Check if product has any details
     * 
     * @return bool
     */
    public function hasDetails(): bool
    {
        return !empty($this->getDetailsArray());
    }
    
    /**
     * Get display status with proper formatting
     * 
     * @return string
     */
    public function getFormattedStatus(): string
    {
        return ucfirst($this->status);
    }
    
    /**
     * Check if product is active
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
    
    /**
     * Check if product is draft
     * 
     * @return bool
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
    
    /**
     * Check if product is inactive
     * 
     * @return bool
     */
    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }
    
    /**
     * Check if product is archived
     * 
     * @return bool
     */
    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }
    
    // =====================================
    // Query Scopes
    // =====================================
    
    /**
     * Scope a query to only include active products
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
    
    /**
     * Scope a query to only include draft products
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }
    
    /**
     * Scope a query to only include inactive products
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }
    
    /**
     * Scope a query to only include archived products
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }
    
    /**
     * Scope a query to filter by status
     * 
     * @param Builder $query
     * @param string $status
     * @return Builder
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
    
    /**
     * Scope a query to search by name, SKU, or description
     * 
     * @param Builder $query
     * @param string $search
     * @return Builder
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('parent_sku', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }
    
    /**
     * Scope a query to only include products with variants
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithVariants(Builder $query): Builder
    {
        return $query->has('variants');
    }
    
    /**
     * Scope a query to only include products without variants
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithoutVariants(Builder $query): Builder
    {
        return $query->doesntHave('variants');
    }
    
    /**
     * Scope a query to only include auto-generated products
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeAutoGenerated(Builder $query): Builder
    {
        return $query->where('auto_generated', true);
    }
    
    /**
     * Scope a query to only include manually created products
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeManuallyCreated(Builder $query): Builder
    {
        return $query->where('auto_generated', false);
    }
    
    /**
     * Scope a query to include published products (active or inactive)
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_INACTIVE]);
    }
    
    /**
     * Scope a query to load common relationships
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithCommonRelations(Builder $query): Builder
    {
        return $query->with(['variants', 'productImages', 'categories']);
    }

}
