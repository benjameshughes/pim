<?php

namespace App\Actions\Import;

use App\Models\Product;
use App\Models\ProductAttribute;
use Illuminate\Support\Facades\Log;

class HandleProductAttributes
{
    public function execute(Product $product, array $data): void
    {
        $attributeFields = [
            'brand', 'manufacturer', 'material', 'category', 'weight_unit',
            'dimensions_unit', 'color_family', 'size_system', 'age_group',
            'gender', 'season', 'care_instructions', 'warranty', 'certification'
        ];
        
        foreach ($attributeFields as $field) {
            if (!empty($data[$field])) {
                $this->createOrUpdateAttribute($product, $field, $data[$field]);
            }
        }
        
        // Handle custom attribute fields (custom_attribute_1, custom_attribute_2, etc.)
        for ($i = 1; $i <= 10; $i++) {
            $nameField = "custom_attribute_{$i}_name";
            $valueField = "custom_attribute_{$i}_value";
            
            if (!empty($data[$nameField]) && !empty($data[$valueField])) {
                $this->createOrUpdateAttribute($product, $data[$nameField], $data[$valueField]);
            }
        }
    }
    
    private function createOrUpdateAttribute(Product $product, string $name, $value): void
    {
        $dataType = $this->determineDataType($value);
        $category = $this->getCategoryForAttribute($name);
        
        ProductAttribute::updateOrCreate([
            'product_id' => $product->id,
            'name' => $name,
        ], [
            'value' => $value,
            'data_type' => $dataType,
            'category' => $category,
        ]);
        
        Log::debug("Created/updated product attribute", [
            'product_id' => $product->id,
            'name' => $name,
            'value' => $value,
            'data_type' => $dataType,
            'category' => $category
        ]);
    }
    
    private function determineDataType(string $value): string
    {
        if (is_numeric($value)) {
            return str_contains($value, '.') ? 'decimal' : 'integer';
        }
        
        if (in_array(strtolower($value), ['true', 'false', '1', '0', 'yes', 'no'])) {
            return 'boolean';
        }
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return 'date';
        }
        
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return 'url';
        }
        
        return 'text';
    }
    
    private function getCategoryForAttribute(string $attributeKey): string
    {
        $categories = [
            'brand' => 'general',
            'manufacturer' => 'general',
            'material' => 'physical',
            'weight_unit' => 'physical',
            'dimensions_unit' => 'physical',
            'color_family' => 'visual',
            'size_system' => 'sizing',
            'age_group' => 'targeting',
            'gender' => 'targeting',
            'season' => 'seasonal',
            'care_instructions' => 'care',
            'warranty' => 'legal',
            'certification' => 'legal',
        ];
        
        return $categories[$attributeKey] ?? 'custom';
    }
}