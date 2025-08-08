<?php

namespace App\Actions\Products;

use App\Actions\Base\BaseAction;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Create Product Action
 * 
 * Handles the creation of new products with validation and transaction safety.
 * Ensures data integrity and follows Laravel best practices.
 * 
 * @package App\Actions\Products
 */
class CreateProductAction extends BaseAction
{
    /**
     * Execute product creation
     * 
     * @param array $data Product data
     * @return Product The created product
     * @throws InvalidArgumentException If required data is missing
     */
    public function execute(...$params): Product
    {
        $data = $params[0] ?? [];
        
        $this->validateProductData($data);
        
        return DB::transaction(function () use ($data) {
            // Auto-generate slug if not provided
            if (empty($data['slug']) && !empty($data['name'])) {
                $data['slug'] = $this->generateUniqueSlug($data['name']);
            }
            
            // Set default status if not provided
            $data['status'] = $data['status'] ?? 'draft';
            
            // Create the product
            $product = Product::create($data);
            
            // Handle any additional post-creation logic
            $this->handlePostCreation($product, $data);
            
            return $product->fresh();
        });
    }
    
    /**
     * Validate product data before creation
     * 
     * @param array $data Product data to validate
     * @throws InvalidArgumentException If validation fails
     * @return void
     */
    protected function validateProductData(array $data): void
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Product name is required');
        }
        
        if (!empty($data['parent_sku'])) {
            // Validate parent_sku uniqueness
            if (Product::where('parent_sku', $data['parent_sku'])->exists()) {
                throw new InvalidArgumentException('Product with this parent SKU already exists');
            }
        }
        
        // Validate status if provided
        if (isset($data['status']) && !in_array($data['status'], ['draft', 'active', 'inactive', 'archived'])) {
            throw new InvalidArgumentException('Invalid product status');
        }
    }
    
    /**
     * Generate unique slug from product name
     * 
     * @param string $name Product name
     * @return string Unique slug
     */
    protected function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;
        
        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Handle post-creation logic
     * 
     * @param Product $product Created product
     * @param array $data Original creation data
     * @return void
     */
    protected function handlePostCreation(Product $product, array $data): void
    {
        // Handle categories if provided
        if (!empty($data['categories'])) {
            $this->attachCategories($product, $data['categories']);
        }
        
        // Handle initial attributes if provided
        if (!empty($data['attributes'])) {
            $this->setAttributes($product, $data['attributes']);
        }
        
        // Handle initial metadata if provided
        if (!empty($data['metadata'])) {
            $this->setMetadata($product, $data['metadata']);
        }
    }
    
    /**
     * Attach categories to the product
     * 
     * @param Product $product Product instance
     * @param array $categories Category data
     * @return void
     */
    protected function attachCategories(Product $product, array $categories): void
    {
        if (empty($categories)) {
            return;
        }
        
        $categoryData = [];
        foreach ($categories as $categoryId => $data) {
            $categoryData[$categoryId] = [
                'is_primary' => $data['is_primary'] ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        $product->categories()->attach($categoryData);
    }
    
    /**
     * Set product attributes
     * 
     * @param Product $product Product instance
     * @param array $attributes Attribute data
     * @return void
     */
    protected function setAttributes(Product $product, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $product->attributes()->create([
                'attribute_key' => $key,
                'attribute_value' => $value,
                'data_type' => is_numeric($value) ? 'number' : 'text',
            ]);
        }
    }
    
    /**
     * Set product metadata
     * 
     * @param Product $product Product instance
     * @param array $metadata Metadata
     * @return void
     */
    protected function setMetadata(Product $product, array $metadata): void
    {
        foreach ($metadata as $key => $value) {
            $product->metadata()->create([
                'meta_key' => $key,
                'meta_value' => $value,
            ]);
        }
    }
}