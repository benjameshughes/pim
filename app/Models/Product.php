<?php

namespace App\Models;

use App\Builders\Products\ProductBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        // Note: Removed old numbered feature columns - now using ProductFeature relationship
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
     */
    public static function build(): ProductBuilder
    {
        return ProductBuilder::create();
    }

    /**
     * Create a ProductBuilder instance for updating this product
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

    public function features(): HasMany
    {
        return $this->hasMany(ProductFeature::class)->ordered();
    }

    public function productFeatures(): HasMany
    {
        return $this->features()->features();
    }

    public function productDetails(): HasMany
    {
        return $this->features()->details();
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
        // ✨ ENHANCED with our LEGENDARY sync status system! ✨
        $syncRecords = ShopifyProductSync::where('product_id', $this->id)->get();

        if ($syncRecords->isEmpty()) {
            return [
                'status' => 'not_synced',
                'colors_synced' => 0,
                'total_colors' => $this->variants->pluck('color')->filter()->unique()->count(),
                'last_synced_at' => null,
                'has_failures' => false,
                'health_score' => 0,
                'health_grade' => 'N/A',
                'needs_attention' => true,
                'sync_summary' => 'Product has never been synced to Shopify',
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

        // ✨ Calculate comprehensive health metrics using our LEGENDARY system! ✨
        $mainSync = ShopifyProductSync::getMainSyncRecord($this->id);
        $healthScore = $mainSync ? $mainSync->calculateSyncHealth() : 0;
        $healthGrade = $this->getHealthGrade($healthScore);
        $syncSummary = $this->generateSyncSummary($status, $successfulSyncs, $failedSyncs);
        $needsAttention = $status !== 'synced' || $healthScore < 80;
        
        // Get shopify URLs for admin links
        $shopifyUrls = $successfulSyncs->map(fn($sync) => $sync->getShopifyAdminUrl())->filter()->unique();

        return [
            'status' => $status,
            'colors_synced' => $successfulSyncs->count(),
            'total_colors' => $this->variants->pluck('color')->filter()->unique()->count(),
            'last_synced_at' => $successfulSyncs->max('last_synced_at'),
            'has_failures' => $failedSyncs->isNotEmpty(),
            'sync_records' => $syncRecords,
            // 💎 NEW LEGENDARY FEATURES 💎
            'health_score' => $healthScore,
            'health_grade' => $healthGrade,
            'needs_attention' => $needsAttention,
            'sync_summary' => $syncSummary,
            'shopify_urls' => $shopifyUrls->values()->toArray(),
            'drift_score' => $mainSync?->data_drift_score ?? 0,
            'variants_synced' => $mainSync?->variants_synced ?? 0,
            'sync_method' => $mainSync?->sync_method ?? 'unknown',
        ];
    }

    /**
     * 💅 SASSILLA'S HELPER METHODS FOR LEGENDARY SYNC STATUS 💅
     */
    
    /**
     * Convert health score to letter grade (because we're CLASSY!)
     */
    private function getHealthGrade(int $health): string
    {
        return match(true) {
            $health >= 95 => 'A+',
            $health >= 90 => 'A',
            $health >= 85 => 'A-',
            $health >= 80 => 'B+',
            $health >= 75 => 'B',
            $health >= 70 => 'B-',
            $health >= 65 => 'C+',
            $health >= 60 => 'C',
            $health >= 55 => 'C-',
            $health >= 50 => 'D',
            $health > 0 => 'F',
            default => 'N/A'
        };
    }
    
    /**
     * Generate user-friendly sync summary message
     */
    private function generateSyncSummary(string $status, $successfulSyncs, $failedSyncs): string
    {
        return match($status) {
            'not_synced' => 'Product has never been synced to Shopify',
            'pending' => 'Sync operation in progress',
            'failed' => 'Sync failed - ' . $failedSyncs->count() . ' color variant(s) need attention',
            'synced' => $successfulSyncs->count() . ' color variant(s) successfully synced to Shopify',
            default => 'Unknown sync status'
        };
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
     * Get product features as array (updated to use new normalized structure)
     */
    public function getFeaturesArray(): array
    {
        return $this->productFeatures->pluck('content')->toArray();
    }

    /**
     * Get product details as array (updated to use new normalized structure)
     */
    public function getDetailsArray(): array
    {
        return $this->productDetails->pluck('content')->toArray();
    }

    /**
     * Check if product has any features (updated to use new normalized structure)
     */
    public function hasFeatures(): bool
    {
        return $this->productFeatures()->exists();
    }

    /**
     * Check if product has any details (updated to use new normalized structure)
     */
    public function hasDetails(): bool
    {
        return $this->productDetails()->exists();
    }

    /**
     * Add a new feature to the product
     */
    public function addFeature(string $content, ?string $title = null, int $sortOrder = 0): ProductFeature
    {
        return $this->features()->create([
            'type' => 'feature',
            'content' => $content,
            'title' => $title,
            'sort_order' => $sortOrder ?: $this->productFeatures()->max('sort_order') + 1,
        ]);
    }

    /**
     * Add a new detail to the product
     */
    public function addDetail(string $content, ?string $title = null, int $sortOrder = 0): ProductFeature
    {
        return $this->features()->create([
            'type' => 'detail',
            'content' => $content,
            'title' => $title,
            'sort_order' => $sortOrder ?: $this->productDetails()->max('sort_order') + 1,
        ]);
    }

    /**
     * Get display status with proper formatting
     */
    public function getFormattedStatus(): string
    {
        return ucfirst($this->status);
    }

    /**
     * Check if product is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if product is draft
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if product is inactive
     */
    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    /**
     * Check if product is archived
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
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope a query to only include draft products
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope a query to only include inactive products
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    /**
     * Scope a query to only include archived products
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    /**
     * Scope a query to filter by status
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to search by name, SKU, or description
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
     */
    public function scopeWithVariants(Builder $query): Builder
    {
        return $query->has('variants');
    }

    /**
     * Scope a query to only include products without variants
     */
    public function scopeWithoutVariants(Builder $query): Builder
    {
        return $query->doesntHave('variants');
    }

    /**
     * Scope a query to only include auto-generated products
     */
    public function scopeAutoGenerated(Builder $query): Builder
    {
        return $query->where('auto_generated', true);
    }

    /**
     * Scope a query to only include manually created products
     */
    public function scopeManuallyCreated(Builder $query): Builder
    {
        return $query->where('auto_generated', false);
    }

    /**
     * Scope a query to include published products (active or inactive)
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_INACTIVE]);
    }

    /**
     * Scope a query to load common relationships
     */
    public function scopeWithCommonRelations(Builder $query): Builder
    {
        return $query->with(['variants', 'productImages', 'categories']);
    }
}
