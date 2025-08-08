<?php

namespace App\Builders\Variants;

use App\Actions\Variants\CreateVariantAction;
use App\Actions\Variants\CreateVariantWithBarcodeAction;
use App\Builders\Base\BaseBuilder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\BarcodePool;
use App\Models\Pricing;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use App\Exceptions\BarcodePoolExhaustedException;
use App\Exceptions\DuplicateSkuException;

/**
 * Variant Builder
 * 
 * Fluent API builder for creating and updating product variants.
 * Provides a clean, readable interface for variant construction.
 * 
 * @package App\Builders\Variants
 */
class VariantBuilder extends BaseBuilder
{
    /**
     * Required fields for variant creation
     * 
     * @var array
     */
    protected array $required = ['product_id', 'sku'];
    
    /**
     * Create a new variant builder instance
     * 
     * @return static
     */
    public static function create(): static
    {
        return new static();
    }
    
    /**
     * Create a builder for a specific product
     * 
     * @param Product $product Product to create variant for
     * @return static
     */
    public static function for(Product $product): static
    {
        return (new static())->product($product);
    }
    
    /**
     * Set the parent product
     * 
     * @param Product $product Parent product
     * @return static
     */
    public function product(Product $product): static
    {
        return $this->set('product_id', $product->id);
    }
    
    /**
     * Set the parent product by ID
     * 
     * @param int $productId Product ID
     * @return static
     */
    public function productId(int $productId): static
    {
        return $this->set('product_id', $productId);
    }
    
    /**
     * Set variant SKU
     * 
     * @param string $sku Variant SKU
     * @return static
     * @throws InvalidArgumentException If SKU is empty
     */
    public function sku(string $sku): static
    {
        if (empty($sku)) {
            throw new InvalidArgumentException('Variant SKU cannot be empty');
        }
        
        return $this->set('sku', $sku);
    }
    
    /**
     * Set variant status
     * 
     * @param string $status Variant status (draft, active, inactive, archived)
     * @return static
     * @throws InvalidArgumentException If status is invalid
     */
    public function status(string $status): static
    {
        $validStatuses = ['draft', 'active', 'inactive', 'archived'];
        
        if (!in_array($status, $validStatuses)) {
            throw new InvalidArgumentException(
                'Invalid status. Must be one of: ' . implode(', ', $validStatuses)
            );
        }
        
        return $this->set('status', $status);
    }
    
    /**
     * Mark variant as draft
     * 
     * @return static
     */
    public function draft(): static
    {
        return $this->status('draft');
    }
    
    /**
     * Mark variant as active
     * 
     * @return static
     */
    public function active(): static
    {
        return $this->status('active');
    }
    
    /**
     * Mark variant as inactive
     * 
     * @return static
     */
    public function inactive(): static
    {
        return $this->status('inactive');
    }
    
    /**
     * Set stock level
     * 
     * @param int $stockLevel Stock level
     * @return static
     */
    public function stockLevel(int $stockLevel): static
    {
        return $this->set('stock_level', $stockLevel);
    }
    
    /**
     * Set package dimensions
     * 
     * @param float $length Length in cm
     * @param float $width Width in cm
     * @param float $height Height in cm
     * @param float|null $weight Weight in kg
     * @return static
     */
    public function dimensions(float $length, float $width, float $height, ?float $weight = null): static
    {
        $this->set('package_length', $length);
        $this->set('package_width', $width);
        $this->set('package_height', $height);
        
        if ($weight !== null) {
            $this->set('package_weight', $weight);
        }
        
        return $this;
    }
    
    /**
     * Set package weight
     * 
     * @param float $weight Weight in kg
     * @return static
     */
    public function weight(float $weight): static
    {
        return $this->set('package_weight', $weight);
    }
    
    /**
     * Set variant images
     * 
     * @param array $images Array of image paths/URLs
     * @return static
     */
    public function images(array $images): static
    {
        return $this->set('images', $images);
    }
    
    /**
     * Set variant attributes
     * 
     * @param array $attributes Associative array of attribute key-value pairs
     * @return static
     */
    public function attributes(array $attributes): static
    {
        return $this->set('attributes', $attributes);
    }
    
    /**
     * Set color attribute (convenience method)
     * 
     * @param string $color Color value
     * @return static
     */
    public function color(string $color): static
    {
        $attributes = $this->get('attributes', []);
        $attributes['color'] = $color;
        return $this->set('attributes', $attributes);
    }
    
    /**
     * Set width attribute (convenience method)
     * 
     * @param string $width Width value
     * @return static
     */
    public function width(string $width): static
    {
        $attributes = $this->get('attributes', []);
        $attributes['width'] = $width;
        return $this->set('attributes', $attributes);
    }
    
    /**
     * Set drop attribute (convenience method)
     * 
     * @param string $drop Drop value
     * @return static
     */
    public function drop(string $drop): static
    {
        $attributes = $this->get('attributes', []);
        $attributes['drop'] = $drop;
        return $this->set('attributes', $attributes);
    }
    
    /**
     * Set pricing information
     * 
     * @param float $retailPrice Retail price
     * @param float|null $costPrice Cost price
     * @param float|null $salePrice Sale price
     * @param string $currency Currency code
     * @return static
     */
    public function pricing(float $retailPrice, ?float $costPrice = null, ?float $salePrice = null, string $currency = 'GBP'): static
    {
        return $this->set('pricing', [
            'retail_price' => $retailPrice,
            'cost_price' => $costPrice,
            'sale_price' => $salePrice,
            'currency' => $currency,
        ]);
    }
    
    /**
     * Set retail price (convenience method)
     * 
     * @param float $price Retail price
     * @return static
     */
    public function retailPrice(float $price): static
    {
        $pricing = $this->get('pricing', []);
        $pricing['retail_price'] = $price;
        return $this->set('pricing', $pricing);
    }
    
    /**
     * Add barcode
     * 
     * @param string $barcode Barcode value
     * @param string $type Barcode type (EAN13, UPC, etc.)
     * @param bool $isPrimary Whether this is the primary barcode
     * @return static
     */
    public function barcode(string $barcode, string $type = 'EAN13', bool $isPrimary = false): static
    {
        $barcodes = $this->get('barcodes', []);
        $barcodes[] = [
            'barcode' => $barcode,
            'type' => $type,
            'is_primary' => $isPrimary,
        ];
        return $this->set('barcodes', $barcodes);
    }
    
    /**
     * Add primary barcode (convenience method)
     * 
     * @param string $barcode Barcode value
     * @param string $type Barcode type
     * @return static
     */
    public function primaryBarcode(string $barcode, string $type = 'EAN13'): static
    {
        return $this->barcode($barcode, $type, true);
    }
    
    /**
     * Auto-assign barcode from pool
     * 
     * @param string $type Barcode type (EAN13, UPC, etc.)
     * @param bool $isPrimary Whether this is the primary barcode
     * @return static
     * @throws InvalidArgumentException If no barcodes available in pool
     */
    public function assignFromPool(string $type = 'EAN13', bool $isPrimary = true): static
    {
        // Optimized query - select only needed columns and add index hints
        $barcodePool = BarcodePool::select(['id', 'barcode', 'barcode_type'])
            ->where('status', 'available')
            ->where('barcode_type', $type)
            ->where('is_legacy', false)
            ->orderBy('id')  // Use index for consistent ordering
            ->first();
            
        if (!$barcodePool) {
            throw new BarcodePoolExhaustedException($type);
        }
        
        return $this->barcode($barcodePool->barcode, $type, $isPrimary)
                    ->set('barcode_pool_id', $barcodePool->id);
    }
    
    /**
     * Set marketplace pricing for multiple channels
     * 
     * @param array $marketplacePricing ['ebay' => ['retail' => 29.99, 'cost' => 15.00], ...]
     * @return static
     */
    public function marketplacePricing(array $marketplacePricing): static
    {
        return $this->set('marketplace_pricing', $marketplacePricing);
    }
    
    /**
     * Add pricing for specific marketplace
     * 
     * @param string $marketplace Marketplace code (ebay, shopify, etc.)
     * @param float $retailPrice Retail price
     * @param float|null $costPrice Cost price
     * @param string $currency Currency code
     * @return static
     */
    public function addMarketplacePricing(string $marketplace, float $retailPrice, ?float $costPrice = null, string $currency = 'GBP'): static
    {
        $marketplacePricing = $this->get('marketplace_pricing', []);
        $marketplacePricing[$marketplace] = [
            'retail_price' => $retailPrice,
            'cost_price' => $costPrice,
            'currency' => $currency,
            'vat_rate' => 20, // Default UK VAT
        ];
        return $this->set('marketplace_pricing', $marketplacePricing);
    }
    
    /**
     * Set main image for variant
     * 
     * @param UploadedFile|string $image Image file or path
     * @return static
     */
    public function mainImage(UploadedFile|string $image): static
    {
        $images = $this->get('variant_images', []);
        $images['main'] = $image;
        return $this->set('variant_images', $images);
    }
    
    /**
     * Set swatch image for variant
     * 
     * @param UploadedFile|string $image Image file or path
     * @return static
     */
    public function swatchImage(UploadedFile|string $image): static
    {
        $images = $this->get('variant_images', []);
        $images['swatch'] = $image;
        return $this->set('variant_images', $images);
    }
    
    /**
     * Add gallery images for variant
     * 
     * @param array $images Array of UploadedFile or string paths
     * @return static
     */
    public function galleryImages(array $images): static
    {
        $variantImages = $this->get('variant_images', []);
        $variantImages['gallery'] = $images;
        return $this->set('variant_images', $variantImages);
    }
    
    /**
     * Set custom attribute (type-safe)
     * 
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     * @param string $dataType Data type (string, number, boolean, json)
     * @param string|null $category Attribute category
     * @return static
     */
    public function customAttribute(string $key, mixed $value, string $dataType = 'string', ?string $category = null): static
    {
        $attributes = $this->get('attributes', []);
        $attributes[$key] = [
            'value' => $value,
            'data_type' => $dataType,
            'category' => $category,
        ];
        return $this->set('attributes', $attributes);
    }
    
    /**
     * Batch set variant dimensions for window shades (width × drop)
     * 
     * @param string $width Width dimension (e.g., '120cm')
     * @param string $drop Drop dimension (e.g., '160cm')
     * @return static
     */
    public function windowDimensions(string $width, string $drop): static
    {
        return $this->width($width)->drop($drop);
    }
    
    /**
     * Set cost price (convenience method)
     * 
     * @param float $price Cost price
     * @return static
     */
    public function costPrice(float $price): static
    {
        $pricing = $this->get('pricing', []);
        $pricing['cost_price'] = $price;
        return $this->set('pricing', $pricing);
    }
    
    /**
     * Apply VAT-inclusive pricing calculation
     * 
     * @param float $priceExcludingVat Base price excluding VAT
     * @param float $vatRate VAT rate (default 20% for UK)
     * @return static
     */
    public function vatInclusivePrice(float $priceExcludingVat, float $vatRate = 20): static
    {
        $vatAmount = $priceExcludingVat * ($vatRate / 100);
        $priceIncludingVat = $priceExcludingVat + $vatAmount;
        
        $pricing = $this->get('pricing', []);
        $pricing['retail_price'] = $priceIncludingVat;
        $pricing['price_excluding_vat'] = $priceExcludingVat;
        $pricing['vat_rate'] = $vatRate;
        $pricing['vat_amount'] = $vatAmount;
        
        return $this->set('pricing', $pricing);
    }
    
    /**
     * Execute the builder and create the variant
     * 
     * @return ProductVariant The created variant
     */
    public function execute(): ProductVariant
    {
        $this->validate();
        
        // Use comprehensive action if we have complex data (barcodes, marketplace pricing, images)
        if ($this->hasComplexData()) {
            return app(CreateVariantWithBarcodeAction::class)->execute($this->data);
        }
        
        // Use simple action for basic variants
        return app(CreateVariantAction::class)->execute($this->data);
    }
    
    /**
     * Check if we have complex data requiring the full creation action
     * 
     * @return bool
     */
    protected function hasComplexData(): bool
    {
        $complexFields = [
            'barcodes',
            'barcode_pool_id', 
            'marketplace_pricing',
            'variant_images',
            'pricing',  // Include pricing to use comprehensive action
        ];
        
        foreach ($complexFields as $field) {
            if ($this->has($field)) {
                return true;
            }
        }
        
        // Check if attributes has complex structure
        if ($this->has('attributes')) {
            $attributes = $this->get('attributes');
            foreach ($attributes as $value) {
                if (is_array($value) && isset($value['data_type'])) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Save (alias for execute)
     * 
     * @return ProductVariant The created variant
     */
    public function save(): ProductVariant
    {
        return $this->execute();
    }
    
    /**
     * Custom validation for variant builder
     * 
     * @throws InvalidArgumentException If validation fails
     * @return void
     */
    protected function customValidation(): void
    {
        // Validate SKU format and uniqueness if provided
        if ($this->has('sku')) {
            $sku = $this->get('sku');
            
            // Check format
            if (!preg_match('/^[A-Z0-9\-_]+$/i', $sku)) {
                throw new InvalidArgumentException('SKU can only contain letters, numbers, hyphens, and underscores');
            }
            
            // Check uniqueness
            if (ProductVariant::where('sku', $sku)->exists()) {
                throw new DuplicateSkuException($sku);
            }
        }
        
        // Validate stock level is non-negative
        if ($this->has('stock_level') && $this->get('stock_level') < 0) {
            throw new InvalidArgumentException('Stock level cannot be negative');
        }
        
        // Validate dimensions are positive
        $dimensionFields = ['package_length', 'package_width', 'package_height', 'package_weight'];
        foreach ($dimensionFields as $field) {
            if ($this->has($field) && $this->get($field) <= 0) {
                throw new InvalidArgumentException("{$field} must be greater than 0");
            }
        }
    }
}