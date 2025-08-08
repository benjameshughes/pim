<?php

namespace App\Services\Import;

class ColumnMappingService
{
    private array $fieldMappings = [
        // Product fields
        'name' => 'product_name',
        'product' => 'product_name', 
        'title' => 'product_name',
        'product_name' => 'product_name',
        'description' => 'description',
        'desc' => 'description',
        'product_description' => 'description',
        
        // Parent/child relationships
        'is parent' => 'is_parent',
        'parent' => 'is_parent',
        'type' => 'is_parent',
        'parent name' => 'parent_name',
        'parent product' => 'parent_name',
        'parent_name' => 'parent_name',
        
        // Variant fields  
        'sku' => 'variant_sku',
        'variant_sku' => 'variant_sku',
        'product_sku' => 'variant_sku',
        'item_sku' => 'variant_sku',
        'color' => 'variant_color',
        'colour' => 'variant_color',
        'variant_color' => 'variant_color',
        'size' => 'variant_size',
        'variant_size' => 'variant_size',
        
        // Pricing
        'retail price' => 'retail_price',
        'price' => 'retail_price',
        'retail_price' => 'retail_price',
        'selling_price' => 'retail_price',
        'cost price' => 'cost_price',
        'cost' => 'cost_price',
        'cost_price' => 'cost_price',
        'wholesale_price' => 'cost_price',
        
        // Stock
        'stock' => 'stock_level',
        'quantity' => 'stock_level',
        'stock_level' => 'stock_level',
        'qty' => 'stock_level',
        'inventory' => 'stock_level',
        
        // Dimensions
        'length' => 'package_length',
        'width' => 'package_width',
        'height' => 'package_height',
        'weight' => 'package_weight',
        'package_length' => 'package_length',
        'package_width' => 'package_width',
        'package_height' => 'package_height',
        'package_weight' => 'package_weight',
        
        // Barcodes
        'barcode' => 'barcode',
        'upc' => 'barcode',
        'ean' => 'barcode',
        'gtin' => 'barcode',
        'barcode_type' => 'barcode_type',
        
        // Images
        'image' => 'image_urls',
        'images' => 'image_urls',
        'photo' => 'image_urls',
        'image_url' => 'image_urls',
        'image_urls' => 'image_urls',
        'main_image' => 'image_urls',
        'product_image' => 'image_urls',
        
        // Status
        'status' => 'status',
        'active' => 'status',
        'enabled' => 'status',
    ];

    private array $featurePatterns = [
        '/(?:item\s+)?feature\s+(\d+)/i' => 'product_features_%d',
        '/feature[\s_-]*(\d+)/i' => 'product_features_%d',
    ];

    private array $detailPatterns = [
        '/(?:finer\s+)?detail\s+(\d+)/i' => 'product_details_%d',
        '/detail[\s_-]*(\d+)/i' => 'product_details_%d',
    ];

    private array $marketplacePatterns = [
        // eBay patterns
        '/ebay.*bo.*title/i' => 'ebay_bo_title',
        '/ebay.*business.*outlet.*title/i' => 'ebay_bo_title',
        '/ebay.*bo.*description/i' => 'ebay_bo_description',
        '/ebay.*business.*outlet.*description/i' => 'ebay_bo_description',
        '/ebay.*bo.*price/i' => 'ebay_bo_price',
        '/ebay.*business.*outlet.*price/i' => 'ebay_bo_price',
        '/ebay.*title/i' => 'ebay_title',
        '/ebay.*description/i' => 'ebay_description',
        '/ebay.*price/i' => 'ebay_price',
        '/ebay.*bo.*item.*id/i' => 'ebay_bo_item_id',
        '/ebay.*business.*outlet.*item/i' => 'ebay_bo_item_id',
        '/ebay.*item.*id/i' => 'ebay_item_id',
        '/ebay.*id/i' => 'ebay_item_id',
        
        // Amazon patterns
        '/amazon.*fba.*title/i' => 'amazon_fba_title',
        '/amazon.*fba.*description/i' => 'amazon_fba_description',
        '/amazon.*fba.*price/i' => 'amazon_fba_price',
        '/amazon.*fba.*asin/i' => 'amazon_fba_asin',
        '/amazon.*title/i' => 'amazon_title',
        '/amazon.*description/i' => 'amazon_description',
        '/amazon.*price/i' => 'amazon_price',
        '/amazon.*asin/i' => 'amazon_asin',
        '/asin/i' => 'amazon_asin',
        
        // OnBuy patterns
        '/onbuy.*title/i' => 'onbuy_title',
        '/onbuy.*description/i' => 'onbuy_description',
        '/onbuy.*price/i' => 'onbuy_price',
        '/onbuy.*product.*id/i' => 'onbuy_product_id',
        '/onbuy.*id/i' => 'onbuy_product_id',
        
        // Website patterns
        '/website.*title/i' => 'website_title',
        '/website.*description/i' => 'website_description',
        '/website.*price/i' => 'website_price',
    ];

    private array $attributePatterns = [
        // General attributes
        '/material/i' => 'attribute_material',
        '/fabric/i' => 'attribute_fabric_type',
        '/operation/i' => 'attribute_operation_type',
        '/mount/i' => 'attribute_mount_type',
        '/child.*safety/i' => 'attribute_child_safety',
        '/room.*darkening/i' => 'attribute_room_darkening',
        '/blackout/i' => 'attribute_room_darkening',
        '/fire.*rating/i' => 'attribute_fire_rating',
        '/warranty/i' => 'attribute_warranty_years',
        '/installation/i' => 'attribute_installation_required',
        '/custom.*size/i' => 'attribute_custom_size_available',
        
        // Variant-specific attributes
        '/width.*mm/i' => 'variant_attribute_width_mm',
        '/drop.*mm/i' => 'variant_attribute_drop_mm',
        '/height.*mm/i' => 'variant_attribute_drop_mm',
        '/chain.*length/i' => 'variant_attribute_chain_length',
        '/slat.*width/i' => 'variant_attribute_slat_width',
        '/fabric.*pattern/i' => 'variant_attribute_fabric_pattern',
        '/opacity/i' => 'variant_attribute_opacity_level',
    ];

    public function autoMapColumns(array $headers): array
    {
        $mapping = [];
        
        foreach ($headers as $index => $header) {
            $mapping[$index] = $this->guessFieldMapping($header);
        }
        
        return $mapping;
    }

    public function guessFieldMapping(string $header): string
    {
        $header = strtolower(trim($header));
        
        // Try exact matches first
        if (isset($this->fieldMappings[$header])) {
            return $this->fieldMappings[$header];
        }
        
        // Try feature patterns
        foreach ($this->featurePatterns as $pattern => $template) {
            if (preg_match($pattern, $header, $matches)) {
                $num = (int) $matches[1];
                if ($num >= 1 && $num <= 5) {
                    return sprintf($template, $num);
                }
            }
        }
        
        // Try detail patterns  
        foreach ($this->detailPatterns as $pattern => $template) {
            if (preg_match($pattern, $header, $matches)) {
                $num = (int) $matches[1];
                if ($num >= 1 && $num <= 5) {
                    return sprintf($template, $num);
                }
            }
        }
        
        // Try marketplace patterns
        foreach ($this->marketplacePatterns as $pattern => $field) {
            if (preg_match($pattern, $header)) {
                return $field;
            }
        }
        
        // Try attribute patterns
        foreach ($this->attributePatterns as $pattern => $field) {
            if (preg_match($pattern, $header)) {
                return $field;
            }
        }
        
        // Try partial matches on basic fields
        foreach ($this->fieldMappings as $key => $field) {
            if (str_contains($header, $key) || str_contains($key, $header)) {
                return $field;
            }
        }
        
        return ''; // No mapping found
    }

    public function getAvailableFields(): array
    {
        $fields = [
            'Core Product Fields' => [
                'product_name' => 'Product Name',
                'description' => 'Product Description',
                'parent_name' => 'Parent Product Name',
                'is_parent' => 'Is Parent Product',
                'status' => 'Product Status',
            ],
            
            'Variant Fields' => [
                'variant_sku' => 'Variant SKU',
                'variant_color' => 'Variant Color',
                'variant_size' => 'Variant Size',
            ],
            
            'Pricing & Stock' => [
                'retail_price' => 'Retail Price',
                'cost_price' => 'Cost Price', 
                'stock_level' => 'Stock Level',
            ],
            
            'Physical Attributes' => [
                'package_length' => 'Package Length',
                'package_width' => 'Package Width', 
                'package_height' => 'Package Height',
                'package_weight' => 'Package Weight',
            ],
            
            'Barcodes & Images' => [
                'barcode' => 'Barcode',
                'barcode_type' => 'Barcode Type',
                'image_urls' => 'Image URLs',
            ],
            
            'Product Features' => [
                'product_features_1' => 'Product Feature 1',
                'product_features_2' => 'Product Feature 2',
                'product_features_3' => 'Product Feature 3',
                'product_features_4' => 'Product Feature 4',
                'product_features_5' => 'Product Feature 5',
            ],
            
            'Product Details' => [
                'product_details_1' => 'Product Detail 1',
                'product_details_2' => 'Product Detail 2',
                'product_details_3' => 'Product Detail 3',
                'product_details_4' => 'Product Detail 4',
                'product_details_5' => 'Product Detail 5',
            ],
            
            'eBay Fields' => [
                'ebay_title' => 'eBay Title',
                'ebay_description' => 'eBay Description',
                'ebay_price' => 'eBay Price',
                'ebay_item_id' => 'eBay Item ID',
                'ebay_bo_title' => 'eBay Business Outlet Title',
                'ebay_bo_description' => 'eBay Business Outlet Description',
                'ebay_bo_price' => 'eBay Business Outlet Price',
                'ebay_bo_item_id' => 'eBay Business Outlet Item ID',
            ],
            
            'Amazon Fields' => [
                'amazon_title' => 'Amazon Title',
                'amazon_description' => 'Amazon Description',
                'amazon_price' => 'Amazon Price',
                'amazon_asin' => 'Amazon ASIN',
                'amazon_fba_title' => 'Amazon FBA Title',
                'amazon_fba_description' => 'Amazon FBA Description',
                'amazon_fba_price' => 'Amazon FBA Price',
                'amazon_fba_asin' => 'Amazon FBA ASIN',
            ],
            
            'Other Marketplaces' => [
                'onbuy_title' => 'OnBuy Title',
                'onbuy_description' => 'OnBuy Description',
                'onbuy_price' => 'OnBuy Price',
                'onbuy_product_id' => 'OnBuy Product ID',
                'website_title' => 'Website Title',
                'website_description' => 'Website Description', 
                'website_price' => 'Website Price',
            ],
        ];
        
        return $fields;
    }

    public function validateMapping(array $mapping): array
    {
        $errors = [];
        $warnings = [];
        
        $mappedFields = array_values(array_filter($mapping));
        
        // Check for required fields
        $requiredFields = ['product_name', 'variant_sku'];
        foreach ($requiredFields as $field) {
            if (!in_array($field, $mappedFields)) {
                $errors[] = "Required field '{$field}' is not mapped";
            }
        }
        
        // Check for duplicate mappings
        $fieldCounts = array_count_values($mappedFields);
        foreach ($fieldCounts as $field => $count) {
            if ($count > 1 && !empty($field)) {
                $warnings[] = "Field '{$field}' is mapped multiple times";
            }
        }
        
        // Check mapping coverage
        $totalColumns = count($mapping);
        $mappedColumns = count(array_filter($mapping));
        $coveragePercentage = $totalColumns > 0 ? ($mappedColumns / $totalColumns) * 100 : 0;
        
        if ($coveragePercentage < 30) {
            $warnings[] = sprintf('Only %.1f%% of columns are mapped', $coveragePercentage);
        }
        
        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'coverage_percentage' => round($coveragePercentage, 1),
            'mapped_fields_count' => $mappedColumns,
            'total_columns' => $totalColumns,
        ];
    }
}