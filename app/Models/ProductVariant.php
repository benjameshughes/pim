<?php

namespace App\Models;

use App\Traits\HasAttributesTrait;
use App\Traits\InheritsAttributesTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProductVariant extends Model
{
    use HasFactory, HasAttributesTrait, InheritsAttributesTrait;

    protected $fillable = [
        'product_id',
        'sku',
        'external_sku',
        'title',
        'color',
        'width',
        'drop',
        'max_drop',
        'price',
        'stock_level',
        'status',
        'parcel_length',
        'parcel_width',
        'parcel_depth',
        'parcel_weight',
    ];

    protected $casts = [
        'width' => 'integer',
        'drop' => 'integer',
        'max_drop' => 'integer',
        'price' => 'float',
        'stock_level' => 'integer',
        'parcel_length' => 'decimal:2',
        'parcel_width' => 'decimal:2',
        'parcel_depth' => 'decimal:2',
        'parcel_weight' => 'decimal:3',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 🏠 PRODUCT RELATIONSHIP
     *
     * Each variant belongs to a product family
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 🖼️ IMAGES - Polymorphic relationship to Image model
     *
     * Each variant can have specific images stored in R2
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')->ordered();
    }

    /**
     * ⭐ PRIMARY IMAGE - Get the primary image for this variant
     */
    public function primaryImage(): ?Image
    {
        return $this->images()->primary()->first();
    }

    /**
     * 🔢 BARCODES
     *
     * Each variant can have multiple barcodes (Caecus + System)
     */
    public function barcodes(): HasMany
    {
        return $this->hasMany(Barcode::class);
    }

    /**
     * 🛍️ SHOPIFY SYNC STATUS
     *
     * Track sync status for this specific variant
     */
    public function shopifySyncStatus(): HasMany
    {
        return $this->hasMany(ShopifySyncStatus::class);
    }

    /**
     * 💰 PRICING - Multi-channel pricing support
     */
    public function pricing(): HasMany
    {
        return $this->hasMany(Pricing::class);
    }

    /**
     * 🔗 MARKETPLACE LINKS
     *
     * Polymorphic relationship to marketplace links for this variant
     */
    public function marketplaceLinks(): MorphMany
    {
        return $this->morphMany(MarketplaceLink::class, 'linkable');
    }

    /**
     * 🔗 VARIANT-LEVEL MARKETPLACE LINKS
     *
     * Only the variant-level marketplace links
     */
    public function variantMarketplaceLinks(): MorphMany
    {
        return $this->marketplaceLinks()->where('link_level', 'variant');
    }

    /**
     * 💎 ACTIVE PRICING - Only active pricing records
     */
    public function activePricing(): HasMany
    {
        return $this->pricing()->active();
    }

    /**
     * 🏷️ ATTRIBUTES
     *
     * Flexible attribute system for variant metadata with inheritance
     */
    public function attributes(): HasMany
    {
        return $this->hasMany(VariantAttribute::class, 'variant_id');
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
     * 🧬 INHERITED ATTRIBUTES
     *
     * Attributes inherited from parent product
     */
    public function inheritedAttributes(): HasMany
    {
        return $this->attributes()->inherited();
    }

    /**
     * 🎯 OVERRIDE ATTRIBUTES
     *
     * Attributes that override inherited values
     */
    public function overrideAttributes(): HasMany
    {
        return $this->attributes()->overrides();
    }

    /**
     * 🎯 GET SMART ATTRIBUTE VALUE WITH INHERITANCE
     *
     * Get attribute value with smart inheritance fallback (use explicit method to avoid conflicts)
     */
    public function getSmartAttributeValue(string $key)
    {
        // First check direct model fields
        if (array_key_exists($key, $this->getAttributes())) {
            return $this->getAttributeValue($key);
        }

        // Try to get from variant attributes system
        $variantAttribute = $this->attributes()->forAttribute($key)->first();
        if ($variantAttribute) {
            return $variantAttribute->getTypedValue();
        }

        // Fallback to product attribute if inheritable
        if ($this->product) {
            $attributeDefinition = AttributeDefinition::findByKey($key);
            if ($attributeDefinition && $attributeDefinition->supportsInheritance()) {
                $productAttribute = $this->product->attributes()->forAttribute($key)->first();
                if ($productAttribute) {
                    return $productAttribute->getTypedValue();
                }
            }
        }

        return null;
    }

    /**
     * 🎯 SET ATTRIBUTE VALUE
     *
     * Set an attribute value in the flexible attributes system
     */
    public function setAttributeValue(string $key, $value, array $options = []): ?VariantAttribute
    {
        try {
            return VariantAttribute::createOrUpdate($this, $key, $value, $options);
        } catch (\InvalidArgumentException $e) {
            // Attribute definition doesn't exist, ignore silently or log
            return null;
        }
    }

    /**
     * 🎯 GET SMART BRAND VALUE WITH INHERITANCE
     *
     * Get brand with inheritance fallback from parent product
     */
    public function getSmartBrandValue()
    {
        // First check if variant has explicit brand override
        $variantBrand = $this->attributes()->forAttribute('brand')->first();
        if ($variantBrand && !$variantBrand->is_inherited) {
            return $variantBrand->getTypedValue();
        }

        // Fallback to product brand (either direct field or attributes)
        return $this->product?->brand;
    }


    /**
     * 🎨 BARCODE RELATIONSHIP
     *
     * Get the primary barcode relationship for this variant (caecus type)
     */
    public function barcode()
    {
        return $this->hasOne(Barcode::class)->where('type', 'caecus');
    }

    /**
     * 🎨 GET BARCODE VALUE
     *
     * Get the actual barcode value for this variant
     */
    public function getBarcodeValue()
    {
        return $this->barcodes()->where('type', 'caecus')->first();
    }

    /**
     * 📦 DISPLAY TITLE
     *
     * Generate a beautiful display title
     */
    public function getDisplayTitleAttribute()
    {
        return "{$this->product->name} {$this->color} {$this->width}cm";
    }

    /**
     * 💰 FORMATTED PRICE
     *
     * Get price with currency symbol
     */
    public function getFormattedPriceAttribute()
    {
        return '£'.number_format($this->price, 2);
    }

    /**
     * 📏 DIMENSIONS STRING
     *
     * Get width x drop display string
     */
    public function getDimensionsAttribute()
    {
        if ($this->drop) {
            return "{$this->width}cm x {$this->drop}cm";
        }

        return "{$this->width}cm (up to {$this->max_drop}cm drop)";
    }

    /**
     * ✅ IS ACTIVE
     *
     * Check if variant is active
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * 📦 IN STOCK
     *
     * Check if variant is in stock
     */
    public function inStock()
    {
        return $this->stock_level > 0;
    }

    /**
     * 🔍 SCOPE: Active variants only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * 🔍 SCOPE: In stock variants
     */
    public function scopeInStock($query)
    {
        return $query->where('stock_level', '>', 0);
    }

    /**
     * 🔍 SCOPE: By color
     */
    public function scopeByColor($query, $color)
    {
        return $query->where('color', $color);
    }

    /**
     * 🔍 SCOPE: By width
     */
    public function scopeByWidth($query, $width)
    {
        return $query->where('width', $width);
    }

    /**
     * 🏗️ BUILDER PATTERN FACTORY
     *
     * Create a new VariantBuilder for fluent variant creation
     */
    public static function buildFor(Product $product): \App\Builders\VariantBuilder
    {
        return new \App\Builders\VariantBuilder($product);
    }
}
