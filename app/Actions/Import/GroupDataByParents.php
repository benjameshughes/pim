<?php

namespace App\Actions\Import;

use App\Services\ProductNameGrouping;
use Illuminate\Support\Facades\Log;

class GroupDataByParents
{
    public function execute(array $allData): array
    {
        $parentGroups = [];
        $nameGroupingService = app(ProductNameGrouping::class);
        
        foreach ($allData as $data) {
            $parentKey = $this->getParentKeyForData($data);
            
            if (!isset($parentGroups[$parentKey])) {
                $parentGroups[$parentKey] = [];
            }
            
            $parentGroups[$parentKey][] = $data;
        }
        
        // Use name similarity grouping for better parent matching
        $enhancedGroups = [];
        foreach ($parentGroups as $parentKey => $variants) {
            if (count($variants) > 1) {
                // Group variants with similar names together
                $similarityGroups = $nameGroupingService->groupSimilarNames(
                    array_column($variants, 'product_name')
                );
                
                foreach ($similarityGroups as $groupIndex => $names) {
                    $enhancedKey = $parentKey . '_group_' . $groupIndex;
                    $enhancedGroups[$enhancedKey] = array_filter($variants, function($variant) use ($names) {
                        return in_array($variant['product_name'], $names);
                    });
                }
            } else {
                $enhancedGroups[$parentKey] = $variants;
            }
        }
        
        Log::info("Grouped data by parents", [
            'original_groups' => count($parentGroups),
            'enhanced_groups' => count($enhancedGroups),
            'total_variants' => count($allData)
        ]);
        
        return $enhancedGroups;
    }
    
    private function getParentKeyForData(array $data): string
    {
        // 1. If parent_name is provided, use it
        if (!empty($data['parent_name'])) {
            return 'parent_' . md5(trim($data['parent_name']));
        }
        
        // 2. If variant_sku follows pattern XXX-YYY, use XXX as parent key
        if (!empty($data['variant_sku']) && preg_match('/^(\d{3})-\d{3}$/', $data['variant_sku'], $matches)) {
            return 'sku_prefix_' . $matches[1];
        }
        
        // 3. Use product name, but remove color/size variations
        $productName = $data['product_name'] ?? 'Unknown Product';
        $cleanedName = $this->cleanProductNameForGrouping($productName);
        
        return 'name_' . md5(trim($cleanedName));
    }
    
    private function cleanProductNameForGrouping(string $name): string
    {
        // Remove common size and color patterns
        $patterns = [
            '/\[.*?\]/',  // Remove bracketed content like [White] [Large]
            '/\(.*?\)/',  // Remove parenthetical content
            '/ - .*$/',   // Remove everything after dash
            '/\s+[A-Z]{1,2}$/',  // Remove single/double letter sizes at end
            '/\s+(Small|Medium|Large|XL|XXL|S|M|L)$/i',  // Remove size words
            '/\s+(Red|Blue|Green|White|Black|Yellow|Pink|Purple|Orange|Brown|Grey|Gray)$/i',  // Remove colors
        ];
        
        $cleaned = $name;
        foreach ($patterns as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned);
        }
        
        return trim($cleaned);
    }
}