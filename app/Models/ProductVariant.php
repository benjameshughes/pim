<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductVariant extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;
    protected $fillable = [
        'product_id',
        'sku',
        'status',
        'stock_level',
        'images',
        'package_length',
        'package_width',
        'package_height',
        'package_weight',
    ];

    protected $casts = [
        'images' => 'array',
        'package_length' => 'decimal:2',
        'package_width' => 'decimal:2',
        'package_height' => 'decimal:2',
        'package_weight' => 'decimal:3',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(Barcode::class);
    }

    public function pricing(): HasMany
    {
        return $this->hasMany(Pricing::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(VariantAttribute::class, 'variant_id');
    }

    public function marketplaceVariants(): HasMany
    {
        return $this->hasMany(MarketplaceVariant::class, 'variant_id');
    }

    public function marketplaceBarcodes(): HasMany
    {
        return $this->hasMany(MarketplaceBarcode::class, 'variant_id');
    }

    public function variantImages(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id')->whereNull('product_id');
    }

    public function primaryBarcode()
    {
        return $this->barcodes()->where('is_primary', true)->first();
    }

    public function mainImage()
    {
        return $this->variantImages()->where('image_type', 'main')->first();
    }

    public function swatchImage()
    {
        return $this->variantImages()->where('image_type', 'swatch')->first();
    }

    public function getMarketplaceData(string $marketplaceCode): ?MarketplaceVariant
    {
        return $this->marketplaceVariants()
            ->whereHas('marketplace', function ($query) use ($marketplaceCode) {
                $query->where('code', $marketplaceCode);
            })
            ->first();
    }

    /**
     * Register media conversions for automatic image processing
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->nonQueued(); // Generate immediately

        $this->addMediaConversion('medium')
            ->width(400)
            ->height(400)
            ->optimize()
            ->nonQueued();

        $this->addMediaConversion('large')
            ->width(800)
            ->height(800)
            ->optimize()
            ->queued(); // Generate in background

        $this->addMediaConversion('webp')
            ->format('webp')
            ->width(600)
            ->height(600)
            ->optimize()
            ->queued();
    }

    /**
     * Register media collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }
    
    /**
     * Accessor methods for core attributes stored in the attribute system
     * These provide seamless access to attributes as if they were direct properties
     */
    
    public function getColorAttribute(): ?string
    {
        $attribute = $this->attributes()->byKey('color')->first();
        return $attribute ? $attribute->attribute_value : null;
    }
    
    public function getWidthAttribute(): ?string
    {
        $attribute = $this->attributes()->byKey('width')->first();
        return $attribute ? $attribute->attribute_value : null;
    }
    
    public function getDropAttribute(): ?string  
    {
        $attribute = $this->attributes()->byKey('drop')->first();
        return $attribute ? $attribute->attribute_value : null;
    }
    
    /**
     * Helper method to get any variant attribute value by key
     */
    public function getVariantAttributeValue(string $key): mixed
    {
        $attribute = $this->attributes()->byKey($key)->first();
        return $attribute ? $attribute->typed_value : null;
    }
    
    /**
     * Helper method to set any variant attribute value  
     */
    public function setVariantAttributeValue(string $key, $value, string $dataType = 'text', ?string $category = null): void
    {
        \App\Models\VariantAttribute::setValue($this->id, $key, $value, $dataType, $category);
        
        // Clear the relationship cache so fresh data is loaded
        unset($this->relations['attributes']);
    }
    
    /**
     * Get formatted display string for dimensions
     */
    public function getDimensionsAttribute(): ?string
    {
        $width = $this->width;
        $drop = $this->drop;
        
        if ($width && $drop) {
            return "{$width} × {$drop}";
        } elseif ($width) {
            return "{$width} wide";
        } elseif ($drop) {
            return "{$drop} drop";
        }
        
        return null;
    }
    
    /**
     * Backward compatibility - get size as formatted dimensions
     * This allows existing views using $variant->size to work
     */
    public function getSizeAttribute(): ?string
    {
        return $this->getDimensionsAttribute();
    }

    /**
     * Get the first variant image URL for display
     */
    public function getImageUrlAttribute(): ?string
    {
        if ($this->images && count($this->images) > 0) {
            return asset('storage/' . $this->images[0]);
        }
        
        // Try variant images relationship
        if ($this->variantImages->isNotEmpty()) {
            $mainImage = $this->variantImages->where('image_type', 'main')->first();
            if ($mainImage) {
                return \Storage::url($mainImage->image_path);
            }
        }
        
        return null;
    }

    /**
     * Get the retail price from the first pricing record
     */
    public function getRetailPriceAttribute(): ?float
    {
        return $this->pricing->first()?->retail_price;
    }

    /**
     * Model event listeners for archiving
     */
    protected static function booted(): void
    {
        static::deleting(function (ProductVariant $variant) {
            // Only archive if deletion_reason is set on the model
            // This allows for programmatic deletion with archiving
            if (isset($variant->deletion_reason)) {
                \App\Models\DeletedProductVariant::createFromVariant(
                    $variant,
                    $variant->deletion_reason,
                    $variant->deletion_notes ?? null
                );
            }

            // Free up any assigned barcodes for reuse
            \App\Models\BarcodePool::where('assigned_to_variant_id', $variant->id)
                ->update([
                    'status' => 'available',
                    'assigned_to_variant_id' => null,
                    'assigned_at' => null
                ]);
        });
    }
    
    /**
     * Create a new variant builder instance
     * 
     * @return \App\Builders\Variants\VariantBuilder
     */
    public static function build(): \App\Builders\Variants\VariantBuilder
    {
        return \App\Builders\Variants\VariantBuilder::create();
    }
    
    /**
     * Create variant builder for specific product
     * 
     * @param Product $product
     * @return \App\Builders\Variants\VariantBuilder  
     */
    public static function buildFor(Product $product): \App\Builders\Variants\VariantBuilder
    {
        return \App\Builders\Variants\VariantBuilder::for($product);
    }
    
    /**
     * Get variant builder for editing this variant
     * 
     * @return \App\Builders\Variants\VariantBuilder
     */
    public function edit(): \App\Builders\Variants\VariantBuilder
    {
        $builder = \App\Builders\Variants\VariantBuilder::create();
        
        // Pre-populate with current variant data
        $builder->productId($this->product_id)
                ->sku($this->sku)
                ->status($this->status ?? 'active')
                ->stockLevel($this->stock_level ?? 0);
                
        // Add dimensions if available
        if ($this->package_length) {
            $builder->dimensions(
                $this->package_length,
                $this->package_width,
                $this->package_height,
                $this->package_weight
            );
        }
        
        // Add current attributes
        $attributes = [];
        foreach ($this->attributes as $attribute) {
            $attributes[$attribute->attribute_key] = $attribute->attribute_value;
        }
        if (!empty($attributes)) {
            $builder->attributes($attributes);
        }
        
        return $builder;
    }
    
    /**
     * Query scope for active variants
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    /**
     * Query scope with common relationships
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithCommon($query)
    {
        return $query->with([
            'product:id,name,parent_sku',
            'barcodes:id,product_variant_id,barcode,is_primary',
            'pricing:id,product_variant_id,retail_price,marketplace',
            'attributes:id,variant_id,attribute_key,attribute_value'
        ]);
    }
    
    /**
     * Check if variant has primary barcode
     * 
     * @return bool
     */
    public function hasPrimaryBarcode(): bool
    {
        return $this->barcodes()->where('is_primary', true)->exists();
    }
    
    /**
     * Check if variant has pricing
     * 
     * @return bool
     */
    public function hasPricing(): bool
    {
        return $this->pricing()->exists();
    }
    
    /**
     * Get display name with product name and attributes
     * 
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        $parts = array_filter([
            $this->product->name ?? 'Product',
            $this->color,
            $this->dimensions ?? $this->size
        ]);
        
        return implode(' - ', $parts);
    }
}
