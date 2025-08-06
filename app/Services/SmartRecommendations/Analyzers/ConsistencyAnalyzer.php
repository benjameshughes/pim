<?php

namespace App\Services\SmartRecommendations\Analyzers;

use App\Models\ProductVariant;
use App\Services\SmartRecommendations\Contracts\AnalyzerInterface;
use App\Services\SmartRecommendations\DTOs\Recommendation;
use App\Services\SmartRecommendations\Actions\SuggestMissingAttributesAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConsistencyAnalyzer implements AnalyzerInterface
{
    public function analyze(array $variantIds = []): Collection
    {
        $recommendations = collect();
        
        // Get variants to analyze
        $variants = $this->getVariants($variantIds);
        
        if ($variants->isEmpty()) {
            return $recommendations;
        }

        // Check for naming inconsistencies
        $recommendations = $recommendations->merge($this->analyzeNamingConsistency($variants));
        
        // Check for attribute value inconsistencies
        $recommendations = $recommendations->merge($this->analyzeAttributeConsistency($variants));
        
        // Check for data format inconsistencies
        $recommendations = $recommendations->merge($this->analyzeDataFormatConsistency($variants));

        return $recommendations;
    }

    public function getType(): string
    {
        return 'data_consistency';
    }

    public function getName(): string
    {
        return 'Data Consistency';
    }

    protected function getVariants(array $variantIds): Collection
    {
        $query = ProductVariant::with(['product', 'attributes', 'product.attributes']);
        
        if (!empty($variantIds)) {
            $query->whereIn('id', $variantIds);
        }
        
        return $query->get();
    }

    protected function analyzeNamingConsistency(Collection $variants): Collection
    {
        $recommendations = collect();
        
        // Group variants by product to check naming patterns
        $productGroups = $variants->groupBy('product_id');
        
        foreach ($productGroups as $productId => $productVariants) {
            if ($productVariants->count() < 2) continue; // Need multiple variants to check consistency
            
            $product = $productVariants->first()->product;
            
            // Check for inconsistent color/size patterns in SKUs
            $skuPatterns = $productVariants->map(function($variant) {
                // Extract pattern from SKU (e.g., ABC-123-RED-LARGE -> ABC-123-{color}-{size})
                $sku = $variant->sku;
                $parts = explode('-', $sku);
                
                if (count($parts) >= 3) {
                    // Try to identify color and size parts
                    $colorPart = $this->identifyColorInSku($parts);
                    $sizePart = $this->identifySizeInSku($parts);
                    
                    return [
                        'base' => implode('-', array_slice($parts, 0, -2)), // Assume last 2 parts are color/size
                        'has_color_pattern' => !is_null($colorPart),
                        'has_size_pattern' => !is_null($sizePart),
                        'sku' => $sku,
                    ];
                }
                
                return ['base' => $sku, 'has_color_pattern' => false, 'has_size_pattern' => false, 'sku' => $sku];
            });
            
            $inconsistentSKUs = $skuPatterns->where('has_color_pattern', false)->where('has_size_pattern', false);
            
            if ($inconsistentSKUs->isNotEmpty() && $skuPatterns->where('has_color_pattern', true)->isNotEmpty()) {
                $recommendations->push(new Recommendation(
                    id: 'inconsistent_sku_naming_' . $productId . '_' . now()->timestamp,
                    type: $this->getType(),
                    priority: 'low',
                    title: "Inconsistent SKU Naming for {$product->name}",
                    description: "Some variants don't follow the established SKU naming pattern",
                    affectedCount: $inconsistentSKUs->count(),
                    impactScore: 30, // Low impact but affects data organization
                    effortScore: 40, // Requires manual review and potential SKU changes
                    metadata: [
                        'product_id' => $productId,
                        'product_name' => $product->name,
                        'inconsistent_skus' => $inconsistentSKUs->pluck('sku')->toArray(),
                        'variant_ids' => $productVariants->whereIn('sku', $inconsistentSKUs->pluck('sku'))->pluck('id')->toArray(),
                    ],
                    action: new SuggestMissingAttributesAction('standardize_skus'),
                ));
            }
        }

        return $recommendations;
    }

    protected function analyzeAttributeConsistency(Collection $variants): Collection
    {
        $recommendations = collect();
        
        // Find attributes that have inconsistent capitalization or formatting
        $allAttributes = $variants->flatMap(fn($variant) => $variant->attributes->merge($variant->product->attributes))
            ->groupBy('attribute_key');

        foreach ($allAttributes as $attributeKey => $attributes) {
            $values = $attributes->pluck('attribute_value')->unique();
            
            // Check for case inconsistencies (e.g., "Red", "red", "RED")
            $normalizedValues = $values->map(fn($value) => strtolower(trim($value)))->unique();
            
            if ($values->count() > $normalizedValues->count()) {
                $inconsistentVariants = $variants->filter(function($variant) use ($attributeKey, $values, $normalizedValues) {
                    $variantAttr = $variant->attributes->where('attribute_key', $attributeKey)->first();
                    $productAttr = $variant->product->attributes->where('attribute_key', $attributeKey)->first();
                    $attr = $variantAttr ?? $productAttr;
                    
                    return $attr && $values->contains($attr->attribute_value);
                });

                $recommendations->push(new Recommendation(
                    id: 'inconsistent_attribute_values_' . Str::slug($attributeKey) . '_' . now()->timestamp,
                    type: $this->getType(),
                    priority: 'low',
                    title: "Inconsistent '{$attributeKey}' Values",
                    description: "Same values with different capitalization (e.g., 'Red' vs 'red')",
                    affectedCount: $inconsistentVariants->count(),
                    impactScore: 25, // Low impact but affects data quality
                    effortScore: 20, // Easy to standardize
                    metadata: [
                        'attribute_key' => $attributeKey,
                        'inconsistent_values' => $values->toArray(),
                        'normalized_count' => $normalizedValues->count(),
                        'variant_ids' => $inconsistentVariants->pluck('id')->toArray(),
                    ],
                    action: new SuggestMissingAttributesAction('standardize_values'),
                ));
            }
        }

        return $recommendations;
    }

    protected function analyzeDataFormatConsistency(Collection $variants): Collection
    {
        $recommendations = collect();
        
        // Check for inconsistent dimension formats
        $dimensionAttributes = ['width', 'drop', 'height', 'length', 'size'];
        
        foreach ($dimensionAttributes as $dimensionAttr) {
            $dimensionValues = $variants->flatMap(function($variant) use ($dimensionAttr) {
                $variantAttr = $variant->attributes->where('attribute_key', $dimensionAttr)->first();
                $productAttr = $variant->product->attributes->where('attribute_key', $dimensionAttr)->first();
                $attr = $variantAttr ?? $productAttr;
                
                return $attr ? [$attr->attribute_value] : [];
            });

            if ($dimensionValues->isNotEmpty()) {
                $formats = $dimensionValues->map(function($value) {
                    if (preg_match('/(\d+)\s*(cm|mm|inch|in|")/', $value)) {
                        return 'with_unit';
                    } elseif (is_numeric($value)) {
                        return 'numeric_only';
                    } else {
                        return 'other';
                    }
                })->countBy();

                if ($formats->count() > 1 && $formats->get('with_unit', 0) > 0 && $formats->get('numeric_only', 0) > 0) {
                    $affectedVariants = $variants->filter(function($variant) use ($dimensionAttr) {
                        $variantAttr = $variant->attributes->where('attribute_key', $dimensionAttr)->first();
                        $productAttr = $variant->product->attributes->where('attribute_key', $dimensionAttr)->first();
                        return $variantAttr || $productAttr;
                    });

                    $recommendations->push(new Recommendation(
                        id: 'inconsistent_dimension_format_' . $dimensionAttr . '_' . now()->timestamp,
                        type: $this->getType(),
                        priority: 'medium',
                        title: "Inconsistent {$dimensionAttr} Format",
                        description: "Some dimensions have units (cm, mm) while others don't",
                        affectedCount: $affectedVariants->count(),
                        impactScore: 50, // Medium impact - affects data usability
                        effortScore: 30, // Moderate effort to standardize
                        metadata: [
                            'attribute_key' => $dimensionAttr,
                            'format_distribution' => $formats->toArray(),
                            'variant_ids' => $affectedVariants->pluck('id')->toArray(),
                        ],
                        action: new SuggestMissingAttributesAction('standardize_dimensions'),
                    ));
                }
            }
        }

        return $recommendations;
    }

    protected function identifyColorInSku(array $skuParts): ?string
    {
        $colors = ['red', 'blue', 'green', 'black', 'white', 'grey', 'gray', 'brown', 'beige', 'cream'];
        
        foreach ($skuParts as $part) {
            if (in_array(strtolower($part), $colors)) {
                return $part;
            }
        }
        
        return null;
    }

    protected function identifySizeInSku(array $skuParts): ?string
    {
        $sizes = ['xs', 'small', 'medium', 'large', 'xl', 'xxl', 's', 'm', 'l'];
        
        foreach ($skuParts as $part) {
            if (in_array(strtolower($part), $sizes) || preg_match('/^\d+$/', $part)) {
                return $part;
            }
        }
        
        return null;
    }
}