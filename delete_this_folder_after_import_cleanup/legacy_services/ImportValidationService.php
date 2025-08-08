<?php

namespace App\Services;

use App\Actions\Import\ValidateImportRow;
use App\DTOs\Import\ImportRequest;
use App\DTOs\Import\ValidationResult;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

class ImportValidationService
{
    public function __construct(
        private ValidateImportRow $validateRow
    ) {}

    /**
     * Validate all import data and return comprehensive results
     */
    public function validateImportData(array $mappedData, ImportRequest $request): ValidationResult
    {
        $result = new ValidationResult;

        $products = [];
        $variants = [];
        $rowNumber = 2; // Start from row 2 (after headers)

        foreach ($mappedData as $rowData) {
            // Validate individual row
            $rowValidation = $this->validateRow->execute($rowData, $rowNumber);

            if ($rowValidation->hasErrors()) {
                $result->addErrors($rowValidation->getErrors());
                $result->incrementErrorRows();
            } else {
                $result->incrementValidRows();

                // Predict actions for this row
                $this->predictImportActions($rowData, $request, $result, $products, $variants);
            }

            // Add any warnings
            if ($rowValidation->hasWarnings()) {
                $result->addWarnings($rowValidation->getWarnings());
            }

            $rowNumber++;
        }

        // Calculate barcode needs
        $this->calculateBarcodeNeeds($result, $mappedData, $request);

        // Add performance warning if checking many rows
        if (count($mappedData) > 100) {
            $result->addWarning('Only checked first 100 rows for performance. Full validation will occur during import.');
        }

        $result->finalize();

        Log::info('Import validation completed', [
            'valid_rows' => $result->validRows,
            'error_rows' => $result->errorRows,
            'warnings' => count($result->warnings),
            'products_to_create' => $result->productsToCreate,
            'variants_to_create' => $result->variantsToCreate,
        ]);

        return $result;
    }

    /**
     * Predict what actions will be taken for each row
     */
    private function predictImportActions(array $rowData, ImportRequest $request, ValidationResult $result, array &$products, array &$variants): void
    {
        $productName = $rowData['product_name'] ?? '';
        $variantSku = $rowData['variant_sku'] ?? '';

        // Predict product actions
        if (! empty($productName) && ! isset($products[$productName])) {
            $products[$productName] = true;

            $productExists = Product::where('name', $productName)->exists();

            switch ($request->importMode) {
                case 'create_only':
                    if ($productExists) {
                        $result->incrementProductsToSkip();
                    } else {
                        $result->incrementProductsToCreate();
                    }
                    break;

                case 'update_existing':
                    if ($productExists) {
                        $result->incrementProductsToUpdate();
                    } else {
                        $result->incrementProductsToSkip();
                    }
                    break;

                case 'create_or_update':
                    if ($productExists) {
                        $result->incrementProductsToUpdate();
                    } else {
                        $result->incrementProductsToCreate();
                    }
                    break;
            }
        }

        // Predict variant actions
        if (! empty($variantSku) && ! isset($variants[$variantSku])) {
            $variants[$variantSku] = true;

            $variantExists = ProductVariant::where('sku', $variantSku)->exists();

            switch ($request->importMode) {
                case 'create_only':
                    if ($variantExists) {
                        $result->incrementVariantsToSkip();
                    } else {
                        $result->incrementVariantsToCreate();
                    }
                    break;

                case 'update_existing':
                    if ($variantExists) {
                        $result->incrementVariantsToUpdate();
                    } else {
                        $result->incrementVariantsToSkip();
                    }
                    break;

                case 'create_or_update':
                    if ($variantExists) {
                        $result->incrementVariantsToUpdate();
                    } else {
                        $result->incrementVariantsToCreate();
                    }
                    break;
            }
        }
    }

    /**
     * Calculate how many barcodes will be needed
     */
    private function calculateBarcodeNeeds(ValidationResult $result, array $mappedData, ImportRequest $request): void
    {
        if (! $request->autoAssignGS1Barcodes) {
            return;
        }

        $barcodesNeeded = 0;

        foreach ($mappedData as $rowData) {
            // If row has no barcode and we're creating/updating variants
            if (empty($rowData['barcode']) && ! empty($rowData['variant_sku'])) {
                $variantExists = ProductVariant::where('sku', $rowData['variant_sku'])->exists();

                $willCreateOrUpdate = match ($request->importMode) {
                    'create_only' => ! $variantExists,
                    'update_existing' => $variantExists,
                    'create_or_update' => true,
                    default => false
                };

                if ($willCreateOrUpdate) {
                    $barcodesNeeded++;
                }
            }
        }

        $result->setBarcodesNeeded($barcodesNeeded);
    }

    /**
     * Validate import constraints and business rules
     */
    public function validateImportConstraints(array $mappedData): array
    {
        $errors = [];
        $skuCounts = [];
        $productNames = [];

        foreach ($mappedData as $index => $rowData) {
            $rowNum = $index + 2;

            // Check for duplicate SKUs in import data
            if (! empty($rowData['variant_sku'])) {
                $sku = $rowData['variant_sku'];
                if (isset($skuCounts[$sku])) {
                    $errors[] = "Row {$rowNum}: Duplicate SKU '{$sku}' found in import data (also on row ".($skuCounts[$sku] + 2).')';
                } else {
                    $skuCounts[$sku] = $index;
                }
            }

            // Check for missing product names when required
            if (empty($rowData['product_name']) && empty($rowData['parent_name'])) {
                $errors[] = "Row {$rowNum}: Either 'Product Name' or 'Parent Name' is required";
            }

            // Validate color/size combinations for variants
            if (! empty($rowData['product_name']) && ! empty($rowData['variant_color']) && ! empty($rowData['variant_size'])) {
                $comboKey = $rowData['product_name'].'|'.$rowData['variant_color'].'|'.$rowData['variant_size'];
                if (isset($productNames[$comboKey])) {
                    $errors[] = "Row {$rowNum}: Duplicate color/size combination for product '{$rowData['product_name']}'";
                } else {
                    $productNames[$comboKey] = $index;
                }
            }
        }

        return $errors;
    }
}
