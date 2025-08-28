<?php

namespace App\Actions\Import;

use App\Actions\Import\ProcessCSVRow;
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
                // Broadcast initial status
                ProductImportProgress::dispatch(
                    $importId,
                    0,
                    0,
                    'reading_file',
                    'Reading CSV file...',
                    null
                );

                // Read and process CSV
                $csv = array_map(function ($line) {
                    return str_getcsv($line, ',', '"', '\\');
                }, file($filePath));
                $headers = array_shift($csv); // Remove header row

                $totalRows = count($csv);
                $processed = 0;
                $processRowAction = new ProcessCSVRow();

                // Broadcast file read complete
                ProductImportProgress::dispatch(
                    $importId,
                    0,
                    $totalRows,
                    'processing',
                    'Starting import...',
                    "Processing {$totalRows} rows",
                    $this->getCurrentStats()
                );

                foreach ($csv as $row) {
                    $result = $processRowAction->execute(
                        $row, 
                        $mappings, 
                        $adHocAttributes, 
                        $headers,
                        function ($action, $message) use ($importId, $processed, $totalRows) {
                            ProductImportProgress::dispatch(
                                $importId,
                                $processed,
                                $totalRows,
                                $action,
                                $this->getActionDisplayName($action),
                                $message,
                                $this->getCurrentStats()
                            );
                        }
                    );

                    $this->updateStats($result);
                    $processed++;

                    // Broadcast progress every 10 rows or on completion
                    if ($processed % 10 === 0 || $processed === $totalRows) {
                        ProductImportProgress::dispatch(
                            $importId,
                            $processed,
                            $totalRows,
                            'processing',
                            "Processed {$processed} of {$totalRows} rows",
                            $result['sku'] ?? "Row {$processed}",
                            $this->getCurrentStats()
                        );
                    }

                    // Legacy progress callback
                    if ($this->progressCallback) {
                        call_user_func($this->progressCallback, (int) round(($processed / $totalRows) * 100));
                    }
                }

                $duration = microtime(true) - $startTime;

                // Broadcast completion
                ProductImportProgress::dispatch(
                    $importId,
                    $totalRows,
                    $totalRows,
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

}
