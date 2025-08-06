<?php

namespace App\Actions\Import;

use App\Services\DataTransformationService;
use App\Services\ImportSecurityService;
use App\DTOs\Import\TransformedRow;
use Illuminate\Support\Facades\Log;

class MapRowToFields
{
    public function __construct(
        private DataTransformationService $transformationService,
        private ImportSecurityService $securityService
    ) {}

    /**
     * Execute row mapping with comprehensive data transformation and security validation
     */
    public function execute(array $row, array $headers, array $headerToFieldMapping): array
    {
        // Step 1: Basic field mapping
        $mapped = $this->mapFieldsFromHeaders($row, $headers, $headerToFieldMapping);
        
        if (empty($mapped)) {
            Log::warning('No fields mapped from row', [
                'row_data_count' => count($row),
                'headers_count' => count($headers),
                'mapping_count' => count($headerToFieldMapping)
            ]);
            return [];
        }
        
        // Step 2: Security validation and sanitization
        $securityResult = $this->securityService->validateImportData([$mapped]);
        
        if ($securityResult->hasThreats()) {
            Log::warning('Security threats detected in row data', [
                'threats' => $securityResult->getThreats(),
                'mapped_fields' => array_keys($mapped)
            ]);
            
            // Sanitize the data to remove threats
            $mapped = $this->securityService->sanitizeImportData([$mapped])[0];
        }
        
        // Step 3: Data transformation and type casting
        try {
            $transformationResult = $this->transformationService->transformImportData([$mapped]);
            
            if ($transformationResult->hasErrors()) {
                Log::warning('Data transformation errors', [
                    'errors' => $transformationResult->getErrors(),
                    'mapped_fields' => array_keys($mapped)
                ]);
                return $mapped; // Return original data if transformation fails
            }
            
            $transformedRows = $transformationResult->getTransformedData();
            if (!empty($transformedRows)) {
                /** @var TransformedRow $transformedRow */
                $transformedRow = $transformedRows[0];
                $mapped = $transformedRow->getAllFields();
            }
            
        } catch (\Exception $e) {
            Log::error('Data transformation failed', [
                'error' => $e->getMessage(),
                'mapped_fields' => array_keys($mapped)
            ]);
            // Continue with original mapped data
        }
        
        Log::debug('Row mapping completed', [
            'original_row_count' => count($row),
            'mapped_fields' => array_keys($mapped),
            'security_threats' => $securityResult->getThreatCount(),
            'final_data_sample' => $this->createDataSample($mapped)
        ]);
        
        return $mapped;
    }
    
    /**
     * Perform basic field mapping from headers
     */
    private function mapFieldsFromHeaders(array $row, array $headers, array $headerToFieldMapping): array
    {
        $mapped = [];
        
        // Map based on header names, not positions
        foreach ($headers as $columnIndex => $headerName) {
            if (isset($headerToFieldMapping[$headerName]) && isset($row[$columnIndex])) {
                $fieldName = $headerToFieldMapping[$headerName];
                $value = $row[$columnIndex];
                
                // Skip completely empty values
                if ($value !== null && $value !== '') {
                    $mapped[$fieldName] = $value;
                }
            }
        }
        
        return $mapped;
    }
    
    /**
     * Create safe data sample for logging (truncate long values)
     */
    private function createDataSample(array $data): array
    {
        $sample = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value) && strlen($value) > 50) {
                $sample[$key] = substr($value, 0, 50) . '...';
            } else {
                $sample[$key] = $value;
            }
        }
        
        return $sample;
    }
}