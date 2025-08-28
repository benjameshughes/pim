<?php

namespace App\Actions\Import;

use App\Actions\Import\ProcessCSVRow;
use App\Actions\Import\CreateOrUpdateProduct;
use App\Actions\Import\CreateOrUpdateVariant;
use App\Actions\Import\AttributeAssignmentAction;
use App\Actions\Import\ExtractParentInfo;
use App\Actions\Import\ExtractDimensions;
use App\Actions\Barcodes\AssignBarcode;
use App\Actions\Pricing\AssignPricing;
use App\Events\Products\ProductImportProgress;
use App\Models\Barcode;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🚀 SIMPLE IMPORT ACTION - No Bloat Edition!
 *
 * Extracts parent SKUs, creates products and variants from CSV data
 * Based on your actual CSV format: "45120RWST-White" → parent "45120RWST" + variant "White"
 */
class SimpleImportAction
{
    private $progressCallback;

    private $createdProducts = 0;

    private $updatedProducts = 0;

    private $createdVariants = 0;

    private $updatedVariants = 0;

    private $skippedRows = 0;

    private $errors = [];

    private $rowData = [];

    private $processedRows = 0;

    private $totalRows = 0;

    private $processedProducts = [];

    /**
     * Process import step by step with clean progress updates
     * Each step processes ALL rows before moving to the next step
     */
    private function processStepByStep(
        array $csv, 
        array $headers, 
        array $mappings, 
        array $adHocAttributes, 
        string $importId, 
        int $totalRows
    ): void {
        $this->totalRows = $totalRows;
        $totalWorkUnits = $totalRows * 6; // 6 processing steps per row
        
        // Step 1: Extract and validate all row data
        ProductImportProgress::dispatch(
            $importId,
            0,
            $totalWorkUnits,
            'extracting_info',
            '📝 Extracting Info',
            'Extracting data from all rows...',
            $this->getCurrentStats()
        );
        
        $this->extractAllRowData($csv, $mappings, $headers);
        
        // Step 2: Create products - loop through all variants
        ProductImportProgress::dispatch($importId, 0, $totalWorkUnits, 'creating_product', '🏭 Creating Products', 'Creating/updating all parent products...', $this->getCurrentStats());
        $createProduct = new CreateOrUpdateProduct();
        $processedProducts = [];
        $currentRow = 0;
        foreach ($this->rowData as &$rowInfo) {
            if (!$rowInfo) continue;
            
            $parentSku = $rowInfo['parentInfo']['parent_sku'];
            if (isset($processedProducts[$parentSku])) {
                $rowInfo['product'] = $processedProducts[$parentSku];
                continue;
            }
            
            // Show current SKU being processed
            $currentProgress = $currentRow;
            ProductImportProgress::dispatch($importId, $currentProgress, $totalWorkUnits, 'creating_product', '🏭 Creating Products', "Creating product for {$parentSku}...", $this->getCurrentStats());
            $currentRow++;
            
            try {
                $product = $createProduct->execute($rowInfo['parentInfo']);
                $processedProducts[$parentSku] = $product;
                $rowInfo['product'] = $product;
                if ($product->wasRecentlyCreated) {
                    $this->createdProducts++;
                } else {
                    $this->updatedProducts++;
                }
            } catch (\Exception $e) {
                $this->skippedRows++;
                $this->errors[] = "Product {$parentSku}: " . $e->getMessage();
                $rowInfo = null;
            }
            
            // Removed delays for production performance
        }
        unset($rowInfo); // Break reference
        // Removed sleep delays
        
        // Step 3: Create variants - loop through all variants
        ProductImportProgress::dispatch($importId, $totalRows, $totalWorkUnits, 'creating_variant', '🎨 Creating Variants', 'Creating/updating all product variants...', $this->getCurrentStats());
        $createVariant = new CreateOrUpdateVariant();
        $currentRow = $totalRows; // Start at 1x totalRows (step 2 complete)
        foreach ($this->rowData as &$rowInfo) {
            if (!$rowInfo || !isset($rowInfo['product'])) continue;
            
            // Show current SKU being processed
            ProductImportProgress::dispatch($importId, $currentRow, $totalWorkUnits, 'creating_variant', '🎨 Creating Variants', "Creating variant for {$rowInfo['data']['sku']}...", $this->getCurrentStats());
            $currentRow++;
            
            try {
                $variant = $createVariant->execute($rowInfo['product'], $rowInfo['data'], $rowInfo['parentInfo']);
                $rowInfo['variant'] = $variant;
                if ($variant->wasRecentlyCreated) {
                    $this->createdVariants++;
                } else {
                    $this->updatedVariants++;
                }
            } catch (\Exception $e) {
                $this->skippedRows++;
                $this->errors[] = "Variant {$rowInfo['data']['sku']}: " . $e->getMessage();
                $rowInfo = null;
            }
            
            // Removed delays for production performance
        }
        unset($rowInfo); // Break reference
        // Removed sleep delays
        
        // Step 4: Assign attributes - loop through all variants
        ProductImportProgress::dispatch($importId, $totalRows * 2, $totalWorkUnits, 'assigning_attributes', '🏷️ Assigning Attributes', 'Assigning attributes to all products and variants...', $this->getCurrentStats());
        $assignAttributes = new AttributeAssignmentAction();
        $currentRow = $totalRows * 2; // Start at 2x totalRows (steps 1&2 complete)
        foreach ($this->rowData as $rowInfo) {
            if (!$rowInfo || !isset($rowInfo['product'], $rowInfo['variant'])) continue;
            
            // Show current SKU being processed
            ProductImportProgress::dispatch($importId, $currentRow, $totalWorkUnits, 'assigning_attributes', '🏷️ Assigning Attributes', "Assigning attributes for {$rowInfo['data']['sku']}...", $this->getCurrentStats());
            $currentRow++;
            
            try {
                $assignAttributes->execute($rowInfo['product'], $rowInfo['variant'], $rowInfo['row'], $mappings, $adHocAttributes, $headers);
            } catch (\Exception $e) {
                $this->errors[] = "Attributes for {$rowInfo['data']['sku']}: " . $e->getMessage();
            }
            
            // Removed delays for production performance
        }
        unset($rowInfo); // Break reference
        // Removed sleep delays
        
        // Step 5: Assign barcodes - simple loop through variants with CSV data
        ProductImportProgress::dispatch($importId, $totalRows * 3, $totalWorkUnits, 'assigning_barcode', '📱 Assigning Barcodes', 'Assigning barcodes to all variants...', $this->getCurrentStats());
        $assignBarcode = new AssignBarcode();
        $currentRow = $totalRows * 3;
        
        foreach ($this->rowData as $rowInfo) {
            if (!$rowInfo || !isset($rowInfo['variant'])) continue;
            
            $variant = $rowInfo['variant'];
            $csvBarcode = $rowInfo['data']['barcode'] ?? null;
            
            ProductImportProgress::dispatch($importId, $currentRow, $totalWorkUnits, 'assigning_barcode', '📱 Assigning Barcodes', "Assigning barcode for {$variant->sku}...", $this->getCurrentStats());
            $currentRow++;
            
            try {
                $assignBarcode->execute($variant, $csvBarcode);
            } catch (\Exception $e) {
                $this->errors[] = "Barcode for {$variant->sku}: " . $e->getMessage();
            }
            
            usleep(300000);
        }
        // Removed sleep delays
        
        // Step 6: Assign pricing - loop through all variants
        ProductImportProgress::dispatch($importId, $totalRows * 4, $totalWorkUnits, 'assigning_pricing', '💰 Setting Prices', 'Setting retail prices for all variants...', $this->getCurrentStats());
        $assignPricing = new AssignPricing();
        $currentRow = $totalRows * 4; // Start at 4x totalRows (steps 1,2,3,4 complete)
        foreach ($this->rowData as $rowInfo) {
            if (!$rowInfo || !isset($rowInfo['variant'])) continue;
            
            // Show current SKU being processed
            ProductImportProgress::dispatch($importId, $currentRow, $totalWorkUnits, 'assigning_pricing', '💰 Setting Prices', "Setting retail price for {$rowInfo['data']['sku']}...", $this->getCurrentStats());
            $currentRow++;
            
            if (!empty($rowInfo['data']['price'])) {
                $price = $this->parsePrice($rowInfo['data']['price']);
                if ($price && $price > 0) {
                    try {
                        $assignPricing->execute($rowInfo['variant'], $price);
                    } catch (\Exception $e) {
                        $this->errors[] = "Pricing for {$rowInfo['data']['sku']}: " . $e->getMessage();
                    }
                }
            }
            
            // Removed delays for production performance
        }
        unset($rowInfo); // Break reference
        // Removed sleep delays
        
        // Step 7: Finishing up
        ProductImportProgress::dispatch($importId, $totalRows * 5, $totalWorkUnits, 'finishing', '✨ Finishing Up', 'Finalizing import and cleaning up...', $this->getCurrentStats());
        sleep(2);
    }

    public function execute(array $config): array
    {
        $filePath = $config['file'];
        $mappings = $config['mappings'];
        $adHocAttributes = $config['ad_hoc_attributes'] ?? [];
        $this->progressCallback = $config['progressCallback'] ?? null;
        $importId = $config['importId'] ?? uniqid('import_');

        Log::info('🚀 Starting simple product import', [
            'file' => basename($filePath),
            'mappings' => $mappings,
            'importId' => $importId,
        ]);

        $startTime = microtime(true);

        try {
            return DB::transaction(function () use ($filePath, $mappings, $adHocAttributes, $startTime, $importId) {
                // Read and process CSV
                $csv = array_map(function ($line) {
                    return str_getcsv($line, ',', '"', '\\');
                }, file($filePath));
                $headers = array_shift($csv); // Remove header row

                $totalRows = count($csv);

                // Process step by step with clean progress updates
                $this->processStepByStep($csv, $headers, $mappings, $adHocAttributes, $importId, $totalRows);

                $duration = microtime(true) - $startTime;

                $totalWorkUnits = $totalRows * 6; // 6 processing steps per row
                
                // Broadcast completion
                ProductImportProgress::dispatch(
                    $importId,
                    $totalWorkUnits,
                    $totalWorkUnits,
                    'completed',
                    'Import completed successfully!',
                    "Processed {$totalRows} rows in " . round($duration, 2) . "s",
                    $this->getCurrentStats()
                );

                Log::info('✅ Simple import completed', [
                    'products_created' => $this->createdProducts,
                    'products_updated' => $this->updatedProducts,
                    'variants_created' => $this->createdVariants,
                    'variants_updated' => $this->updatedVariants,
                    'skipped_rows' => $this->skippedRows,
                    'duration_seconds' => round($duration, 2),
                ]);

                return [
                    'success' => true,
                    'message' => 'Import completed successfully',
                    'created_products' => $this->createdProducts,
                    'updated_products' => $this->updatedProducts,
                    'created_variants' => $this->createdVariants,
                    'updated_variants' => $this->updatedVariants,
                    'skipped_rows' => $this->skippedRows,
                    'total_processed' => $this->createdProducts + $this->updatedProducts + $this->createdVariants + $this->updatedVariants,
                    'duration' => round($duration, 2),
                    'errors' => $this->errors,
                ];
            });

        } catch (\Exception $e) {
            // Broadcast error
            ProductImportProgress::dispatch(
                $importId ?? uniqid('import_'),
                0,
                0,
                'error',
                'Import failed',
                $e->getMessage()
            );

            Log::error('💥 Simple import failed', [
                'error' => $e->getMessage(),
                'file' => basename($filePath),
            ]);

            throw $e;
        }
    }

    /**
     * Update statistics based on processing result
     */
    private function updateStats(array $result): void
    {
        if (!$result['success']) {
            if ($result['action'] === 'skipped') {
                $this->skippedRows++;
            } else {
                $this->errors[] = $result['error'] ?? 'Unknown error';
                $this->skippedRows++;
            }
            return;
        }

        if ($result['product_created'] ?? false) {
            $this->createdProducts++;
        } else {
            $this->updatedProducts++;
        }

        if ($result['variant_created'] ?? false) {
            $this->createdVariants++;
        } else {
            $this->updatedVariants++;
        }
    }

    /**
     * Get current statistics for broadcasting
     */
    private function getCurrentStats(): array
    {
        return [
            'products_created' => $this->createdProducts,
            'products_updated' => $this->updatedProducts,
            'variants_created' => $this->createdVariants,
            'variants_updated' => $this->updatedVariants,
            'errors' => count($this->errors),
            'skipped_rows' => $this->skippedRows,
        ];
    }

    /**
     * Get display name for action
     */
    private function getActionDisplayName(string $action): string
    {
        return match($action) {
            'extracting_info' => '📝 Extracting Info',
            'creating_product' => '🏭 Creating Product',
            'creating_variant' => '🎨 Creating Variant',
            'assigning_attributes' => '🏷️ Assigning Attributes',
            'assigning_barcode' => '📱 Assigning Barcode',
            'assigning_pricing' => '💰 Setting Price',
            'processing' => '⚙️ Processing',
            'reading_file' => '📂 Reading File',
            'completed' => '✅ Completed',
            'error' => '❌ Error',
            default => '🔄 ' . ucfirst(str_replace('_', ' ', $action)),
        };
    }



    /**
     * Extract and validate data from all CSV rows
     */
    private function extractAllRowData(array $csv, array $mappings, array $headers): void
    {
        $extractParentInfo = new ExtractParentInfo(new ExtractDimensions());
        
        foreach ($csv as $index => $row) {
            // Extract data from row using mappings
            $data = $this->extractRowData($row, $mappings);
            
            
            if (!$data['sku'] || !$data['title']) {
                $this->skippedRows++;
                $this->errors[] = "Row " . ($index + 1) . ": Missing required SKU or title";
                continue;
            }
            
            try {
                // Extract parent SKU and product info
                $parentInfo = $extractParentInfo->execute($data);
                
                $this->rowData[] = [
                    'index' => $index,
                    'row' => $row,
                    'data' => $data,
                    'parentInfo' => $parentInfo
                ];
            } catch (\Exception $e) {
                $this->skippedRows++;
                $this->errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
            }
        }
    }


    /**
     * Extract data from CSV row using column mappings
     */
    private function extractRowData(array $row, array $mappings): array
    {
        $data = [];

        foreach ($mappings as $field => $columnIndex) {
            $data[$field] = ($columnIndex !== '') ? ($row[$columnIndex] ?? '') : '';
        }

        return $data;
    }

    /**
     * Parse price from string (handles various formats)
     */
    private function parsePrice(?string $priceString): ?float
    {
        if (empty($priceString)) {
            return null;
        }

        // Remove currency symbols and extract numeric value
        $cleaned = preg_replace('/[^\d.,]/', '', $priceString);
        $cleaned = str_replace(',', '.', $cleaned);

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

}
