<?php

namespace App\Actions\API\Shopify;

use App\Models\Product;
use App\Models\ProductVariant;

class BuildShopifyProductData
{
    /**
     * Build complete Shopify product data structure for a color group
     */
    public function execute(Product $product, string $color, array $variants): array
    {
        // Generate color-specific product title
        $productTitle = $color === 'Default' 
            ? $product->name 
            : $product->name . ' - ' . $color;

        // Determine product options based on variant attributes (excluding color)
        $options = $this->determineProductOptions($variants);
        
        // Build the main product data structure
        $productData = [
            'title' => $productTitle,
            'body_html' => $this->generateProductDescription($product, $color, $variants),
            'vendor' => 'BLINDS_OUTLET',
            'product_type' => $this->determineProductType($product),
            'status' => $this->mapStatus($product->status),
            'options' => $options,
            'variants' => $this->buildShopifyVariants($variants, $options),
            'category' => $this->determineProductCategory($product),
            'metafields' => $this->buildProductMetafields($product, $color, $variants)
        ];

        // Add product images if available
        $images = $this->getProductImages($product, $variants);
        if (!empty($images)) {
            $productData['images'] = $images;
        }

        return $productData;
    }

    /**
     * Determine product options based on variant attributes (excluding color)
     * Creates a single 'Size' option combining width/drop for variants with different attribute sets
     */
    private function determineProductOptions(array $variants): array
    {
        $options = [];
        $attributeCombinations = [];

        // Analyze variant attribute patterns
        $hasWidthOnly = false;
        $hasDropOnly = false;
        $hasBoth = false;

        foreach ($variants as $variant) {
            $nonColorAttrs = [];
            foreach ($variant->attributes as $attr) {
                if ($attr->attribute_key !== 'color') {
                    $nonColorAttrs[] = $attr->attribute_key;
                }
            }

            if (count($nonColorAttrs) === 1) {
                if (in_array('width', $nonColorAttrs)) $hasWidthOnly = true;
                if (in_array('drop', $nonColorAttrs)) $hasDropOnly = true;
            } else if (count($nonColorAttrs) > 1) {
                $hasBoth = true;
            }
        }

        // If we have mixed attribute patterns (some width-only, some drop-only), 
        // create a single "Size" option with combined values
        if (($hasWidthOnly && $hasDropOnly) || $hasBoth) {
            $sizeValues = [];
            foreach ($variants as $variant) {
                $sizeValue = $this->buildVariantTitle($variant);
                if ($sizeValue && $sizeValue !== 'Standard') {
                    $sizeValues[] = $sizeValue;
                }
            }
            
            $options[] = [
                'name' => 'Size',
                'values' => array_unique($sizeValues) ?: ['Standard']
            ];
        } else {
            // Standard approach for consistent attribute patterns
            $attributeValues = [];
            foreach ($variants as $variant) {
                foreach ($variant->attributes as $attr) {
                    if ($attr->attribute_key !== 'color') {
                        if (!isset($attributeValues[$attr->attribute_key])) {
                            $attributeValues[$attr->attribute_key] = [];
                        }
                        $attributeValues[$attr->attribute_key][] = $attr->attribute_value;
                    }
                }
            }

            // Convert to Shopify options format (max 3 options)
            $optionIndex = 1;
            foreach ($attributeValues as $key => $values) {
                if ($optionIndex <= 3) {
                    $options[] = [
                        'name' => ucfirst($key),
                        'values' => array_unique($values)
                    ];
                    $optionIndex++;
                }
            }
        }

        // Fallback if no options found
        if (empty($options)) {
            $options[] = [
                'name' => 'Size',
                'values' => ['Standard']
            ];
        }

        return $options;
    }

    /**
     * Build Shopify variants array from Laravel variants
     */
    private function buildShopifyVariants(array $variants, array $options): array
    {
        $shopifyVariants = [];

        foreach ($variants as $variant) {
            $variantTitle = $this->buildVariantTitle($variant);
            
            $shopifyVariant = [
                'title' => $variantTitle,
                'sku' => $variant->sku,
                'inventory_quantity' => $variant->stock_level ?? 0,
                'inventory_management' => 'shopify',
                'inventory_policy' => 'deny'
            ];

            // Add pricing
            $price = $this->getVariantPrice($variant);
            if ($price) {
                $shopifyVariant['price'] = $price['amount'];
            }

            // Map to Shopify options
            $optionIndex = 1;
            foreach ($options as $option) {
                $optionName = strtolower($option['name']);
                
                if ($optionName === 'size') {
                    // For size option, use the variant title as the option value
                    $shopifyVariant['option' . $optionIndex] = $variantTitle ?: 'Standard';
                } else {
                    // For other options, try to find matching attribute
                    $attrValue = null;
                    foreach ($variant->attributes as $attr) {
                        if ($attr->attribute_key === $optionName) {
                            $attrValue = $attr->attribute_value;
                            break;
                        }
                    }
                    
                    $shopifyVariant['option' . $optionIndex] = $attrValue ?: ($option['values'][0] ?? 'Standard');
                }
                $optionIndex++;
            }

            // Add barcode if available
            $barcode = $variant->barcodes()->where('is_primary', true)->first();
            if ($barcode) {
                $shopifyVariant['barcode'] = $barcode->barcode;
            }

            $shopifyVariants[] = $shopifyVariant;
        }

        return $shopifyVariants;
    }

    /**
     * Build variant title from attributes (excluding color)
     */
    private function buildVariantTitle(ProductVariant $variant): string
    {
        $attributes = [];
        
        foreach ($variant->attributes as $attr) {
            if ($attr->attribute_key !== 'color') { // Exclude color since it's in parent title
                $value = $attr->attribute_value;
                if ($attr->attribute_key === 'width' || $attr->attribute_key === 'drop') {
                    $value = str_contains($value, 'cm') ? $value : $value . 'cm';
                }
                $attributes[] = $value;
            }
        }

        return implode(' x ', $attributes) ?: 'Standard';
    }

    /**
     * Generate product description
     */
    private function generateProductDescription(Product $product, string $color, array $variants): string
    {
        $description = $product->description ?? $product->name;
        
        if ($color !== 'Default') {
            $description .= "\n\nColor: " . $color;
        }
        
        $description .= "\n\nHigh-quality blind manufactured to order.";
        $description .= "\n\nAvailable in " . count($variants) . " different sizes.";
        
        return $description;
    }

    /**
     * Get variant pricing
     */
    private function getVariantPrice(ProductVariant $variant): ?array
    {
        $pricing = $variant->pricing()->first();
        
        if ($pricing && $pricing->retail_price > 0) {
            return [
                'amount' => (float)$pricing->retail_price,
                'currency' => $pricing->currency ?? 'GBP'
            ];
        }

        // Fallback pricing logic
        $basePrice = 25.99;
        $width = $variant->attributes()->byKey('width')->first()?->attribute_value;
        if ($width) {
            $widthValue = (float)preg_replace('/[^0-9.]/', '', $width);
            if ($widthValue > 0) {
                $basePrice += ($widthValue * 0.15);
            }
        }

        return [
            'amount' => round($basePrice, 2),
            'currency' => 'GBP'
        ];
    }

    /**
     * Determine product type based on product name
     */
    private function determineProductType(Product $product): string
    {
        $name = strtolower($product->name);
        
        if (str_contains($name, 'blackout')) return 'Blackout Blinds';
        if (str_contains($name, 'roller')) return 'Roller Blinds';
        if (str_contains($name, 'vertical')) return 'Vertical Blinds';
        if (str_contains($name, 'venetian')) return 'Venetian Blinds';
        if (str_contains($name, 'roman')) return 'Roman Blinds';
        
        return 'Blinds';
    }

    /**
     * Map Laravel status to Shopify status
     */
    private function mapStatus(string $status): string
    {
        return match($status) {
            'active' => 'active',
            'inactive' => 'draft',
            'discontinued' => 'archived',
            default => 'draft'
        };
    }

    /**
     * Get product images with fallback logic
     */
    private function getProductImages(Product $product, array $variants): array
    {
        $images = [];

        // Try to get images from variants first
        foreach ($variants as $variant) {
            if ($variant->images && count($variant->images) > 0) {
                foreach ($variant->images as $image) {
                    $images[] = [
                        'src' => url(\Storage::url($image)),
                        'alt' => $variant->sku
                    ];
                }
                break; // Use first variant with images
            }
        }

        // Fallback to product images
        if (empty($images) && $product->images && count($product->images) > 0) {
            foreach ($product->images as $image) {
                $images[] = [
                    'src' => url(\Storage::url($image)),
                    'alt' => $product->name
                ];
            }
        }

        return $images;
    }

    /**
     * Determine Shopify product category using cached taxonomy data
     */
    private function determineProductCategory(Product $product): ?string
    {
        // Use the cached taxonomy to find the best match
        $category = \App\Models\ShopifyTaxonomyCategory::getBestMatchForProduct($product->name);
        
        // For now, use the verified Home & Garden category until we can get full taxonomy
        return 'gid://shopify/TaxonomyCategory/hg'; // Home & Garden (verified category)
    }

    /**
     * Build product metafields for enhanced product data
     */
    private function buildProductMetafields(Product $product, string $color, array $variants): array
    {
        $metafields = [];

        // Get category-specific metafields from taxonomy
        $category = \App\Models\ShopifyTaxonomyCategory::getBestMatchForProduct($product->name);
        if ($category && !empty($category->attributes)) {
            // Add category-specific metafields
            foreach ($category->attributes as $attribute) {
                $key = strtolower(str_replace(' ', '_', $attribute['name']));
                $value = $this->inferAttributeValue($attribute, $product, $color, $variants);
                
                if ($value) {
                    $metafields[] = [
                        'namespace' => 'taxonomy',
                        'key' => $key,
                        'value' => $value,
                        'type' => 'single_line_text_field'
                    ];
                }
            }
        }

        // Add color information if not default
        if ($color !== 'Default') {
            $metafields[] = [
                'namespace' => 'custom',
                'key' => 'primary_color',
                'value' => $color,
                'type' => 'single_line_text_field'
            ];
        }

        // Add dimensions information
        $dimensions = $this->extractDimensions($variants);
        if (!empty($dimensions)) {
            $metafields[] = [
                'namespace' => 'custom',
                'key' => 'available_sizes',
                'value' => implode(', ', $dimensions),
                'type' => 'multi_line_text_field'
            ];
        }

        // Add material/care instructions
        $metafields[] = [
            'namespace' => 'custom',
            'key' => 'care_instructions',
            'value' => 'Dust regularly with a soft cloth. For deeper cleaning, use a vacuum with brush attachment.',
            'type' => 'multi_line_text_field'
        ];

        // Add product features based on type
        $features = $this->extractProductFeatures($product);
        if (!empty($features)) {
            $metafields[] = [
                'namespace' => 'custom',
                'key' => 'key_features',
                'value' => implode(', ', $features),
                'type' => 'multi_line_text_field'
            ];
        }

        // Add installation information
        $metafields[] = [
            'namespace' => 'custom',
            'key' => 'installation_type',
            'value' => 'Inside or outside window recess mounting',
            'type' => 'single_line_text_field'
        ];

        // Add warranty information
        $metafields[] = [
            'namespace' => 'custom',
            'key' => 'warranty',
            'value' => '2 year manufacturer warranty',
            'type' => 'single_line_text_field'
        ];

        return $metafields;
    }

    /**
     * Extract unique dimensions from variants
     */
    private function extractDimensions(array $variants): array
    {
        $dimensions = [];
        
        foreach ($variants as $variant) {
            foreach ($variant->attributes as $attr) {
                if (in_array($attr->attribute_key, ['width', 'drop', 'size'])) {
                    $value = $attr->attribute_value;
                    if (!str_contains($value, 'cm') && is_numeric($value)) {
                        $value .= 'cm';
                    }
                    $dimensions[] = $value;
                }
            }
        }

        return array_unique($dimensions);
    }

    /**
     * Extract product features based on name and type
     */
    private function extractProductFeatures(Product $product): array
    {
        $name = strtolower($product->name);
        $features = [];

        if (str_contains($name, 'blackout')) {
            $features[] = 'Room darkening';
            $features[] = 'Energy efficient';
            $features[] = 'Privacy protection';
        }

        if (str_contains($name, 'thermal')) {
            $features[] = 'Thermal insulation';
            $features[] = 'Energy saving';
        }

        if (str_contains($name, 'waterproof') || str_contains($name, 'bathroom')) {
            $features[] = 'Moisture resistant';
            $features[] = 'Easy to clean';
        }

        if (str_contains($name, 'cordless')) {
            $features[] = 'Child safe';
            $features[] = 'Easy operation';
        }

        // Default features for all blinds
        $features[] = 'Made to measure';
        $features[] = 'UK manufactured';
        $features[] = 'Professional quality';

        return array_unique($features);
    }

    /**
     * Infer attribute value from product data
     */
    private function inferAttributeValue(array $attribute, Product $product, string $color, array $variants): ?string
    {
        $attributeName = strtolower($attribute['name']);
        $productName = strtolower($product->name);
        
        switch ($attributeName) {
            case 'color':
                return $color !== 'Default' ? $color : null;
                
            case 'material':
                // Try to detect material from product name
                $materials = ['fabric', 'wood', 'aluminum', 'vinyl', 'bamboo', 'metal', 'plastic'];
                foreach ($materials as $material) {
                    if (str_contains($productName, $material)) {
                        return ucfirst($material);
                    }
                }
                return 'Fabric'; // Default for blinds
                
            case 'mount type':
                return 'Inside Mount'; // Default assumption
                
            case 'light control':
                if (str_contains($productName, 'blackout')) {
                    return 'Blackout';
                } elseif (str_contains($productName, 'room darkening')) {
                    return 'Room Darkening';
                } elseif (str_contains($productName, 'sheer')) {
                    return 'Sheer';
                }
                return 'Light Filtering'; // Default
                
            case 'operating system':
                if (str_contains($productName, 'cordless')) {
                    return 'Cordless';
                } elseif (str_contains($productName, 'motorized')) {
                    return 'Motorized';
                }
                return 'Corded'; // Default
                
            default:
                return null;
        }
    }
}