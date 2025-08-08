<?php

namespace App\Actions\Variants;

use App\Actions\Base\BaseAction;
use App\Models\ProductVariant;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Create Product Variant Action
 * 
 * Handles the creation of new product variants with validation and transaction safety.
 * Ensures data integrity and follows Laravel best practices.
 * 
 * @package App\Actions\Variants
 */
class CreateVariantAction extends BaseAction
{
    /**
     * Execute variant creation
     * 
     * @param array $data Variant data
     * @return ProductVariant The created variant
     * @throws InvalidArgumentException If required data is missing
     */
    public function execute(...$params): ProductVariant
    {
        $data = $params[0] ?? [];
        
        $this->validateVariantData($data);
        
        return DB::transaction(function () use ($data) {
            // Create the variant
            $variant = ProductVariant::create($data);
            
            // Handle any additional post-creation logic
            $this->handlePostCreation($variant, $data);
            
            return $variant->fresh();
        });
    }
    
    /**
     * Validate variant data before creation
     * 
     * @param array $data Variant data to validate
     * @throws InvalidArgumentException If validation fails
     * @return void
     */
    protected function validateVariantData(array $data): void
    {
        if (empty($data['product_id'])) {
            throw new InvalidArgumentException('Product ID is required');
        }
        
        if (empty($data['sku'])) {
            throw new InvalidArgumentException('Variant SKU is required');
        }
        
        // Validate product exists
        if (!Product::find($data['product_id'])) {
            throw new InvalidArgumentException('Product not found');
        }
        
        // Validate SKU uniqueness
        if (ProductVariant::where('sku', $data['sku'])->exists()) {
            throw new InvalidArgumentException('Variant with this SKU already exists');
        }
        
        // Validate status if provided
        if (isset($data['status']) && !in_array($data['status'], ['draft', 'active', 'inactive', 'archived'])) {
            throw new InvalidArgumentException('Invalid variant status');
        }
    }
    
    /**
     * Handle post-creation logic
     * 
     * @param ProductVariant $variant Created variant
     * @param array $data Original creation data
     * @return void
     */
    protected function handlePostCreation(ProductVariant $variant, array $data): void
    {
        // Handle initial attributes if provided
        if (!empty($data['attributes'])) {
            $this->setAttributes($variant, $data['attributes']);
        }
        
        // Handle initial pricing if provided
        if (!empty($data['pricing'])) {
            $this->setPricing($variant, $data['pricing']);
        }
        
        // Handle initial barcodes if provided
        if (!empty($data['barcodes'])) {
            $this->setBarcodes($variant, $data['barcodes']);
        }
    }
    
    /**
     * Set variant attributes
     * 
     * @param ProductVariant $variant Variant instance
     * @param array $attributes Attribute data
     * @return void
     */
    protected function setAttributes(ProductVariant $variant, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $variant->setVariantAttributeValue($key, $value, 'string');
        }
    }
    
    /**
     * Set variant pricing
     * 
     * @param ProductVariant $variant Variant instance
     * @param array $pricing Pricing data
     * @return void
     */
    protected function setPricing(ProductVariant $variant, array $pricing): void
    {
        $variant->pricing()->create([
            'retail_price' => $pricing['retail_price'] ?? null,
            'cost_price' => $pricing['cost_price'] ?? null,
            'sale_price' => $pricing['sale_price'] ?? null,
            'currency' => $pricing['currency'] ?? 'GBP',
        ]);
    }
    
    /**
     * Set variant barcodes
     * 
     * @param ProductVariant $variant Variant instance
     * @param array $barcodes Barcode data
     * @return void
     */
    protected function setBarcodes(ProductVariant $variant, array $barcodes): void
    {
        foreach ($barcodes as $barcode) {
            $variant->barcodes()->create([
                'barcode' => $barcode['barcode'],
                'barcode_type' => $barcode['type'] ?? 'EAN13',
                'is_primary' => $barcode['is_primary'] ?? false,
            ]);
        }
    }
}