<?php

namespace App\Services;

use App\DTOs\Import\ImportRequest;
use App\DTOs\Import\ImportResult;
use App\Services\DataTransformationService;
use App\Services\ImportSecurityService;
use App\Services\ImportErrorHandlingService;
use App\Services\ImportPerformanceService;
use App\Services\ExcelProcessingService;
use App\Services\ColumnMappingService;
use App\Services\ProductImportService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Enhanced Import Manager with comprehensive data handling, security, and performance optimization
 * Integrates all import services for production-ready Excel import processing
 */
class EnhancedImportManagerService
{
    public function __construct(
        private DataTransformationService $transformationService,
        private ImportSecurityService $securityService,
        private ImportErrorHandlingService $errorHandlingService,
        private ImportPerformanceService $performanceService,
        private ExcelProcessingService $excelService,
        private ColumnMappingService $columnMappingService,
        private ProductImportService $productImportService
    ) {}
    
    /**
     * Execute complete import process with all security and performance optimizations
     */
    public function executeImport(ImportRequest $request, callable $progressCallback = null): ImportResult
    {
        $importId = uniqid('import_', true);
        $this->errorHandlingService = new ImportErrorHandlingService($importId);
        
        Log::info('Starting enhanced import process', [
            'import_id' => $importId,
            'filename' => $request->file->getClientOriginalName(),
            'import_mode' => $request->importMode
        ]);
        
        try {
            // Phase 1: Security validation of uploaded file
            $securityResult = $this->securityService->validateFileUpload($request->file);
            
            if (!$securityResult->isSecure()) {
                throw new \Exception('File failed security validation: ' . 
                    implode(', ', array_column($securityResult->getThreats(), 'message')));
            }
            
            // Phase 2: Load and analyze Excel data
            $rawData = $this->loadExcelData($request);
            
            if (empty($rawData)) {
                throw new \Exception('No valid data found in Excel file');
            }
            
            // Phase 3: Security validation of data content
            $dataSecurityResult = $this->securityService->validateImportData($rawData);
            
            if ($dataSecurityResult->hasThreats()) {
                Log::warning('Data security threats detected', [
                    'import_id' => $importId,
                    'threat_count' => $dataSecurityResult->getThreatCount()
                ]);
                
                // Sanitize the data
                $rawData = $this->securityService->sanitizeImportData($rawData);
            }
            
            // Phase 4: Data transformation and validation
            $transformationResult = $this->transformationService->transformImportData($rawData);
            
            if ($transformationResult->hasErrors()) {
                foreach ($transformationResult->getErrors() as $error) {
                    $this->errorHandlingService->recordWarning(
                        "Data transformation error: {$error['message']}",
                        $error['row_number'] ?? null,
                        null,
                        $error['raw_data'] ?? []
                    );
                }
            }
            
            if (!$transformationResult->isSuccessful()) {
                throw new \Exception('Data transformation failed with ' . 
                    $transformationResult->getErrorCount() . ' errors');
            }
            
            $transformedData = $transformationResult->getTransformedData();
            
            // Phase 5: Performance-optimized import execution
            return $this->executeOptimizedImport($transformedData, $request, $progressCallback);
            
        } catch (\Throwable $e) {
            $this->errorHandlingService->handleGeneralError($e, 'import_execution', [
                'import_id' => $importId,
                'request' => $request->toArray()
            ]);
            
            $errorReport = $this->errorHandlingService->generateErrorReport();
            
            Log::error('Import process failed', [
                'import_id' => $importId,
                'error' => $e->getMessage(),
                'error_report_id' => $errorReport->getImportId()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Load Excel data with error handling
     */
    private function loadExcelData(ImportRequest $request): array
    {
        try {
            // Load headers for mapping
            $headerData = $this->excelService->loadHeadersForSelectedSheets(
                $request->selectedWorksheets, 
                $request->file
            );
            
            // Load all data for import
            $worksheetData = $this->excelService->loadAllDataForImport($request);
            
            // Map columns to fields
            $mappedData = $this->columnMappingService->mapAllRows(
                $worksheetData,
                $request->columnMapping,
                $headerData['headers']
            );
            
            Log::info('Excel data loaded successfully', [
                'total_rows' => count($mappedData),
                'mapped_fields' => count($request->columnMapping)
            ]);
            
            return $mappedData;
            
        } catch (\Exception $e) {
            $this->errorHandlingService->handleGeneralError($e, 'excel_data_loading');
            throw new \Exception('Failed to load Excel data: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute import with performance optimization
     */
    private function executeOptimizedImport(
        array $transformedData, 
        ImportRequest $request, 
        callable $progressCallback = null
    ): ImportResult {
        
        // Optimize database for bulk operations
        $this->performanceService->optimizeDatabaseOperations();
        
        try {
            // Convert transformed data to array format expected by ProductImportService
            $importData = $this->convertTransformedDataForImport($transformedData);
            
            // Process in optimized chunks
            $result = $this->performanceService->processInChunks(
                $importData,
                function(array $chunk) use ($request) {
                    return $this->processImportChunk($chunk, $request);
                },
                $progressCallback
            );
            
            // Combine chunk results into final import result
            $finalResult = $this->combineChunkResults($result);
            
            Log::info('Optimized import completed', [
                'total_processed' => count($importData),
                'success_count' => $finalResult->getProductsCreated() + $finalResult->getVariantsCreated(),
                'performance_metrics' => $this->performanceService->getMetrics()->getPerformanceSummary()
            ]);
            
            return $finalResult;
            
        } finally {
            // Always restore normal database operations
            $this->performanceService->restoreNormalDatabaseOperations();
        }
    }
    
    /**
     * Process a single chunk of import data
     */
    private function processImportChunk(array $chunk, ImportRequest $request): ImportResult
    {
        DB::beginTransaction();
        
        try {
            $chunkResult = $this->productImportService->importProducts($chunk, $request);
            
            DB::commit();
            return $chunkResult;
            
        } catch (\Throwable $e) {
            DB::rollBack();
            
            // Handle chunk processing error
            $this->errorHandlingService->handleGeneralError($e, 'chunk_processing', [
                'chunk_size' => count($chunk),
                'first_row_sample' => $chunk[0] ?? []
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Convert transformed data back to format expected by existing import services
     */
    private function convertTransformedDataForImport(array $transformedData): array
    {
        $importData = [];
        
        foreach ($transformedData as $transformedRow) {
            if (method_exists($transformedRow, 'getAllFields')) {
                $importData[] = $transformedRow->getAllFields();
            } else {
                $importData[] = $transformedRow;
            }
        }
        
        return $importData;
    }
    
    /**
     * Combine results from multiple chunks
     */
    private function combineChunkResults(array $chunkResults): ImportResult
    {
        $finalResult = new ImportResult();
        
        foreach ($chunkResults as $chunkResult) {
            if ($chunkResult instanceof ImportResult) {
                // Combine statistics
                $finalResult->addProductsCreated($chunkResult->getProductsCreated());
                $finalResult->addVariantsCreated($chunkResult->getVariantsCreated());
                
                // Combine errors
                foreach ($chunkResult->getErrors() as $error) {
                    $finalResult->addError($error);
                }
            }
        }
        
        return $finalResult;
    }
    
    /**
     * Execute dry run with all validations
     */
    public function executeDryRun(ImportRequest $request): array
    {
        $importId = uniqid('dryrun_', true);
        
        Log::info('Starting enhanced dry run', [
            'import_id' => $importId,
            'filename' => $request->file->getClientOriginalName()
        ]);
        
        try {
            // Security validation
            $securityResult = $this->securityService->validateFileUpload($request->file);
            
            // Load limited data for dry run
            $rawData = $this->excelService->loadDataForDryRun($request);
            
            // Data security validation
            $dataSecurityResult = $this->securityService->validateImportData($rawData);
            
            // Data transformation validation
            $transformationResult = $this->transformationService->transformImportData($rawData);
            
            return [
                'security_validation' => $securityResult->toArray(),
                'data_security_validation' => $dataSecurityResult->toArray(),
                'transformation_validation' => $transformationResult->toArray(),
                'sample_transformed_data' => array_slice($transformationResult->getTransformedData(), 0, 5),
                'recommendations' => $this->generateDryRunRecommendations(
                    $securityResult, 
                    $dataSecurityResult, 
                    $transformationResult
                )
            ];
            
        } catch (\Exception $e) {
            Log::error('Dry run failed', [
                'import_id' => $importId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'recommendations' => ['Fix the reported errors before attempting import']
            ];
        }
    }
    
    /**
     * Generate recommendations based on dry run results
     */
    private function generateDryRunRecommendations(
        $securityResult, 
        $dataSecurityResult, 
        $transformationResult
    ): array {
        $recommendations = [];
        
        if (!$securityResult->isSecure()) {
            $recommendations[] = 'File security issues detected - review and sanitize file before import';
        }
        
        if ($dataSecurityResult->hasThreats()) {
            $recommendations[] = 'Data contains potential security threats - they will be sanitized during import';
        }
        
        if ($transformationResult->hasErrors()) {
            $recommendations[] = 'Data transformation errors found - review data format and field mappings';
        }
        
        if ($transformationResult->hasWarnings()) {
            $recommendations[] = 'Data transformation warnings - some data may be modified during import';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Import looks good to proceed - all validations passed';
        }
        
        return $recommendations;
    }
    
    /**
     * Get comprehensive import statistics
     */
    public function getImportStatistics(string $importId): array
    {
        return [
            'performance_metrics' => $this->performanceService->getMetrics()->toArray(),
            'error_statistics' => $this->errorHandlingService->getStatistics(),
            'import_id' => $importId
        ];
    }
}