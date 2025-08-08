<?php

namespace App\Actions\Import;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductAttributeExtractorV2;

class CreateProductVariant
{
    public function execute(array $variantData, Product $parent): ProductVariant
    {
        // Extract attributes using improved V2 extractor
        $extractedAttributes = ProductAttributeExtractorV2::extractAttributes($variantData['product_name'] ?? '');

        // Create variant with basic data (no direct color/width/drop columns)
        $variant = ProductVariant::create([
            'product_id' => $parent->id,
            'sku' => $variantData['variant_sku'] ?? null,
            'stock_level' => $variantData['stock_quantity'] ?? 0,
            'package_weight' => $variantData['weight'] ?? null,
            'package_length' => $variantData['length'] ?? null,
            'package_width' => $variantData['package_width'] ?? null,
            'package_height' => $variantData['height'] ?? null,
            'status' => 'active',
        ]);

        // Prepare attribute data combining import data with extracted attributes
        $attributeData = [
            'color' => $variantData['variant_color'] ?? $extractedAttributes['color'] ?? null,
            'width' => $this->cleanDimensionValue($variantData['variant_width'] ?? $extractedAttributes['width'] ?? null),
            'drop' => $this->cleanDimensionValue($variantData['variant_drop'] ?? $extractedAttributes['drop'] ?? null),
        ];

        // Handle variant attributes using the attribute system
        app(HandleVariantAttributes::class)->execute($variant, $attributeData);

        // Handle barcode assignment
        if (! empty($variantData['barcode'])) {
            app(AssignVariantBarcode::class)->execute($variant, $variantData['barcode']);
        }

        // Handle pricing
        if (! empty($variantData['retail_price'])) {
            app(CreateVariantPricing::class)->execute($variant, $variantData);
        }

        return $variant;
    }

    /**
     * Clean dimension values by removing units and converting to numeric
     * e.g., "45cm" becomes "45", "150.5cm" becomes "150.5"
     */
    private function cleanDimensionValue(?string $value): ?float
    {
        if (! $value) {
            return null;
        }

        // Extract numeric part from dimension values like "45cm", "150.5mm", etc.
        if (preg_match('/^(\d+(?:\.\d+)?)/', $value, $matches)) {
            return (float) $matches[1];
        }

        // If it's already numeric, return as float
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }
}
