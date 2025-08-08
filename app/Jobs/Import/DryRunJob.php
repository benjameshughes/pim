<?php

namespace App\Jobs\Import;

use App\Models\ImportSession;
use App\Models\BarcodePool;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Import\ColumnMappingService;
use App\Services\Import\ValidationEngine;
use App\Services\Import\SkuPatternAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DryRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 2;

    private array $sampleData = [];
    private array $skuPatterns = [];
    private array $validationResults = [];

    public function __construct(
        private ImportSession $session
    ) {}

    public function handle(): void
    {
        Log::info('Starting dry run analysis', [
            'session_id' => $this->session->session_id,
        ]);

        try {
            $this->session->updateProgress(
                stage: 'dry_run',
                operation: 'Loading sample data for analysis',
                percentage: 5
            );

            // Load sample data (first 100 rows)
            $this->loadSampleData();

            $this->session->updateProgress(
                stage: 'dry_run',
                operation: 'Analyzing SKU patterns',
                percentage: 20
            );

            // Analyze SKU patterns for intelligent grouping
            $this->analyzeSkuPatterns();

            $this->session->updateProgress(
                stage: 'dry_run',
                operation: 'Validating data quality',
                percentage: 40
            );

            // Run comprehensive validation
            $this->validateSampleData();

            $this->session->updateProgress(
                stage: 'dry_run',
                operation: 'Predicting import outcomes',
                percentage: 60
            );

            // Predict what will happen during import
            $this->predictImportOutcomes();

            $this->session->updateProgress(
                stage: 'dry_run',
                operation: 'Checking resource availability',
                percentage: 80
            );

            // Check barcode pool and other resources
            $this->checkResourceAvailability();

            // Compile final dry run results
            $dryRunResults = $this->compileDryRunResults();
            
            $this->session->update([
                'dry_run_results' => $dryRunResults,
                'status' => 'awaiting_mapping',
            ]);

            $this->session->updateProgress(
                stage: 'dry_run',
                operation: 'Dry run completed - ready for import',
                percentage: 100
            );

            Log::info('Dry run completed successfully', [
                'session_id' => $this->session->session_id,
                'sample_rows' => count($this->sampleData),
                'validation_score' => $dryRunResults['validation_score'] ?? 0,
            ]);

            // Auto-proceed if validation score is high
            if (($dryRunResults['validation_score'] ?? 0) >= 85) {
                Log::info('High validation score detected, auto-proceeding to import', [
                    'session_id' => $this->session->session_id,
                    'validation_score' => $dryRunResults['validation_score'],
                ]);

                ProcessImportJob::dispatch($this->session)
                    ->onQueue('imports')
                    ->delay(now()->addSeconds(5));
            }

        } catch (\Exception $e) {
            Log::error('Dry run failed', [
                'session_id' => $this->session->session_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->session->markAsFailed('Dry run failed: ' . $e->getMessage());
        }
    }

    private function loadSampleData(): void
    {
        $filePath = Storage::path($this->session->file_path);
        
        if (!file_exists($filePath)) {
            throw new \Exception('Import file not found');
        }

        $fileAnalysis = $this->session->file_analysis;
        $columnMapping = $this->session->column_mapping ?? [];

        if ($this->session->file_type === 'csv') {
            $this->loadCsvSampleData($filePath, $columnMapping);
        } else {
            $this->loadExcelSampleData($filePath, $fileAnalysis, $columnMapping);
        }

        Log::info('Sample data loaded for dry run', [
            'session_id' => $this->session->session_id,
            'sample_rows' => count($this->sampleData),
        ]);
    }

    private function loadCsvSampleData(string $filePath, array $columnMapping): void
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception('Could not open CSV file');
        }

        // Skip header
        $headers = fgetcsv($handle);
        
        // Load up to 100 sample rows
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false && $rowCount < 100) {
            $mappedRow = $this->mapRowData($row, $headers, $columnMapping);
            if (!empty($mappedRow['product_name']) || !empty($mappedRow['variant_sku'])) {
                $this->sampleData[] = $mappedRow;
                $rowCount++;
            }
        }
        
        fclose($handle);
    }

    private function loadExcelSampleData(string $filePath, array $fileAnalysis, array $columnMapping): void
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        // Load from first worksheet with data
        $worksheetName = null;
        foreach ($fileAnalysis['worksheets'] ?? [] as $worksheet) {
            if (($worksheet['total_rows'] ?? 0) > 0) {
                $worksheetName = $worksheet['name'];
                break;
            }
        }

        if (!$worksheetName) {
            throw new \Exception('No worksheets with data found');
        }

        $reader->setLoadSheetsOnly([$worksheetName]);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        // Get headers
        $headers = [];
        $highestColumn = $worksheet->getHighestColumn();
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $cellValue = $worksheet->getCell($col . '1')->getCalculatedValue();
            $headers[] = (string) $cellValue;
        }

        // Load up to 100 sample rows
        $maxRow = min($worksheet->getHighestRow(), 101); // 1 header + 100 data rows
        for ($row = 2; $row <= $maxRow; $row++) {
            $rowData = [];
            for ($colIndex = 0; $colIndex < count($headers); $colIndex++) {
                $col = chr(ord('A') + $colIndex);
                $cellValue = $worksheet->getCell($col . $row)->getCalculatedValue();
                $rowData[] = (string) $cellValue;
            }

            $mappedRow = $this->mapRowData($rowData, $headers, $columnMapping);
            if (!empty($mappedRow['product_name']) || !empty($mappedRow['variant_sku'])) {
                $this->sampleData[] = $mappedRow;
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function mapRowData(array $row, array $headers, array $columnMapping): array
    {
        $mappingService = app(ColumnMappingService::class);
        $mapped = [];

        foreach ($columnMapping as $columnIndex => $fieldName) {
            if (!empty($fieldName) && isset($row[$columnIndex])) {
                $mapped[$fieldName] = $row[$columnIndex];
            }
        }

        return $mapped;
    }

    private function analyzeSkuPatterns(): void
    {
        $skus = array_filter(array_column($this->sampleData, 'variant_sku'));
        
        if (empty($skus)) {
            $this->skuPatterns = [
                'dominant_pattern' => 'none',
                'confidence' => 0,
                'analysis' => 'No SKUs found in sample data',
            ];
            return;
        }

        $analyzer = app(SkuPatternAnalyzer::class);
        $this->skuPatterns = $analyzer->analyzePatterns($skus);

        Log::info('SKU pattern analysis completed', [
            'session_id' => $this->session->session_id,
            'dominant_pattern' => $this->skuPatterns['dominant_pattern'] ?? 'unknown',
            'confidence' => $this->skuPatterns['confidence'] ?? 0,
            'sample_skus' => count($skus),
        ]);
    }

    private function validateSampleData(): void
    {
        $validator = app(ValidationEngine::class);
        $this->validationResults = [
            'total_rows' => count($this->sampleData),
            'valid_rows' => 0,
            'error_rows' => 0,
            'warnings' => [],
            'errors' => [],
            'field_coverage' => [],
            'data_quality_score' => 0,
        ];

        foreach ($this->sampleData as $index => $rowData) {
            $rowNumber = $index + 2; // Account for header
            $rowValidation = $validator->validateRow($rowData, $rowNumber, $this->session->configuration);

            if (empty($rowValidation['errors'])) {
                $this->validationResults['valid_rows']++;
            } else {
                $this->validationResults['error_rows']++;
                $this->validationResults['errors'] = array_merge(
                    $this->validationResults['errors'],
                    $rowValidation['errors']
                );
            }

            if (!empty($rowValidation['warnings'])) {
                $this->validationResults['warnings'] = array_merge(
                    $this->validationResults['warnings'],
                    $rowValidation['warnings']
                );
            }
        }

        // Calculate field coverage
        $this->calculateFieldCoverage();

        // Calculate overall data quality score
        $this->validationResults['data_quality_score'] = $this->calculateDataQualityScore();

        Log::info('Data validation completed', [
            'session_id' => $this->session->session_id,
            'valid_rows' => $this->validationResults['valid_rows'],
            'error_rows' => $this->validationResults['error_rows'],
            'data_quality_score' => $this->validationResults['data_quality_score'],
        ]);
    }

    private function calculateFieldCoverage(): void
    {
        $fieldCounts = [];
        $totalRows = count($this->sampleData);

        foreach ($this->sampleData as $row) {
            foreach ($row as $field => $value) {
                if (!empty($value)) {
                    $fieldCounts[$field] = ($fieldCounts[$field] ?? 0) + 1;
                }
            }
        }

        foreach ($fieldCounts as $field => $count) {
            $this->validationResults['field_coverage'][$field] = [
                'populated_rows' => $count,
                'coverage_percentage' => $totalRows > 0 ? round(($count / $totalRows) * 100, 1) : 0,
            ];
        }
    }

    private function calculateDataQualityScore(): int
    {
        $totalRows = $this->validationResults['total_rows'];
        if ($totalRows === 0) {
            return 0;
        }

        $validRowsPercentage = ($this->validationResults['valid_rows'] / $totalRows) * 100;
        $essentialFieldsCovered = 0;
        $essentialFields = ['product_name', 'variant_sku'];

        foreach ($essentialFields as $field) {
            $coverage = $this->validationResults['field_coverage'][$field]['coverage_percentage'] ?? 0;
            if ($coverage >= 90) {
                $essentialFieldsCovered++;
            }
        }

        $essentialFieldsScore = (count($essentialFields) > 0) 
            ? ($essentialFieldsCovered / count($essentialFields)) * 100
            : 0;

        // Weighted average: 60% valid rows, 40% essential fields coverage
        return (int) round(($validRowsPercentage * 0.6) + ($essentialFieldsScore * 0.4));
    }

    private function predictImportOutcomes(): void
    {
        // This will be expanded with actual prediction logic
        // For now, we'll do basic analysis based on the sample data
        
        $importModeConfig = $this->session->configuration['import_mode'] ?? 'create_or_update';
        $predictions = [
            'products_to_create' => 0,
            'products_to_update' => 0,
            'products_to_skip' => 0,
            'variants_to_create' => 0,
            'variants_to_update' => 0,
            'variants_to_skip' => 0,
        ];

        $processedProducts = [];
        $processedVariants = [];

        foreach ($this->sampleData as $row) {
            $productName = $row['product_name'] ?? '';
            $variantSku = $row['variant_sku'] ?? '';

            // Predict product actions
            if ($productName && !isset($processedProducts[$productName])) {
                $processedProducts[$productName] = true;
                $productExists = Product::where('name', $productName)->exists();

                switch ($importModeConfig) {
                    case 'create_only':
                        if ($productExists) {
                            $predictions['products_to_skip']++;
                        } else {
                            $predictions['products_to_create']++;
                        }
                        break;
                    case 'update_existing':
                        if ($productExists) {
                            $predictions['products_to_update']++;
                        } else {
                            $predictions['products_to_skip']++;
                        }
                        break;
                    case 'create_or_update':
                        if ($productExists) {
                            $predictions['products_to_update']++;
                        } else {
                            $predictions['products_to_create']++;
                        }
                        break;
                }
            }

            // Predict variant actions
            if ($variantSku && !isset($processedVariants[$variantSku])) {
                $processedVariants[$variantSku] = true;
                $variantExists = ProductVariant::where('sku', $variantSku)->exists();

                switch ($importModeConfig) {
                    case 'create_only':
                        if ($variantExists) {
                            $predictions['variants_to_skip']++;
                        } else {
                            $predictions['variants_to_create']++;
                        }
                        break;
                    case 'update_existing':
                        if ($variantExists) {
                            $predictions['variants_to_update']++;
                        } else {
                            $predictions['variants_to_skip']++;
                        }
                        break;
                    case 'create_or_update':
                        if ($variantExists) {
                            $predictions['variants_to_update']++;
                        } else {
                            $predictions['variants_to_create']++;
                        }
                        break;
                }
            }
        }

        $this->validationResults['predictions'] = $predictions;
    }

    private function checkResourceAvailability(): void
    {
        $configuration = $this->session->configuration;
        $resources = [
            'barcode_pool' => ['available' => 0, 'needed' => 0, 'sufficient' => true],
            'storage_space' => ['available_mb' => 0, 'estimated_needed_mb' => 0, 'sufficient' => true],
        ];

        // Check GS1 barcode availability
        if ($configuration['auto_assign_gs1_barcodes'] ?? false) {
            $poolStats = BarcodePool::getStats();
            $barcodesNeeded = $this->validationResults['predictions']['variants_to_create'] ?? 0;
            
            $resources['barcode_pool'] = [
                'available' => $poolStats['available'],
                'needed' => $barcodesNeeded,
                'sufficient' => $poolStats['available'] >= $barcodesNeeded,
            ];
        }

        // Estimate storage needs for images
        $estimatedImageStorage = count($this->sampleData) * 2; // 2MB per product estimate
        $availableStorage = disk_free_space(storage_path()) / (1024 * 1024); // Convert to MB

        $resources['storage_space'] = [
            'available_mb' => round($availableStorage),
            'estimated_needed_mb' => $estimatedImageStorage,
            'sufficient' => $availableStorage >= ($estimatedImageStorage * 2), // 2x safety margin
        ];

        $this->validationResults['resource_availability'] = $resources;
    }

    private function compileDryRunResults(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'sample_size' => count($this->sampleData),
            'sku_pattern_analysis' => $this->skuPatterns,
            'validation_results' => $this->validationResults,
            'validation_score' => $this->validationResults['data_quality_score'],
            'recommendations' => $this->generateRecommendations(),
            'ready_for_import' => $this->isReadyForImport(),
        ];
    }

    private function generateRecommendations(): array
    {
        $recommendations = [];

        // Data quality recommendations
        if ($this->validationResults['data_quality_score'] < 70) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Low Data Quality Score',
                'message' => 'Consider cleaning your data before importing. Many rows have validation issues.',
                'action' => 'Review error details and fix data quality issues.',
            ];
        }

        // SKU pattern recommendations
        if (($this->skuPatterns['confidence'] ?? 0) < 0.7) {
            $recommendations[] = [
                'type' => 'info',
                'title' => 'Inconsistent SKU Patterns',
                'message' => 'Your SKUs don\'t follow a consistent pattern. Consider standardizing them.',
                'action' => 'Enable name-based grouping instead of SKU-based grouping.',
            ];
        }

        // Resource availability recommendations
        $barcodePool = $this->validationResults['resource_availability']['barcode_pool'] ?? [];
        if (isset($barcodePool['sufficient']) && !$barcodePool['sufficient']) {
            $recommendations[] = [
                'type' => 'error',
                'title' => 'Insufficient GS1 Barcodes',
                'message' => "Need {$barcodePool['needed']} barcodes but only {$barcodePool['available']} available.",
                'action' => 'Import more GS1 barcodes or disable auto-assignment.',
            ];
        }

        return $recommendations;
    }

    private function isReadyForImport(): bool
    {
        // Check if there are any blocking issues
        $recommendations = $this->generateRecommendations();
        
        foreach ($recommendations as $rec) {
            if ($rec['type'] === 'error') {
                return false;
            }
        }

        // Must have minimum data quality score
        return ($this->validationResults['data_quality_score'] ?? 0) >= 50;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DryRunJob failed', [
            'session_id' => $this->session->session_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->session->markAsFailed('Dry run failed: ' . $exception->getMessage());
    }
}