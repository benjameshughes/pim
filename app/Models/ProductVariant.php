<?php

namespace App\Models;

use App\Traits\HasAttributesTrait;
use App\Traits\InheritsAttributesTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProductVariant extends Model
{
    use HasAttributesTrait, HasFactory, InheritsAttributesTrait;

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
     * 🖼️ IMAGES - Many-to-many relationship with Image model via pivot
     *
     * Each variant can have specific images stored in R2
     */
    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Image::class, 'image_variant')->orderBy('sort_order')->orderBy('created_at');
    }

    /**
     * ⭐ PRIMARY IMAGE - Get the primary image for this variant
     */
    public function primaryImage(): ?Image
    {
        return $this->images()->primary()->first();
    }

    /**
     * 🏷️ BARCODE
     *
     * Single barcode assigned to this variant
     */
    public function barcode(): HasOne
    {
        return $this->hasOne(Barcode::class, 'product_variant_id');
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
     * 💰 PRICING - DECOUPLED ACCESS
     * Pricing system operates independently but can be accessed via variant
     */
    public function getPricingData()
    {
        return \App\Models\Pricing::where('product_variant_id', $this->id)->get();
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
     * 💎 ACTIVE PRICING - DECOUPLED ACCESS
     * Get active pricing records without tight coupling
     */
    public function getActivePricingData()
    {
        return \App\Models\Pricing::where('product_variant_id', $this->id)->active()->get();
    }

    /**
     * 💰 PRICE ACCESSOR - $variant->price
     * Get the default/primary price for this variant via independent pricing service
     */

    /**
     * 💰 PRICE FOR CHANNEL - Get price for specific sales channel via service
     */
    public function getPriceForChannel($channelId = null)
    {
        if (! $channelId) {
            return $this->price; // Use default price accessor
        }

        // Use the new ChannelPricingService instead of old PricingService
        return $this->getChannelPrice();
    }

    /**
     * 💰 GET RETAIL PRICE - Get price from default (Retail) sales channel
     */
    public function getRetailPrice(): float
    {
        $defaultChannel = \App\Models\SalesChannel::getDefault();
        if (!$defaultChannel) {
            return 0.0;
        }

        $retailPricing = $this->pricingRecords()
            ->where('sales_channel_id', $defaultChannel->id)
            ->first();

        return $retailPricing ? $retailPricing->price : 0.0;
    }

    /**
     * 🔗 PRICING RELATIONSHIP - For Eloquent queries only
     * Note: This is for query purposes, pricing operates independently
     */
    public function pricingRecords(): HasMany
    {
        return $this->hasMany(\App\Models\Pricing::class, 'product_variant_id');
    }

    /**
     * 🎯 NEW CHANNEL PRICING SYSTEM - Using Attribute-Based Pricing
     * These methods replace the complex Pricing table approach with simple attributes
     */

    /**
     * 💰 GET CHANNEL PRICE - New attribute-based channel pricing
     * Get price for specific channel with fallback to default price
     */
    public function getChannelPrice(?string $channelCode = null): float
    {
        return app(\App\Services\Pricing\ChannelPricingService::class)
            ->getPriceForChannel($this, $channelCode);
    }

    /**
     * 💰 SET CHANNEL PRICE - New attribute-based channel pricing  
     * Set price for specific channel (null removes override)
     */
    public function setChannelPrice(string $channelCode, ?float $price): array
    {
        return app(\App\Services\Pricing\ChannelPricingService::class)
            ->setPriceForChannel($this, $channelCode, $price);
    }

    /**
     * 💰 HAS CHANNEL OVERRIDE - Check if variant has channel-specific pricing
     */
    public function hasChannelOverride(string $channelCode): bool
    {
        return app(\App\Services\Pricing\ChannelPricingService::class)
            ->hasChannelOverride($this, $channelCode);
    }

    /**
     * 💰 GET ALL CHANNEL PRICES - Get pricing for all channels
     */
    public function getAllChannelPrices(): array
    {
        return app(\App\Services\Pricing\ChannelPricingService::class)
            ->getAllChannelPrices($this);
    }

    /**
     * 💰 REMOVE CHANNEL OVERRIDE - Remove channel price override
     */
    public function removeChannelOverride(string $channelCode): array
    {
        return $this->setChannelPrice($channelCode, null);
    }

    /**
     * 💰 CHANNEL PRICING SUMMARY - Get summary of all channel pricing
     */
    public function getChannelPricingSummary(): array
    {
        $allPrices = $this->getAllChannelPrices();
        
        $summary = [
            'variant_id' => $this->id,
            'variant_sku' => $this->sku,
            'default_price' => $this->price,
            'channels_with_overrides' => [],
            'channels_using_default' => [],
            'total_channels' => count($allPrices),
            'overrides_count' => 0,
        ];

        foreach ($allPrices as $channelCode => $channelData) {
            if ($channelData['has_override']) {
                $summary['channels_with_overrides'][$channelCode] = [
                    'name' => $channelData['name'],
                    'price' => $channelData['price'],
                    'markup_percentage' => $this->price > 0 
                        ? round((($channelData['price'] - $this->price) / $this->price) * 100, 2)
                        : 0,
                ];
                $summary['overrides_count']++;
            } else {
                $summary['channels_using_default'][] = [
                    'code' => $channelCode,
                    'name' => $channelData['name'],
                ];
            }
        }

        return $summary;
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
        if ($variantBrand && ! $variantBrand->is_inherited) {
            return $variantBrand->getTypedValue();
        }

        // Fallback to product brand (either direct field or attributes)
        return $this->product?->brand;
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
     * 📦 STOCK ACCESSOR - $variant->stock
     * Get stock record for this variant via independent stock service
     */
    public function getStockAttribute()
    {
        return app(\App\Services\StockService::class)->getStockForVariant($this->id);
    }

    /**
     * 📦 STOCK LEVEL ACCESSOR - $variant->stock_level
     * Get current stock quantity for this variant via service
     */
    public function getStockLevelAttribute()
    {
        return app(\App\Services\StockService::class)->getStockLevelForVariant($this->id);
    }

    /**
     * 📦 IN STOCK
     * Check if variant is in stock via service
     */
    public function inStock()
    {
        return app(\App\Services\StockService::class)->isVariantInStock($this->id);
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
     * Note: This scope queries the actual stock table for accuracy
     */
    public function scopeInStock($query)
    {
        return $query->whereHas('stockRecords', function ($q) {
            $q->where('quantity', '>', 0)->where('status', 'available');
        });
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
     * 📦 STOCK RELATIONSHIP - For Eloquent queries only
     * Note: This is for query purposes, stock operates independently
     */
    public function stockRecords(): HasMany
    {
        return $this->hasMany(\App\Models\Stock::class, 'product_variant_id');
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
