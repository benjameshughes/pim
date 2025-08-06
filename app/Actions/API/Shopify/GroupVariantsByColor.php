<?php

namespace App\Actions\API\Shopify;

use App\Models\Product;
use Illuminate\Support\Collection;

class GroupVariantsByColor
{
    /**
     * Group product variants by color attribute for Shopify parent splitting
     * 
     * This creates separate groups for each color so we can create individual
     * Shopify products like "Blackout Blind - Black", "Blackout Blind - White"
     */
    public function execute(Product $product): array
    {
        $colorGroups = [];
        
        foreach ($product->variants as $variant) {
            // Try to get color from direct column first, then attributes
            $color = $variant->color;
            
            if (!$color) {
                $colorAttr = $variant->attributes()->byKey('color')->first();
                $color = $colorAttr ? $colorAttr->attribute_value : null;
            }
            
            // Skip variants without color instead of grouping them as "Default"
            if (!$color) {
                continue;
            }
            
            if (!isset($colorGroups[$color])) {
                $colorGroups[$color] = [];
            }
            
            $colorGroups[$color][] = $variant;
        }
        
        return $colorGroups;
    }
}