<?php

namespace App\Actions\Import;

class GuessFieldMapping
{
    private array $fieldPatterns = [
        'product_name' => [
            'name', 'product name', 'title', 'product title', 'product', 'item name', 'description',
            'product_name', 'productname', 'item_name', 'itemname'
        ],
        'variant_sku' => [
            'sku', 'item code', 'product code', 'code', 'item_code', 'product_code', 'variant_sku',
            'variantsku', 'item sku', 'product sku', 'barcode', 'part number', 'part_number'
        ],
        'variant_color' => [
            'color', 'colour', 'variant color', 'variant colour', 'color name', 'colour name',
            'variant_color', 'variantcolor', 'variant_colour', 'variantcolour'
        ],
        'variant_size' => [
            'size', 'variant size', 'size name', 'dimensions', 'variant_size', 'variantsize'
        ],
        'retail_price' => [
            'price', 'retail price', 'selling price', 'sale price', 'unit price', 'cost',
            'retail_price', 'retailprice', 'selling_price', 'sellingprice', 'sale_price',
            'saleprice', 'unit_price', 'unitprice'
        ],
        'cost_price' => [
            'cost price', 'wholesale price', 'purchase price', 'buy price', 'cost_price',
            'costprice', 'wholesale_price', 'wholesaleprice', 'purchase_price', 'purchaseprice'
        ],
        'stock_level' => [
            'stock', 'quantity', 'stock level', 'stock quantity', 'qty', 'inventory',
            'stock_level', 'stocklevel', 'stock_quantity', 'stockquantity'
        ],
        'package_weight' => [
            'weight', 'package weight', 'item weight', 'net weight', 'gross weight',
            'package_weight', 'packageweight', 'item_weight', 'itemweight'
        ],
        'package_length' => [
            'length', 'package length', 'item length', 'l', 'package_length', 'packagelength'
        ],
        'package_width' => [
            'width', 'package width', 'item width', 'w', 'package_width', 'packagewidth'
        ],
        'package_height' => [
            'height', 'package height', 'item height', 'h', 'package_height', 'packageheight'
        ],
        'barcode' => [
            'barcode', 'ean', 'upc', 'gtin', 'isbn', 'barcode number', 'ean13', 'upc-a'
        ],
        'image_urls' => [
            'image', 'images', 'image url', 'image urls', 'photo', 'photos', 'picture',
            'image_url', 'imageurl', 'image_urls', 'imageurls'
        ],
        'description' => [
            'description', 'desc', 'product description', 'item description', 'details',
            'product_description', 'productdescription'
        ],
        'is_parent' => [
            'is parent', 'parent', 'is_parent', 'isparent', 'parent product', 'parent_product'
        ],
        'parent_name' => [
            'parent name', 'parent product name', 'parent title', 'parent_name', 'parentname'
        ],
        'status' => [
            'status', 'product status', 'item status', 'active', 'enabled', 'available'
        ]
    ];

    public function execute(string $header): ?string
    {
        $normalizedHeader = $this->normalizeHeader($header);
        
        foreach ($this->fieldPatterns as $field => $patterns) {
            foreach ($patterns as $pattern) {
                $normalizedPattern = $this->normalizeHeader($pattern);
                
                if ($normalizedHeader === $normalizedPattern) {
                    return $field;
                }
                
                if (str_contains($normalizedHeader, $normalizedPattern) || 
                    str_contains($normalizedPattern, $normalizedHeader)) {
                    return $field;
                }
                
                if ($this->fuzzyMatch($normalizedHeader, $normalizedPattern)) {
                    return $field;
                }
            }
        }
        
        return null;
    }
    
    private function normalizeHeader(string $header): string
    {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $header)));
    }
    
    private function fuzzyMatch(string $header, string $pattern): bool
    {
        $headerWords = explode(' ', $header);
        $patternWords = explode(' ', $pattern);
        
        $matches = 0;
        foreach ($patternWords as $patternWord) {
            foreach ($headerWords as $headerWord) {
                if (levenshtein($headerWord, $patternWord) <= 1 && strlen($patternWord) > 2) {
                    $matches++;
                    break;
                }
            }
        }
        
        return $matches >= count($patternWords) * 0.7;
    }
}