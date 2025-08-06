<?php

namespace App\Actions\Import;

use App\Models\BarcodePool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use App\Exceptions\ImportException;

class ImportBarcodePoolAction
{
    public function execute(
        string $filePath,
        string $barcodeType = 'EAN13',
        ?int $legacyThreshold = null,
        array $options = []
    ): array {
        $batchId = Str::uuid()->toString();
        
        // Set default options
        $options = array_merge([
            'clear_existing' => false,
            'validate_format' => true,
            'chunk_size' => 1000,
            'legacy_notes' => 'Imported from GS1 spreadsheet - legacy archive',
        ], $options);

        DB::beginTransaction();
        
        try {
            // Clear existing pool if requested
            if ($options['clear_existing']) {
                $this->clearExistingPool();
            }

            // Extract barcodes from file
            $barcodes = $this->extractBarcodesFromFile($filePath);
            
            // Validate barcodes if requested
            if ($options['validate_format']) {
                $barcodes = $this->validateBarcodes($barcodes, $barcodeType);
            }

            // Remove duplicates and existing barcodes
            $barcodes = $this->deduplicate($barcodes);

            // Apply Clean Slate Strategy
            $importResults = $this->applyCleanSlateStrategy(
                $barcodes, 
                $barcodeType, 
                $legacyThreshold, 
                $batchId,
                $options
            );

            DB::commit();

            return [
                'success' => true,
                'batch_id' => $batchId,
                'total_processed' => count($barcodes),
                'results' => $importResults,
                'summary' => $this->generateSummary($importResults),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw new ImportException("Barcode pool import failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Process file using chunked import for memory efficiency
     */
    public function executeChunked(
        string $filePath,
        string $barcodeType = 'EAN13',
        ?int $legacyThreshold = null,
        array $options = []
    ): array {
        $batchId = Str::uuid()->toString();
        
        // Set default options
        $options = array_merge([
            'clear_existing' => false,
            'validate_format' => true,
            'chunk_size' => 1000,
            'legacy_notes' => 'Imported from GS1 spreadsheet - legacy archive',
        ], $options);

        DB::beginTransaction();
        
        try {
            // Clear existing pool if requested
            if ($options['clear_existing']) {
                $this->clearExistingPool();
            }

            // Process file in chunks using Laravel Excel
            $results = [
                'legacy_archived' => 0,
                'available' => 0,
                'errors' => [],
                'total_processed' => 0,
            ];

            $this->processFileInChunks($filePath, $batchId, $barcodeType, $legacyThreshold, $options, $results);

            DB::commit();

            return [
                'success' => true,
                'batch_id' => $batchId,
                'total_processed' => $results['total_processed'],
                'results' => $results,
                'summary' => $this->generateSummary($results),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw new ImportException("Barcode pool import failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Process file in memory-efficient chunks
     */
    private function processFileInChunks(
        string $filePath,
        string $batchId,
        string $barcodeType,
        ?int $legacyThreshold,
        array $options,
        array &$results
    ): void {
        Excel::import(new class($batchId, $barcodeType, $legacyThreshold, $options, $results) implements \Maatwebsite\Excel\Concerns\ToCollection, \Maatwebsite\Excel\Concerns\WithChunkReading {
            
            private $batchId;
            private $barcodeType;
            private $legacyThreshold;
            private $options;
            private $results;
            private $headerSkipped = false;

            public function __construct($batchId, $barcodeType, $legacyThreshold, $options, &$results)
            {
                $this->batchId = $batchId;
                $this->barcodeType = $barcodeType;
                $this->legacyThreshold = $legacyThreshold;
                $this->options = $options;
                $this->results = &$results;
            }

            public function collection(\Illuminate\Support\Collection $collection)
            {
                $insertData = [];
                $barcodesToCheck = [];
                
                // First pass: collect all barcodes from this chunk
                foreach ($collection as $row) {
                    // Skip header row on first chunk
                    if (!$this->headerSkipped && $this->isHeaderRow($row->toArray())) {
                        $this->headerSkipped = true;
                        continue;
                    }
                    
                    if (!empty($row[0])) {
                        $barcode = trim((string) $row[0]);
                        if (!empty($barcode)) {
                            $barcodesToCheck[] = $barcode;
                        }
                    }
                }

                // Batch check for existing barcodes (single query instead of 1000)
                $existingBarcodes = BarcodePool::whereIn('barcode', $barcodesToCheck)
                    ->pluck('barcode')
                    ->toArray();

                // Second pass: process rows and build insert data
                foreach ($collection as $row) {
                    if (!empty($row[0])) {
                        $barcode = trim((string) $row[0]);
                        if (!empty($barcode) && !in_array($barcode, $existingBarcodes)) {
                            // Enhanced CSV format
                            $barcodeData = [
                                'barcode' => $barcode,
                                'barcode_type' => $row[1] ?? $this->barcodeType,
                                'status' => $row[2] ?? 'available',
                                'legacy_sku' => $row[3] ?? '',
                                'legacy_status' => $row[4] ?? '',
                                'legacy_product_name' => $row[5] ?? '',
                                'legacy_brand' => $row[6] ?? '',
                                'legacy_updated' => $row[7] ?? '',
                                'import_batch_id' => $row[8] ?: $this->batchId,
                                'notes' => $row[9] ?? '',
                            ];

                            $status = $barcodeData['status'];
                            $isLegacy = $status === 'legacy_archive';

                            $insertData[] = [
                                'barcode' => $barcode,
                                'barcode_type' => $barcodeData['barcode_type'],
                                'status' => $status,
                                'is_legacy' => $isLegacy,
                                'import_batch_id' => $this->batchId,
                                'legacy_notes' => $this->buildLegacyNotes($barcodeData),
                                'notes' => $barcodeData['notes'] ?: null,
                                'assigned_to_variant_id' => null,
                                'assigned_at' => null,
                                'date_first_used' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            if ($isLegacy) {
                                $this->results['legacy_archived']++;
                            } else {
                                $this->results['available']++;
                            }
                            
                            $this->results['total_processed']++;
                        }
                    }
                }

                // Batch insert for performance
                if (!empty($insertData)) {
                    BarcodePool::insert($insertData);
                }
            }

            public function chunkSize(): int
            {
                return 1000; // Process 1000 rows at a time
            }

            private function isHeaderRow(array $row): bool
            {
                $firstCell = strtolower(trim((string) ($row[0] ?? '')));
                return in_array($firstCell, ['barcode', 'code', 'gtin', 'ean', 'upc', 'sku']);
            }

            private function buildLegacyNotes(array $barcodeData): ?string
            {
                $notes = [];
                
                if (!empty($barcodeData['legacy_sku'])) {
                    $notes[] = "Legacy SKU: {$barcodeData['legacy_sku']}";
                }
                
                if (!empty($barcodeData['legacy_product_name'])) {
                    $notes[] = "Product: {$barcodeData['legacy_product_name']}";
                }
                
                if (!empty($barcodeData['legacy_brand'])) {
                    $notes[] = "Brand: {$barcodeData['legacy_brand']}";
                }
                
                if (!empty($barcodeData['legacy_updated'])) {
                    $notes[] = "Last Updated: {$barcodeData['legacy_updated']}";
                }
                
                if (!empty($barcodeData['legacy_status'])) {
                    $notes[] = "Original Status: {$barcodeData['legacy_status']}";
                }
                
                return !empty($notes) ? implode(' | ', $notes) : null;
            }

        }, $filePath);
    }

    /**
     * Extract barcodes from various file formats (legacy method for simple imports)
     */
    private function extractBarcodesFromFile(string $filePath): array
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $barcodes = [];

        switch (strtolower($extension)) {
            case 'xlsx':
            case 'xls':
            case 'csv':
                $data = Excel::toArray(null, $filePath)[0];
                foreach ($data as $index => $row) {
                    // Skip header row
                    if ($index === 0 && $this->isHeaderRow($row)) {
                        continue;
                    }
                    
                    if (is_array($row) && !empty($row[0])) {
                        $barcode = trim((string) $row[0]);
                        if (!empty($barcode)) {
                            // Enhanced CSV format - capture additional fields
                            $barcodes[] = [
                                'barcode' => $barcode,
                                'barcode_type' => $row[1] ?? 'EAN13',
                                'status' => $row[2] ?? 'available',
                                'legacy_sku' => $row[3] ?? '',
                                'legacy_status' => $row[4] ?? '',
                                'legacy_product_name' => $row[5] ?? '',
                                'legacy_brand' => $row[6] ?? '',
                                'legacy_updated' => $row[7] ?? '',
                                'import_batch_id' => $row[8] ?? '',
                                'notes' => $row[9] ?? '',
                            ];
                        }
                    }
                }
                break;

            case 'txt':
                $content = file_get_contents($filePath);
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $barcode = trim($line);
                    if (!empty($barcode)) {
                        $barcodes[] = $barcode;
                    }
                }
                break;

            default:
                throw new ImportException("Unsupported file format: {$extension}");
        }

        if (empty($barcodes)) {
            throw new ImportException("No valid barcodes found in file");
        }

        return $barcodes;
    }

    /**
     * Check if row is likely a header row
     */
    private function isHeaderRow(array $row): bool
    {
        $firstCell = strtolower(trim((string) ($row[0] ?? '')));
        $headerIndicators = ['barcode', 'code', 'gtin', 'ean', 'upc', 'sku'];
        
        return in_array($firstCell, $headerIndicators);
    }

    /**
     * Validate barcode formats
     */
    private function validateBarcodes(array $barcodes, string $barcodeType): array
    {
        $validBarcodes = [];
        $patterns = [
            'EAN13' => '/^\d{13}$/',
            'EAN8' => '/^\d{8}$/',
            'UPC' => '/^\d{12}$/',
            'CODE128' => '/^[\x00-\x7F]+$/',
            'CODE39' => '/^[A-Z0-9\-\.\$\/\+\%\s]+$/',
            'CODABAR' => '/^[A-D][0-9\-\$\:\/\.\+]+[A-D]$/i',
        ];
        
        foreach ($barcodes as $barcodeData) {
            $barcode = is_array($barcodeData) ? $barcodeData['barcode'] : $barcodeData;
            $type = is_array($barcodeData) ? $barcodeData['barcode_type'] : $barcodeType;
            $pattern = $patterns[$type] ?? null;
            
            if ($pattern) {
                if (preg_match($pattern, $barcode)) {
                    $validBarcodes[] = $barcodeData;
                }
            } else {
                // No validation pattern, accept all
                $validBarcodes[] = $barcodeData;
            }
        }

        return $validBarcodes;
    }

    /**
     * Remove duplicates and existing barcodes
     */
    private function deduplicate(array $barcodes): array
    {
        // Extract just barcode values for duplicate checking
        $barcodeValues = [];
        $barcodeMap = [];
        
        foreach ($barcodes as $barcodeData) {
            $barcode = is_array($barcodeData) ? $barcodeData['barcode'] : $barcodeData;
            $barcodeValues[] = $barcode;
            $barcodeMap[$barcode] = $barcodeData;
        }

        // Remove duplicates from input
        $uniqueBarcodeValues = array_unique($barcodeValues);

        // Remove barcodes that already exist in the pool
        $existing = BarcodePool::whereIn('barcode', $uniqueBarcodeValues)
            ->pluck('barcode')
            ->toArray();

        $newBarcodeValues = array_diff($uniqueBarcodeValues, $existing);
        
        // Return the full barcode data for new barcodes only
        return array_map(function($barcode) use ($barcodeMap) {
            return $barcodeMap[$barcode];
        }, $newBarcodeValues);
    }

    /**
     * Apply Clean Slate Strategy to imported barcodes
     */
    private function applyCleanSlateStrategy(
        array $barcodes,
        string $barcodeType,
        ?int $legacyThreshold,
        string $batchId,
        array $options
    ): array {
        $results = [
            'legacy_archived' => 0,
            'available' => 0,
            'errors' => [],
        ];

        // Sort barcodes numerically if they're numeric
        if ($this->areBarcodesNumeric($barcodes)) {
            sort($barcodes, SORT_NUMERIC);
        } else {
            sort($barcodes);
        }

        // Process in chunks for better performance
        $chunks = array_chunk($barcodes, $options['chunk_size']);
        
        foreach ($chunks as $chunk) {
            $this->processChunk($chunk, $barcodeType, $legacyThreshold, $batchId, $options, $results);
        }

        return $results;
    }

    /**
     * Check if barcodes are numeric for proper sorting
     */
    private function areBarcodesNumeric(array $barcodes): bool
    {
        $sample = array_slice($barcodes, 0, min(10, count($barcodes)));
        foreach ($sample as $barcode) {
            if (!is_numeric($barcode)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Process a chunk of barcodes
     */
    private function processChunk(
        array $chunk,
        string $barcodeType,
        ?int $legacyThreshold,
        string $batchId,
        array $options,
        array &$results
    ): void {
        $insertData = [];

        foreach ($chunk as $barcodeData) {
            try {
                $barcode = is_array($barcodeData) ? $barcodeData['barcode'] : $barcodeData;
                $isLegacy = $this->shouldMarkAsLegacy($barcode, $legacyThreshold);
                
                // Use enhanced data if available, otherwise fall back to defaults
                if (is_array($barcodeData)) {
                    $insertData[] = [
                        'barcode' => $barcode,
                        'barcode_type' => $barcodeData['barcode_type'] ?? $barcodeType,
                        'status' => $barcodeData['status'] ?? ($isLegacy ? 'legacy_archive' : 'available'),
                        'is_legacy' => ($barcodeData['status'] ?? '') === 'legacy_archive',
                        'import_batch_id' => $barcodeData['import_batch_id'] ?: $batchId,
                        'legacy_notes' => $this->buildLegacyNotes($barcodeData),
                        'notes' => $barcodeData['notes'] ?: null,
                        'assigned_to_variant_id' => null,
                        'assigned_at' => null,
                        'date_first_used' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                } else {
                    // Simple barcode format (backwards compatibility)
                    $insertData[] = [
                        'barcode' => $barcode,
                        'barcode_type' => $barcodeType,
                        'status' => $isLegacy ? 'legacy_archive' : 'available',
                        'is_legacy' => $isLegacy,
                        'import_batch_id' => $batchId,
                        'legacy_notes' => $isLegacy ? $options['legacy_notes'] : null,
                        'notes' => null,
                        'assigned_to_variant_id' => null,
                        'assigned_at' => null,
                        'date_first_used' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $status = is_array($barcodeData) ? ($barcodeData['status'] ?? 'available') : ($isLegacy ? 'legacy_archive' : 'available');
                if ($status === 'legacy_archive') {
                    $results['legacy_archived']++;
                } else {
                    $results['available']++;
                }

            } catch (\Exception $e) {
                $results['errors'][] = "Error processing barcode {$barcode}: " . $e->getMessage();
            }
        }

        // Bulk insert for better performance
        if (!empty($insertData)) {
            BarcodePool::insert($insertData);
        }
    }

    /**
     * Determine if barcode should be marked as legacy based on threshold
     */
    private function shouldMarkAsLegacy($barcode, ?int $legacyThreshold): bool
    {
        if ($legacyThreshold === null) {
            return false;
        }

        // For numeric barcodes, compare numerically
        if (is_numeric($barcode)) {
            return (int) $barcode <= $legacyThreshold;
        }

        // For non-numeric, try to extract numbers and compare
        $numericPart = preg_replace('/\D/', '', $barcode);
        if (!empty($numericPart) && is_numeric($numericPart)) {
            return (int) $numericPart <= $legacyThreshold;
        }

        // If no numeric comparison possible, don't mark as legacy
        return false;
    }

    /**
     * Clear existing barcode pool
     */
    private function clearExistingPool(): void
    {
        BarcodePool::where('status', '!=', 'assigned')->delete();
    }

    /**
     * Build legacy notes from enhanced barcode data
     */
    private function buildLegacyNotes(array $barcodeData): ?string
    {
        $notes = [];
        
        if (!empty($barcodeData['legacy_sku'])) {
            $notes[] = "Legacy SKU: {$barcodeData['legacy_sku']}";
        }
        
        if (!empty($barcodeData['legacy_product_name'])) {
            $notes[] = "Product: {$barcodeData['legacy_product_name']}";
        }
        
        if (!empty($barcodeData['legacy_brand'])) {
            $notes[] = "Brand: {$barcodeData['legacy_brand']}";
        }
        
        if (!empty($barcodeData['legacy_updated'])) {
            $notes[] = "Last Updated: {$barcodeData['legacy_updated']}";
        }
        
        if (!empty($barcodeData['legacy_status'])) {
            $notes[] = "Original Status: {$barcodeData['legacy_status']}";
        }
        
        return !empty($notes) ? implode(' | ', $notes) : null;
    }

    /**
     * Generate import summary
     */
    private function generateSummary(array $results): array
    {
        $total = $results['legacy_archived'] + $results['available'];
        
        return [
            'total_imported' => $total,
            'legacy_archived' => $results['legacy_archived'],
            'available_for_assignment' => $results['available'],
            'error_count' => count($results['errors']),
            'success_rate' => $total > 0 ? round((($total - count($results['errors'])) / $total) * 100, 2) : 0,
        ];
    }
}