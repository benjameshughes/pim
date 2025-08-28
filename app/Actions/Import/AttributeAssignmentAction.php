<?php

namespace App\Actions\Import;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

/**
 * 🏷️ ATTRIBUTE ASSIGNMENT ACTION
 *
 * Handles automatic and ad-hoc attribute assignment during imports
 * - Auto-maps unmapped CSV columns to attributes
 * - Applies user-defined ad-hoc attributes
 * - Respects product/variant hierarchy and fillable rules
 */
class AttributeAssignmentAction
{
    /**
     * Assign attributes to product and/or variant
     *
     * @param Product $product
     * @param ProductVariant|null $variant
     * @param array $csvData Raw CSV data with all columns
     * @param array $mappings Column mappings used for fillable fields
     * @param array $adHocAttributes User-defined key-value pairs
     * @param array $csvHeaders Original CSV headers for column names
     * @return array Results summary
     */
    public function execute(
        Product $product,
        ?ProductVariant $variant,
        array $csvData,
        array $mappings,
        array $adHocAttributes = [],
        array $csvHeaders = []
    ): array {
        $results = [
            'product_attributes' => ['created' => [], 'updated' => [], 'errors' => []],
            'variant_attributes' => ['created' => [], 'updated' => [], 'errors' => []],
        ];

        // 1. Process auto-mapped attributes (unmapped CSV columns)
        $autoAttributes = $this->extractAutoAttributes($csvData, $mappings, $csvHeaders);
        
        // 2. Process ad-hoc attributes
        $processedAdHoc = $this->processAdHocAttributes($adHocAttributes);

        // 3. Merge and dedupe attributes (ad-hoc takes precedence)
        $allAttributes = array_merge($autoAttributes, $processedAdHoc);

        // 4. Assign to product (product-level attributes)
        $productAttributes = $this->filterProductAttributes($allAttributes);
        if (!empty($productAttributes)) {
            $productResults = $product->syncAttributes($productAttributes, ['source' => 'import']);
            $results['product_attributes'] = $productResults;
            
            Log::debug('Assigned product attributes', [
                'product_id' => $product->id,
                'attributes' => array_keys($productAttributes),
                'results' => $productResults
            ]);
        }

        // 5. Assign to variant (variant-level attributes)
        if ($variant) {
            $variantAttributes = $this->filterVariantAttributes($allAttributes);
            if (!empty($variantAttributes)) {
                $variantResults = $variant->syncAttributes($variantAttributes, ['source' => 'import']);
                $results['variant_attributes'] = $variantResults;
                
                Log::debug('Assigned variant attributes', [
                    'variant_id' => $variant->id,
                    'attributes' => array_keys($variantAttributes),
                    'results' => $variantResults
                ]);
            }
        }

        return $results;
    }

    /**
     * Extract auto-attributes from unmapped CSV columns
     */
    private function extractAutoAttributes(array $csvData, array $mappings, array $csvHeaders): array
    {
        $autoAttributes = [];
        $usedColumns = array_values(array_filter($mappings)); // Remove empty mappings

        foreach ($csvData as $columnIndex => $value) {
            // Skip if column is already mapped to a fillable field
            if (in_array($columnIndex, $usedColumns)) {
                continue;
            }

            // Skip empty values
            if (empty($value)) {
                continue;
            }

            // Use header name as attribute key, or fallback to column index
            $attributeKey = isset($csvHeaders[$columnIndex]) 
                ? $this->sanitizeAttributeKey($csvHeaders[$columnIndex])
                : "column_{$columnIndex}";

            $autoAttributes[$attributeKey] = $value;
        }

        return $autoAttributes;
    }

    /**
     * Process and validate ad-hoc attributes
     */
    private function processAdHocAttributes(array $adHocAttributes): array
    {
        $processed = [];

        foreach ($adHocAttributes as $key => $value) {
            // Skip empty keys or values
            if (empty($key) || empty($value)) {
                continue;
            }

            $sanitizedKey = $this->sanitizeAttributeKey($key);
            $processed[$sanitizedKey] = $value;
        }

        return $processed;
    }

    /**
     * Filter attributes that should be assigned to product
     * 
     * Product-level attributes are typically:
     * - Brand, category, manufacturer info
     * - General product metadata
     * - Marketing/SEO data
     */
    private function filterProductAttributes(array $attributes): array
    {
        $productAttributeKeys = [
            'brand', 'category', 'manufacturer', 'supplier', 'type',
            'material', 'collection', 'series', 'model',
            'meta_title', 'meta_description', 'keywords',
            'origin_country', 'warranty', 'care_instructions'
        ];

        return array_intersect_key($attributes, array_flip($productAttributeKeys));
    }

    /**
     * Filter attributes that should be assigned to variant
     * 
     * Variant-level attributes are typically:
     * - Size, color, dimension specifics
     * - SKU-specific data
     * - Pricing and stock details
     */
    private function filterVariantAttributes(array $attributes): array
    {
        $variantAttributeKeys = [
            'color_code', 'size_chart', 'pattern', 'finish', 'texture',
            'weight_kg', 'dimensions', 'fabric_composition', 
            'style_code', 'season', 'gender', 'age_group',
            'barcode_type', 'supplier_sku', 'cost_price'
        ];

        // Also include any attributes not claimed by product-level
        $productAttributes = $this->filterProductAttributes($attributes);
        $remainingAttributes = array_diff_key($attributes, $productAttributes);

        $explicitVariantAttributes = array_intersect_key($attributes, array_flip($variantAttributeKeys));
        
        return array_merge($explicitVariantAttributes, $remainingAttributes);
    }

    /**
     * Sanitize attribute key for database storage
     */
    private function sanitizeAttributeKey(string $key): string
    {
        // Convert to lowercase, replace spaces/special chars with underscores
        $sanitized = strtolower(trim($key));
        $sanitized = preg_replace('/[^a-z0-9]+/', '_', $sanitized);
        $sanitized = trim($sanitized, '_');

        // Ensure it's not empty and not too long
        if (empty($sanitized)) {
            $sanitized = 'unknown_attribute';
        }

        return substr($sanitized, 0, 50); // Limit length
    }
}