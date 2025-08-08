<?php

namespace App\Actions\Products;

use App\Actions\Base\BaseAction;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Update Product Action
 * 
 * Handles updating existing products with validation and transaction safety.
 * Supports partial updates and maintains data integrity.
 * 
 * @package App\Actions\Products
 */
class UpdateProductAction extends BaseAction
{
    /**
     * Execute product update
     * 
     * @param Product $product Product to update
     * @param array $data Update data
     * @return Product The updated product
     * @throws InvalidArgumentException If validation fails
     */
    public function execute(...$params): Product
    {
        $product = $params[0];
        $data = $params[1] ?? [];
        
        if (!$product instanceof Product) {
            throw new InvalidArgumentException('First parameter must be a Product instance');
        }
        
        $this->validateUpdateData($product, $data);
        
        return DB::transaction(function () use ($product, $data) {
            // Handle slug update if name changed
            if (isset($data['name']) && $data['name'] !== $product->name) {
                if (empty($data['slug'])) {
                    $data['slug'] = $this->generateUniqueSlug($data['name'], $product->id);
                }
            }
            
            // Update the product
            $product->update($data);
            
            // Handle relationship updates
            $this->handleRelationshipUpdates($product, $data);
            
            return $product->fresh();
        });
    }
    
    /**
     * Validate update data
     * 
     * @param Product $product Product being updated
     * @param array $data Update data
     * @throws InvalidArgumentException If validation fails
     * @return void
     */
    protected function validateUpdateData(Product $product, array $data): void
    {
        // Validate parent_sku uniqueness if being changed
        if (isset($data['parent_sku']) && $data['parent_sku'] !== $product->parent_sku) {
            if (Product::where('parent_sku', $data['parent_sku'])
                      ->where('id', '!=', $product->id)
                      ->exists()) {
                throw new InvalidArgumentException('Product with this parent SKU already exists');
            }
        }
        
        // Validate status if provided
        if (isset($data['status']) && !in_array($data['status'], ['draft', 'active', 'inactive', 'archived'])) {
            throw new InvalidArgumentException('Invalid product status');
        }
        
        // Validate slug uniqueness if provided
        if (isset($data['slug']) && $data['slug'] !== $product->slug) {
            if (Product::where('slug', $data['slug'])
                      ->where('id', '!=', $product->id)
                      ->exists()) {
                throw new InvalidArgumentException('Product with this slug already exists');
            }
        }
    }
    
    /**
     * Generate unique slug from name, excluding current product
     * 
     * @param string $name Product name
     * @param int $excludeId Product ID to exclude from uniqueness check
     * @return string Unique slug
     */
    protected function generateUniqueSlug(string $name, int $excludeId): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;
        
        while (Product::where('slug', $slug)->where('id', '!=', $excludeId)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Handle relationship updates
     * 
     * @param Product $product Product instance
     * @param array $data Update data
     * @return void
     */
    protected function handleRelationshipUpdates(Product $product, array $data): void
    {
        // Handle categories update
        if (array_key_exists('categories', $data)) {
            $this->updateCategories($product, $data['categories']);
        }
        
        // Handle attributes update
        if (array_key_exists('attributes', $data)) {
            $this->updateAttributes($product, $data['attributes']);
        }
        
        // Handle metadata update
        if (array_key_exists('metadata', $data)) {
            $this->updateMetadata($product, $data['metadata']);
        }
    }
    
    /**
     * Update product categories
     * 
     * @param Product $product Product instance
     * @param array|null $categories Category data
     * @return void
     */
    protected function updateCategories(Product $product, ?array $categories): void
    {
        if ($categories === null) {
            $product->categories()->detach();
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
        
        $product->categories()->sync($categoryData);
    }
    
    /**
     * Update product attributes
     * 
     * @param Product $product Product instance
     * @param array|null $attributes Attribute data
     * @return void
     */
    protected function updateAttributes(Product $product, ?array $attributes): void
    {
        if ($attributes === null) {
            $product->attributes()->delete();
            return;
        }
        
        // Remove existing attributes
        $product->attributes()->delete();
        
        // Add new attributes
        foreach ($attributes as $key => $value) {
            $product->attributes()->create([
                'attribute_key' => $key,
                'attribute_value' => $value,
                'data_type' => is_numeric($value) ? 'number' : 'text',
            ]);
        }
    }
    
    /**
     * Update product metadata
     * 
     * @param Product $product Product instance
     * @param array|null $metadata Metadata
     * @return void
     */
    protected function updateMetadata(Product $product, ?array $metadata): void
    {
        if ($metadata === null) {
            $product->metadata()->delete();
            return;
        }
        
        // Remove existing metadata
        $product->metadata()->delete();
        
        // Add new metadata
        foreach ($metadata as $key => $value) {
            $product->metadata()->create([
                'meta_key' => $key,
                'meta_value' => $value,
            ]);
        }
    }
}