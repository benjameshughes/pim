<?php

namespace App\Services;

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class AttributeService
{
    /**
     * Get all active attributes for a specific context
     */
    public function getActiveAttributes(string $appliesTo = 'both'): Collection
    {
        $query = AttributeDefinition::active()->ordered();
        
        if ($appliesTo !== 'both') {
            $query->where(function($q) use ($appliesTo) {
                $q->where('applies_to', $appliesTo)
                  ->orWhere('applies_to', 'both');
            });
        }
        
        return $query->get();
    }

    /**
     * Get attributes by category
     */
    public function getAttributesByCategory(?string $category = null): Collection
    {
        $query = AttributeDefinition::active()->ordered();
        
        if ($category) {
            $query->byCategory($category);
        }
        
        return $query->get()->groupBy('category');
    }

    /**
     * Get core window treatment attributes
     */
    public function getCoreWindowTreatmentAttributes(): Collection
    {
        return AttributeDefinition::active()
            ->whereIn('key', [
                'color', 'width', 'drop', 'material', 
                'control_type', 'operation_type', 'fitting_type'
            ])
            ->ordered()
            ->get();
    }

    /**
     * Validate attribute value against its definition
     */
    public function validateAttributeValue(string $attributeKey, $value): bool
    {
        $attribute = AttributeDefinition::where('key', $attributeKey)->first();
        
        if (!$attribute) {
            return false;
        }
        
        return $attribute->validateValue($value);
    }

    /**
     * Get available options for a dropdown attribute
     */
    public function getAttributeOptions(string $attributeKey): array
    {
        $attribute = AttributeDefinition::where('key', $attributeKey)->first();
        
        if (!$attribute || !isset($attribute->validation_rules['options'])) {
            return [];
        }
        
        return $attribute->validation_rules['options'];
    }

    /**
     * Get common sizes for dimensional attributes
     */
    public function getCommonSizes(string $attributeKey): array
    {
        $attribute = AttributeDefinition::where('key', $attributeKey)->first();
        
        if (!$attribute || !isset($attribute->validation_rules['common_sizes'])) {
            return [];
        }
        
        return $attribute->validation_rules['common_sizes'];
    }

    /**
     * Create attribute value record for a product
     */
    public function setProductAttribute(Product $product, string $attributeKey, $value): bool
    {
        if (!$this->validateAttributeValue($attributeKey, $value)) {
            return false;
        }

        $attribute = AttributeDefinition::where('key', $attributeKey)
            ->forProducts()
            ->first();
            
        if (!$attribute) {
            return false;
        }

        $product->attributes()->updateOrCreate(
            ['attribute_definition_id' => $attribute->id],
            ['value' => $value]
        );

        return true;
    }

    /**
     * Create attribute value record for a variant
     */
    public function setVariantAttribute(ProductVariant $variant, string $attributeKey, $value): bool
    {
        if (!$this->validateAttributeValue($attributeKey, $value)) {
            return false;
        }

        $attribute = AttributeDefinition::where('key', $attributeKey)
            ->forVariants()
            ->first();
            
        if (!$attribute) {
            return false;
        }

        $variant->attributes()->updateOrCreate(
            ['attribute_definition_id' => $attribute->id],
            ['value' => $value]
        );

        return true;
    }

    /**
     * Get formatted attribute display value
     */
    public function getFormattedValue(string $attributeKey, $value): string
    {
        $attribute = AttributeDefinition::where('key', $attributeKey)->first();
        
        if (!$attribute) {
            return (string) $value;
        }

        // Handle units for dimensional attributes
        if (isset($attribute->validation_rules['unit'])) {
            return $value . ' ' . $attribute->validation_rules['unit'];
        }

        // Handle boolean values
        if ($attribute->data_type === 'boolean') {
            return $value ? 'Yes' : 'No';
        }

        // Format option values (convert snake_case to Title Case)
        if (isset($attribute->validation_rules['options']) && 
            in_array($value, $attribute->validation_rules['options'])) {
            return ucwords(str_replace(['-', '_'], ' ', $value));
        }

        return (string) $value;
    }

    /**
     * Get attribute categories with counts
     */
    public function getAttributeCategoriesWithCounts(): Collection
    {
        return AttributeDefinition::active()
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('category')
            ->get();
    }

    /**
     * Bulk update attributes for a model
     */
    public function bulkUpdateAttributes($model, array $attributes): array
    {
        $results = [];
        
        foreach ($attributes as $attributeKey => $value) {
            if ($model instanceof Product) {
                $results[$attributeKey] = $this->setProductAttribute($model, $attributeKey, $value);
            } elseif ($model instanceof ProductVariant) {
                $results[$attributeKey] = $this->setVariantAttribute($model, $attributeKey, $value);
            } else {
                $results[$attributeKey] = false;
            }
        }
        
        return $results;
    }

    /**
     * Search products by attribute values
     */
    public function searchProductsByAttributes(array $attributeFilters): Collection
    {
        $query = Product::query();
        
        foreach ($attributeFilters as $attributeKey => $value) {
            $attribute = AttributeDefinition::where('key', $attributeKey)->first();
            
            if ($attribute) {
                $query->whereHas('attributes', function($q) use ($attribute, $value) {
                    $q->where('attribute_definition_id', $attribute->id)
                      ->where('value', 'like', "%{$value}%");
                });
            }
        }
        
        return $query->with(['attributes.attributeDefinition'])->get();
    }
}