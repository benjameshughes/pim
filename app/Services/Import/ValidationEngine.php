<?php

namespace App\Services\Import;

class ValidationEngine
{
    public function validateRow(array $data, int $rowNumber, array $configuration): array
    {
        $errors = [];
        $warnings = [];

        // Required fields validation
        $errors = array_merge($errors, $this->validateRequiredFields($data, $rowNumber));

        // Data type validation
        $errors = array_merge($errors, $this->validateDataTypes($data, $rowNumber));

        // Business logic validation
        $warnings = array_merge($warnings, $this->validateBusinessLogic($data, $rowNumber, $configuration));

        // Constraint validation
        $errors = array_merge($errors, $this->validateConstraints($data, $rowNumber, $configuration));

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'is_valid' => empty($errors),
        ];
    }

    private function validateRequiredFields(array $data, int $rowNumber): array
    {
        $errors = [];
        $requiredFields = ['product_name', 'variant_sku'];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[] = "Row {$rowNumber}: {$field} is required but missing";
            }
        }

        return $errors;
    }

    private function validateDataTypes(array $data, int $rowNumber): array
    {
        $errors = [];

        // Numeric field validation
        $numericFields = [
            'retail_price' => 'Retail Price',
            'cost_price' => 'Cost Price',
            'stock_level' => 'Stock Level',
            'package_length' => 'Package Length',
            'package_width' => 'Package Width',
            'package_height' => 'Package Height',
            'package_weight' => 'Package Weight',
        ];

        foreach ($numericFields as $field => $displayName) {
            if (!empty($data[$field]) && !is_numeric($data[$field])) {
                $errors[] = "Row {$rowNumber}: {$displayName} must be numeric, got '{$data[$field]}'";
            }
        }

        // Price validation
        if (!empty($data['retail_price']) && (float) $data['retail_price'] < 0) {
            $errors[] = "Row {$rowNumber}: Retail Price cannot be negative";
        }

        if (!empty($data['cost_price']) && (float) $data['cost_price'] < 0) {
            $errors[] = "Row {$rowNumber}: Cost Price cannot be negative";
        }

        // Stock validation
        if (!empty($data['stock_level']) && (int) $data['stock_level'] < 0) {
            $errors[] = "Row {$rowNumber}: Stock Level cannot be negative";
        }

        // SKU format validation
        if (!empty($data['variant_sku'])) {
            $sku = $data['variant_sku'];
            if (strlen($sku) < 3 || strlen($sku) > 50) {
                $errors[] = "Row {$rowNumber}: SKU must be between 3 and 50 characters";
            }
            
            if (!preg_match('/^[A-Za-z0-9\-_]+$/', $sku)) {
                $errors[] = "Row {$rowNumber}: SKU contains invalid characters (only letters, numbers, hyphens, and underscores allowed)";
            }
        }

        // Barcode validation
        if (!empty($data['barcode'])) {
            $barcode = $data['barcode'];
            if (!preg_match('/^\d{8,14}$/', $barcode)) {
                $errors[] = "Row {$rowNumber}: Barcode must be 8-14 digits only";
            }
        }

        return $errors;
    }

    private function validateBusinessLogic(array $data, int $rowNumber, array $configuration): array
    {
        $warnings = [];

        // Price consistency checks
        if (!empty($data['retail_price']) && !empty($data['cost_price'])) {
            $retail = (float) $data['retail_price'];
            $cost = (float) $data['cost_price'];
            
            if ($retail <= $cost) {
                $warnings[] = "Row {$rowNumber}: Retail price (£{$retail}) should be higher than cost price (£{$cost})";
            }
            
            $margin = (($retail - $cost) / $retail) * 100;
            if ($margin < 10) {
                $warnings[] = "Row {$rowNumber}: Low profit margin ({$margin}%)";
            }
        }

        // Stock level warnings
        if (!empty($data['stock_level'])) {
            $stock = (int) $data['stock_level'];
            if ($stock === 0) {
                $warnings[] = "Row {$rowNumber}: Zero stock level";
            } elseif ($stock > 10000) {
                $warnings[] = "Row {$rowNumber}: Very high stock level ({$stock}) - please verify";
            }
        }

        // Product name quality checks
        if (!empty($data['product_name'])) {
            $name = $data['product_name'];
            if (strlen($name) < 10) {
                $warnings[] = "Row {$rowNumber}: Product name is very short (might need more detail)";
            }
            
            if (preg_match('/\b(?:test|sample|dummy|lorem)\b/i', $name)) {
                $warnings[] = "Row {$rowNumber}: Product name contains test/placeholder text";
            }
        }

        // Dimension consistency
        $dimensions = ['package_length', 'package_width', 'package_height'];
        $dimensionValues = array_filter(array_intersect_key($data, array_flip($dimensions)));
        
        if (count($dimensionValues) > 1) {
            $values = array_values($dimensionValues);
            $maxDimension = max($values);
            $minDimension = min($values);
            
            if ($maxDimension / $minDimension > 100) {
                $warnings[] = "Row {$rowNumber}: Unusual dimension ratio - please verify measurements";
            }
        }

        return $warnings;
    }

    private function validateConstraints(array $data, int $rowNumber, array $configuration): array
    {
        $errors = [];
        $importMode = $configuration['import_mode'] ?? 'create_or_update';

        // SKU uniqueness constraint (simulate database check)
        if (!empty($data['variant_sku'])) {
            $existingSku = \App\Models\ProductVariant::where('sku', $data['variant_sku'])->exists();
            
            if ($existingSku && $importMode === 'create_only') {
                // This would be skipped, not an error in create_only mode
            } elseif (!$existingSku && $importMode === 'update_existing') {
                // This would be skipped, not an error in update_existing mode
            }
        }

        // Barcode uniqueness constraint
        if (!empty($data['barcode'])) {
            // Check if barcode format is valid for automatic type detection
            $barcode = $data['barcode'];
            $detectedType = $this->detectBarcodeType($barcode);
            
            if (!$detectedType) {
                $errors[] = "Row {$rowNumber}: Could not detect barcode type for '{$barcode}'";
            }
        }

        // Product name length constraint
        if (!empty($data['product_name']) && strlen($data['product_name']) > 255) {
            $errors[] = "Row {$rowNumber}: Product name too long (max 255 characters)";
        }

        // Description length constraint
        if (!empty($data['description']) && strlen($data['description']) > 2000) {
            $errors[] = "Row {$rowNumber}: Description too long (max 2000 characters)";
        }

        return $errors;
    }

    private function detectBarcodeType(string $barcode): ?string
    {
        $barcode = preg_replace('/[^0-9]/', '', $barcode);
        $length = strlen($barcode);
        
        return match ($length) {
            8 => 'EAN8',
            12 => 'UPC',
            13 => 'EAN13',
            14 => 'GTIN14',
            default => null,
        };
    }

    public function validateColumnMapping(array $mapping, array $availableFields): array
    {
        $errors = [];
        $warnings = [];

        // Check for required field mappings
        $requiredFields = ['product_name', 'variant_sku'];
        $mappedFields = array_values(array_filter($mapping));

        foreach ($requiredFields as $field) {
            if (!in_array($field, $mappedFields)) {
                $errors[] = "Required field '{$field}' is not mapped to any column";
            }
        }

        // Check for duplicate mappings
        $fieldCounts = array_count_values($mappedFields);
        foreach ($fieldCounts as $field => $count) {
            if ($count > 1 && !empty($field)) {
                $warnings[] = "Field '{$field}' is mapped to {$count} columns - only the first will be used";
            }
        }

        // Check for unmapped important fields
        $importantFields = ['retail_price', 'cost_price', 'stock_level', 'description'];
        $unmappedImportant = array_diff($importantFields, $mappedFields);
        
        if (!empty($unmappedImportant)) {
            $warnings[] = 'Consider mapping these useful fields: ' . implode(', ', $unmappedImportant);
        }

        // Calculate mapping coverage
        $totalColumns = count($mapping);
        $mappedColumns = count(array_filter($mapping));
        $coveragePercentage = $totalColumns > 0 ? ($mappedColumns / $totalColumns) * 100 : 0;

        if ($coveragePercentage < 30) {
            $warnings[] = sprintf('Only %.1f%% of columns are mapped - consider mapping more fields', $coveragePercentage);
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'is_valid' => empty($errors),
            'coverage_percentage' => round($coveragePercentage, 1),
            'mapped_columns' => $mappedColumns,
            'total_columns' => $totalColumns,
        ];
    }
}