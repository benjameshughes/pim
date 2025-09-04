<?php

namespace App\Models;

use App\Support\Collections\ShopifyProductCollection;
use App\Traits\HasAttributesTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Product extends Model
{
    use HasAttributesTrait, HasFactory;

    protected $fillable = [
        'name',
        'parent_sku',
        'description',
        'status',
        'image_url',
        'category_id',
        'meta_description',
        // Import fields (linnworks_sku removed by migration)
        'barcode',
        'length',
        'width',
        'depth',
        'weight',
        'retail_price',
    ];

    protected $casts = [
        'status' => \App\Enums\ProductStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 💎 VARIANTS - The heart of the business
     *
     * Each product has many variants (color + width combinations)
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * 🖼️ IMAGES - Many-to-many relationship with Image model via pivot
     *
     * Each product can have multiple images stored in R2
     */
    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Image::class, 'image_product')->orderBy('image_product.sort_order')->orderBy('images.created_at');
    }

    /**
     * ⭐ PRIMARY IMAGE - Get the primary image for this product
     */
    public function primaryImage(): ?Image
    {
        return $this->images()->wherePivot('is_primary', true)->first();
    }

    /**
     * 🎨 COLOR GROUP IMAGES - Many-to-many relationship with Image model for color grouping
     *
     * Images that represent specific colors across all variants of that color
     */
    public function colorGroupImages(): BelongsToMany
    {
        return $this->belongsToMany(Image::class, 'image_color_group')
            ->withPivot('color', 'is_primary', 'sort_order')
            ->withTimestamps()
            ->orderBy('image_color_group.sort_order')
            ->orderBy('images.created_at');
    }

    /**
     * 📊 SYNC STATUSES
     *
     * Unified sync statuses across all marketplaces
     */
    public function syncStatuses(): HasMany
    {
        return $this->hasMany(SyncStatus::class);
    }

    /**
     * 📝 SYNC LOGS
     *
     * Comprehensive audit trail for all sync operations
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    /**
     * 🏷️ TAGS
     *
     * The tags that belong to the product.
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * ✅ ACTIVE VARIANTS
     *
     * Only get active variants
     */
    public function activeVariants(): HasMany
    {
        return $this->variants()->where('status', 'active');
    }

    /**
     * 🔗 SKU LINKS (Legacy)
     *
     * @deprecated Use marketplaceLinks() instead. Maintained for backward compatibility.
     */
    public function skuLinks(): HasMany
    {
        return $this->hasMany(SkuLink::class);
    }

    /**
     * 🔗 MARKETPLACE LINKS
     *
     * Polymorphic relationship to marketplace links for this product
     */
    public function marketplaceLinks(): MorphMany
    {
        return $this->morphMany(MarketplaceLink::class, 'linkable');
    }

    /**
     * 🔗 PRODUCT-LEVEL MARKETPLACE LINKS
     *
     * Only the product-level marketplace links (excludes variant links)
     */
    public function productMarketplaceLinks(): MorphMany
    {
        return $this->marketplaceLinks()->where('link_level', 'product');
    }

    /**
     * 🏷️ ATTRIBUTES
     *
     * Flexible attribute system for product metadata
     */
    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    /**
     * ✅ VALID ATTRIBUTES
     *
     * Only attributes that pass validation
     */
    public function validAttributes(): HasMany
    {
        return $this->attributes()->valid();
    }

    /**
     * 🧬 INHERITABLE ATTRIBUTES
     *
     * Attributes that can be inherited by variants
     */
    public function inheritableAttributes(): HasMany
    {
        return $this->attributes()->whereHas('attributeDefinition', function ($query) {
            $query->where('is_inheritable', true);
        });
    }

    /**
     * 🎯 GET SMART ATTRIBUTE VALUE
     *
     * Get a specific attribute value from flexible system (use explicit method to avoid conflicts)
     */
    public function getSmartAttributeValue(string $key)
    {
        // First check direct model fields
        if (array_key_exists($key, $this->getAttributes())) {
            return $this->getAttributeValue($key);
        }

        // Try to get from attributes system
        $attribute = $this->attributes()->forAttribute($key)->first();

        return $attribute?->getTypedValue();
    }

    /**
     * 🎯 SET ATTRIBUTE VALUE
     *
     * Set an attribute value in the flexible attributes system
     */
    public function setAttributeValue(string $key, $value, array $options = []): ?ProductAttribute
    {
        try {
            return ProductAttribute::createOrUpdate($this, $key, $value, $options);
        } catch (\InvalidArgumentException $e) {
            // Attribute definition doesn't exist, ignore silently or log
            return null;
        }
    }

    /**
     * 🎯 GET SMART BRAND VALUE
     *
     * Get brand with fallback from direct field to attributes system
     */
    public function getSmartBrandValue()
    {
        // First check direct brand field
        if (! empty($this->getOriginal('brand'))) {
            return $this->getOriginal('brand');
        }

        // Fallback to attributes system
        $brandAttribute = $this->attributes()->forAttribute('brand')->first();

        return $brandAttribute?->getTypedValue();
    }

    /**
     * 🎨 UNIQUE COLORS
     *
     * Get all unique colors for this product
     */
    public function getColorsAttribute()
    {
        return $this->variants->pluck('color')->unique()->values();
    }

    /**
     * 📏 AVAILABLE WIDTHS
     *
     * Get all available widths for this product
     */
    public function getWidthsAttribute()
    {
        return $this->variants->pluck('width')->unique()->sort()->values();
    }

    /**
     * 🔍 SCOPE: Active products only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * 💅✨ CUSTOM SHOPIFY COLLECTION - BECAUSE WE'RE EXTRA LIKE THAT! ✨💅
     *
     * Return our fabulous ShopifyProductCollection instead of boring default Collection
     */
    public function newCollection(array $models = []): Collection
    {
        return new ShopifyProductCollection($models);
    }

    /**
     * 🛍️ SHOPIFY SCOPE - Get products with Shopify superpowers!
     * Usage: Product::shopify()->syncable()->exportToShopify()
     */
    public function scopeShopify($query)
    {
        return $query->with(['variants.barcodes', 'variants.pricing']);
    }

    /**
     * 🔥✨ COLLECTION-BASED DTO INTEGRATION ✨🔥
     */

    /**
     * 🚀 Create product from Collection-powered ProductDTO
     */
    public static function createFromDTO(\App\DTOs\Products\ProductDTO $dto): self
    {
        $modelData = $dto->toModelData();

        return static::create($modelData->toArray());
    }

    /**
     * ✏️ Update product from Collection-powered ProductDTO
     */
    public function updateFromDTO(\App\DTOs\Products\ProductDTO $dto): bool
    {
        $modelData = $dto->toModelData();

        // Only update fields that are actually different for efficiency
        $currentDto = \App\DTOs\Products\ProductDTO::fromModel($this);
        $changedFields = $dto->getChangedFields($currentDto);

        if ($changedFields->isEmpty()) {
            return true; // No changes needed
        }

        // Update only changed fields using Collection operations
        $updateData = $modelData->only($changedFields->toArray());

        return $this->update($updateData->toArray());
    }

    /**
     * 🎯 Convert model to Collection-powered ProductDTO
     */
    public function toProductDTO(): \App\DTOs\Products\ProductDTO
    {
        return \App\DTOs\Products\ProductDTO::fromModel($this);
    }

    /**
     * 📊 Get Collection-based statistics for products
     */
    public static function getCollectionStatistics(): \Illuminate\Support\Collection
    {
        $products = static::all();

        return collect([
            'total_products' => $products->count(),
            'active_products' => $products->where('status', 'active')->count(),
            'inactive_products' => $products->where('status', 'inactive')->count(),
            'status_distribution' => \App\Enums\ProductStatus::getStatistics($products),
            'completion_stats' => $products->map(fn ($product) => $product->toProductDTO()->getStatistics()->get('completeness_percentage')
            )->pipe(fn ($percentages) => [
                'average_completion' => $percentages->average(),
                'min_completion' => $percentages->min(),
                'max_completion' => $percentages->max(),
                'fully_complete' => $percentages->filter(fn ($p) => $p >= 100)->count(),
            ]),
            'field_usage' => collect([
                'with_description' => $products->whereNotNull('description')->count(),
                'with_image' => $products->whereNotNull('image_url')->count(),
                'with_variants' => $products->has('variants')->count(),
            ]),
        ]);
    }

    /**
     * 🎨 COLOR GROUP IMAGE HELPER METHODS
     */

    /**
     * 🎨 GET IMAGES FOR COLOR
     *
     * Get all images for a specific color group
     */
    public function getImagesForColor(string $color): BelongsToMany
    {
        return $this->colorGroupImages()->wherePivot('color', $color);
    }

    /**
     * ⭐ GET PRIMARY IMAGE FOR COLOR
     *
     * Get the primary image for a specific color group
     */
    public function getPrimaryImageForColor(string $color): ?Image
    {
        return $this->getImagesForColor($color)->wherePivot('is_primary', true)->first();
    }

    /**
     * 🎨 GET ALL COLOR IMAGES
     *
     * Get images grouped by color
     */
    public function getColorImages(): \Illuminate\Support\Collection
    {
        return $this->colorGroupImages()
            ->get()
            ->groupBy('pivot.color')
            ->map(function ($images, $color) {
                return [
                    'color' => $color,
                    'images' => $images,
                    'primary_image' => $images->where('pivot.is_primary', true)->first(),
                    'count' => $images->count(),
                ];
            });
    }

    /**
     * 🎯 GET AVAILABLE COLORS WITH IMAGES
     *
     * Get colors that have images assigned
     */
    public function getColorsWithImages(): \Illuminate\Support\Collection
    {
        return $this->colorGroupImages()
            ->get()
            ->pluck('pivot.color')
            ->unique()
            ->values();
    }
}
