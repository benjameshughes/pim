<?php

namespace App\Actions\Import;

use App\Models\ProductVariant;
use App\Models\VariantAttribute;
use Illuminate\Support\Facades\Log;

class HandleVariantAttributes
{
    public function execute(ProductVariant $variant, array $data): void
    {
        // Core variant attributes - these are the primary ones displayed prominently
        $coreAttributes = [
            'color' => 'text',      // Color as text
            'width' => 'number',    // Width as number  
            'drop' => 'number',     // Drop as number
        ];
        
        // Set core attributes first
        foreach ($coreAttributes as $key => $dataType) {
            if (isset($data[$key]) && $data[$key] !== null && $data[$key] !== '') {
                $this->createOrUpdateAttribute($variant, $key, $data[$key], $dataType, 'core');
            }
        }
        
        // Additional variant attributes  
        $attributeFields = [
            'finish', 'texture', 'pattern', 'style', 'fit', 'cut',
            'collar_type', 'sleeve_type', 'heel_height', 'sole_material',
            'lining_material', 'closure_type', 'pocket_count', 'additional_features'
        ];
        
        foreach ($attributeFields as $field) {
            if (!empty($data[$field])) {
                $this->createOrUpdateAttribute($variant, $field, $data[$field]);
            }
        }
        
        // Handle custom variant attribute fields
        for ($i = 1; $i <= 10; $i++) {
            $nameField = "custom_variant_attribute_{$i}_name";
            $valueField = "custom_variant_attribute_{$i}_value";
            
            if (!empty($data[$nameField]) && !empty($data[$valueField])) {
                $this->createOrUpdateAttribute($variant, $data[$nameField], $data[$valueField]);
            }
        }
    }
    
    private function createOrUpdateAttribute(ProductVariant $variant, string $name, $value, ?string $dataType = null, ?string $category = null): void
    {
        $dataType = $dataType ?? $this->determineDataType($value);
        $category = $category ?? $this->getCategoryForAttribute($name);
        
        VariantAttribute::setValue(
            $variant->id,
            $name,
            $value,
            $dataType,
            $category
        );
        
        Log::debug("Created/updated variant attribute", [
            'variant_id' => $variant->id,
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
            // Core variant attributes
            'color' => 'core',
            'width' => 'core', 
            'drop' => 'core',
            
            // Additional attributes
            'finish' => 'surface',
            'texture' => 'surface',
            'pattern' => 'visual',
            'style' => 'design',
            'fit' => 'sizing',
            'cut' => 'design',
            'collar_type' => 'design',
            'sleeve_type' => 'design',
            'heel_height' => 'physical',
            'sole_material' => 'material',
            'lining_material' => 'material',
            'closure_type' => 'functional',
            'pocket_count' => 'functional',
            'additional_features' => 'features',
        ];
        
        return $categories[$attributeKey] ?? 'custom';
    }
}