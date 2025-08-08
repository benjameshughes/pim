<?php

namespace App\Services;

use App\DTOs\Import\ImportRequest;
use App\DTOs\Import\ImportResult;
use App\DTOs\Import\ValidationResult;
use App\DTOs\Import\WorksheetAnalysis;
use Illuminate\Support\Facades\Log;

class ImportManagerService
{
    public function __construct(
        private ExcelProcessingService $excelService,
        private ColumnMappingService $mappingService,
        private ImportValidationService $validationService,
        private ProductImportService $productService
    ) {}

    /**
     * Analyze uploaded file and return worksheet information
     */
    public function analyzeFile($file): WorksheetAnalysis
    {
        Log::info('Starting file analysis', ['filename' => $file->getClientOriginalName()]);

        return $this->excelService->analyzeFile($file);
    }

    /**
     * Load headers and sample data for selected worksheets
     */
    public function loadHeadersForSheets(array $selectedWorksheets, $file): array
    {
        return $this->excelService->loadHeadersForSelectedSheets($selectedWorksheets, $file);
    }

    /**
     * Execute dry run validation without making changes
     */
    public function runDryRun(ImportRequest $request): ValidationResult
    {
        Log::info('Starting dry run validation');

        // Load all data for validation
        $worksheetData = $this->excelService->loadDataForDryRun($request);

        // Apply column mappings
        $mappedData = $this->mappingService->mapAllRows($worksheetData, $request->columnMapping, $request->originalHeaders);

        // Run validation
        $result = $this->validationService->validateImportData($mappedData, $request);

        Log::info('Dry run completed', [
            'valid_rows' => $result->validRows,
            'error_rows' => $result->errorRows,
            'warnings' => count($result->warnings),
        ]);

        return $result;
    }

    /**
     * Execute the actual import process
     */
    public function executeImport(ImportRequest $request, ?callable $progressCallback = null): ImportResult
    {
        Log::info('Starting actual import process');

        // Load all data for import
        $worksheetData = $this->excelService->loadAllDataForImport($request);

        // Apply column mappings
        $mappedData = $this->mappingService->mapAllRows($worksheetData, $request->columnMapping, $request->originalHeaders);

        // Execute the import with progress tracking
        $result = $this->productService->importProducts($mappedData, $request, $progressCallback);

        Log::info('Import process completed', [
            'products_created' => $result->productsCreated,
            'variants_created' => $result->variantsCreated,
            'errors' => count($result->errors),
        ]);

        return $result;
    }

    /**
     * Save column mapping configuration for future use
     */
    public function saveColumnMapping(array $columnMapping, array $headers): void
    {
        $this->mappingService->saveMappingConfiguration($columnMapping, $headers);
    }

    /**
     * Load saved column mapping configuration
     */
    public function loadSavedMapping(): ?array
    {
        return $this->mappingService->loadSavedMappingConfiguration();
    }

    /**
     * Get mapping statistics for UI display
     */
    public function getMappingStats(): array
    {
        return $this->mappingService->getMappingStatistics();
    }

    /**
     * Load sample data for configuration UI
     */
    public function loadSampleDataForConfiguration(ImportRequest $request): array
    {
        Log::info('Loading sample data for configuration');

        // Load sample data from selected worksheets
        return $this->excelService->loadSampleData($request->file, $request->selectedWorksheets);
    }

    /**
     * Guess column mappings based on headers
     */
    public function guessColumnMappings(array $sampleData): array
    {
        if (empty($sampleData)) {
            return [];
        }

        // Get headers from first worksheet's first sample row
        // sampleData structure: ['WorksheetName' => [['col1' => 'val1', 'col2' => 'val2'], ...]]
        $firstWorksheetData = reset($sampleData);
        if (empty($firstWorksheetData)) {
            return [];
        }

        $headers = array_keys($firstWorksheetData[0] ?? []);

        return $this->mappingService->guessColumnMappings($headers);
    }
}
