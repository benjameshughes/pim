<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\AttributeDefinition;
use App\Exceptions\ProductWizard\ProductSaveException;

/**
 * 💾 SAVE PRODUCT ACTION
 * 
 * Handles Step 1: Parent Product creation/update
 * - Product creation/update with validation
 * - Brand attribute management
 * - Clean separation from Livewire UI logic
 * 
 * Follows ProductWizard.md specification for Step 1
 */
class SaveProductAction
{
    public function execute(array $data, ?Product $product = null): array
    {
        try {
            $isEditMode = $product && $product->exists;
            
            // 1. Save/Update Product
            if ($isEditMode) {
                $product->update([
                    'name' => $data['name'],
                    'parent_sku' => $data['parent_sku'],
                    'description' => $data['description'] ?? '',
                    'status' => $data['status'],
                ]);
            } else {
                $product = Product::create([
                    'name' => $data['name'],
                    'parent_sku' => $data['parent_sku'],
                    'description' => $data['description'] ?? '',
                    'status' => $data['status'],
                ]);
            }

            // 2. Handle Brand Attribute (per ProductWizard.md Step 1 requirements)
            if (!empty($data['brand'])) {
                $this->saveBrandAttribute($product, $data['brand']);
            }

            return [
                'success' => true,
                'product' => $product->fresh(),
                'message' => $isEditMode ? 'Product updated successfully' : 'Product created successfully'
            ];
        } catch (\Exception $e) {
            throw ProductSaveException::productCreationFailed($e);
        }
    }

    /**
     * Handle brand attribute creation/update
     */
    protected function saveBrandAttribute(Product $product, string $brand): void
    {
        try {
            // Ensure brand attribute definition exists
            $brandDef = AttributeDefinition::findByKey('brand');
            if (!$brandDef) {
                $brandDef = AttributeDefinition::create([
                    'key' => 'brand',
                    'name' => 'Brand',
                    'type' => 'string',
                    'is_required' => false,
                    'applies_to_products' => true,
                    'applies_to_variants' => true,
                ]);
            }

            // Set brand value for product
            ProductAttribute::setValueFor($product, 'brand', $brand);
        } catch (\Exception $e) {
            throw ProductSaveException::attributeCreationFailed('brand', $e);
        }
    }
}