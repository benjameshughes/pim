<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use App\DTOs\Import\WorksheetAnalysis;
use App\DTOs\Import\WorksheetInfo;
use App\DTOs\Import\ImportRequest;
use Illuminate\Support\Facades\Log;

class ExcelProcessingService
{
    /**
     * Analyze Excel file and return worksheet information
     */
    public function analyzeFile($file): WorksheetAnalysis
    {
        $filePath = $file->getRealPath();
        
        try {
            // Detect file type and create appropriate reader
            $reader = IOFactory::createReaderForFile($filePath);
            
            // For CSV files, there's only one "worksheet"
            if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'csv') {
                $worksheetNames = ['Sheet1']; // CSV has only one sheet
            } else {
                $worksheetNames = $reader->listWorksheetNames($filePath);
            }
            
            Log::info('Detected worksheets', ['count' => count($worksheetNames), 'names' => $worksheetNames]);
            
            $worksheets = [];
            foreach ($worksheetNames as $index => $name) {
                // Load minimal info for each worksheet
                $worksheetInfo = $this->analyzeWorksheet($filePath, $index, $name);
                if ($worksheetInfo) {
                    $worksheets[] = $worksheetInfo;
                }
                
                // Memory management - don't load too many at once
                if (count($worksheets) >= 50) {
                    Log::warning('Limiting worksheet analysis to first 50 sheets for memory management');
                    break;
                }
            }
            
            return new WorksheetAnalysis($worksheets);
            
        } catch (ReaderException $e) {
            Log::error('Excel file analysis failed', ['error' => $e->getMessage()]);
            throw new \Exception('Failed to analyze Excel file: ' . $e->getMessage());
        }
    }

    /**
     * Analyze individual worksheet
     */
    private function analyzeWorksheet(string $filePath, int $index, string $name): ?WorksheetInfo
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false); // Skip empty cells for performance
            
            // Only set sheet loading for Excel files, not CSV
            if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'csv') {
                $reader->setLoadSheetsOnly([$name]);
            }
            
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Get headers (first row)
            $headers = [];
            $headerRow = $worksheet->rangeToArray('A1:Z1', null, true, false, false)[0] ?? [];
            foreach ($headerRow as $header) {
                if (!empty($header)) {
                    $headers[] = (string) $header;
                } else {
                    break; // Stop at first empty header
                }
            }
            
            // Count rows with data (use faster method for large files)
            $highestRow = $worksheet->getHighestRow();
            if ($highestRow <= 1000) {
                // For smaller files, count actual data rows
                $rowCount = 0;
                for ($row = 2; $row <= $highestRow; $row++) {
                    $rowData = $worksheet->rangeToArray("A{$row}:" . chr(65 + count($headers) - 1) . $row, null, true, false, false)[0] ?? [];
                    if (!empty(array_filter($rowData))) {
                        $rowCount++;
                    }
                }
            } else {
                // For large files, estimate based on highest row
                $rowCount = max(0, $highestRow - 1); // Subtract header row
                Log::info("Large file detected - using estimated row count", ['estimated_rows' => $rowCount]);
            }
            
            // Generate preview
            $preview = implode(', ', array_slice($headers, 0, 3));
            if (count($headers) > 3) {
                $preview .= '...';
            }
            
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            
            return new WorksheetInfo(
                index: $index,
                name: $name, 
                headers: $headers,
                rowCount: $rowCount,
                preview: $preview
            );
            
        } catch (\Exception $e) {
            Log::warning('Failed to analyze worksheet', ['name' => $name, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Load headers for selected worksheets
     */
    public function loadHeadersForSelectedSheets(array $selectedWorksheets, $file): array
    {
        $filePath = $file->getRealPath();
        
        // Get worksheet names using proper PhpSpreadsheet API
        $reader = IOFactory::createReaderForFile($filePath);
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'csv') {
            $worksheetNames = ['Sheet1']; // CSV has only one sheet
        } else {
            $worksheetNames = $reader->listWorksheetNames($filePath);
        }
        
        // Use the first selected worksheet for header mapping
        $firstWorksheetIndex = $selectedWorksheets[0];
        $firstWorksheetName = $worksheetNames[$firstWorksheetIndex];
        
        return $this->loadWorksheetHeaders($filePath, $firstWorksheetName);
    }

    /**
     * Load headers and sample data from a specific worksheet
     */
    private function loadWorksheetHeaders(string $filePath, string $worksheetName): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$worksheetName]);
        
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Extract headers
        $headerRow = $worksheet->rangeToArray('A1:AZ1', null, true, false, false)[0] ?? [];
        $headers = [];
        foreach ($headerRow as $header) {
            if (!empty($header)) {
                $headers[] = (string) $header;
            }
        }
        
        // Extract sample data (first 5 rows)
        $sampleData = [];
        for ($row = 2; $row <= min(6, $worksheet->getHighestRow()); $row++) {
            $rowRange = "A{$row}:" . chr(65 + count($headers) - 1) . $row;
            $rowData = $worksheet->rangeToArray($rowRange, null, true, false, false)[0] ?? [];
            
            // Pad or trim to match header count
            $rowData = array_pad(array_slice($rowData, 0, count($headers)), count($headers), null);
            $sampleData[] = $rowData;
        }
        
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        
        return [
            'headers' => $headers,
            'sampleData' => $sampleData
        ];
    }

    /**
     * Load data for dry run validation (limited rows)
     */
    public function loadDataForDryRun(ImportRequest $request): array
    {
        return $this->loadWorksheetData($request, 100); // Limit to 100 rows for dry run
    }

    /**
     * Load all data for actual import
     */
    public function loadAllDataForImport(ImportRequest $request): array
    {
        return $this->loadWorksheetData($request); // Load all data
    }

    /**
     * Load worksheet data with optional row limit
     */
    private function loadWorksheetData(ImportRequest $request, ?int $rowLimit = null): array
    {
        $filePath = $request->file->getRealPath();
        
        // Get worksheet names using proper PhpSpreadsheet API
        $reader = IOFactory::createReaderForFile($filePath);
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'csv') {
            $worksheetNames = ['Sheet1']; // CSV has only one sheet
        } else {
            $worksheetNames = $reader->listWorksheetNames($filePath);
        }
        
        $allData = [];
        
        foreach ($request->selectedWorksheets as $worksheetIndex) {
            $worksheetName = $worksheetNames[$worksheetIndex];
            
            Log::info('Loading data from worksheet', [
                'worksheet_index' => $worksheetIndex,
                'worksheet_name' => $worksheetName,
                'row_limit' => $rowLimit
            ]);
            
            $worksheetData = $this->loadSingleWorksheetData($filePath, $worksheetName, $rowLimit);
            $allData = array_merge($allData, $worksheetData);
        }
        
        Log::info('Data loading completed', [
            'total_rows' => count($allData),
            'worksheets_processed' => count($request->selectedWorksheets)
        ]);
        
        return $allData;
    }

    /**
     * Load data from a single worksheet
     */
    private function loadSingleWorksheetData(string $filePath, string $worksheetName, ?int $rowLimit = null): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$worksheetName]);
        
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Get headers
        $headerRow = $worksheet->rangeToArray('A1:AZ1', null, true, false, false)[0] ?? [];
        $headers = [];
        foreach ($headerRow as $header) {
            if (!empty($header)) {
                $headers[] = (string) $header;
            }
        }
        
        // Load data rows
        $data = [];
        $maxRow = $worksheet->getHighestRow();
        $endRow = $rowLimit ? min($maxRow, $rowLimit + 1) : $maxRow;
        
        for ($row = 2; $row <= $endRow; $row++) {
            $rowRange = "A{$row}:" . chr(65 + count($headers) - 1) . $row;
            $rowData = $worksheet->rangeToArray($rowRange, null, true, false, false)[0] ?? [];
            
            // Pad or trim to match header count
            $rowData = array_pad(array_slice($rowData, 0, count($headers)), count($headers), null);
            
            // Only include rows with some data
            if (!empty(array_filter($rowData))) {
                $data[] = [
                    'data' => $rowData,
                    'headers' => $headers
                ];
            }
        }
        
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        
        return $data;
    }

    /**
     * Load sample data from selected worksheets for UI preview
     */
    public function loadSampleData($file, array $selectedWorksheets, int $sampleSize = 5): array
    {
        $filePath = $file->getRealPath();
        $allSampleData = [];
        
        foreach ($selectedWorksheets as $worksheetName) {
            try {
                $reader = IOFactory::createReaderForFile($filePath);
                $reader->setReadDataOnly(true);
                $reader->setReadEmptyCells(false);
                
                // Only set sheet loading for Excel files, not CSV
                if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'csv') {
                    $reader->setLoadSheetsOnly([$worksheetName]);
                }
                
                $spreadsheet = $reader->load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                
                // Get headers (first row)
                $headers = [];
                $headerRow = $worksheet->rangeToArray('A1:Z1', null, true, false, false)[0] ?? [];
                foreach ($headerRow as $header) {
                    if (!empty($header)) {
                        $headers[] = (string) $header;
                    } else {
                        break; // Stop at first empty header
                    }
                }
                
                // Get sample data rows
                $sampleData = [];
                $maxRows = min($sampleSize + 1, $worksheet->getHighestRow());
                for ($row = 2; $row <= $maxRows; $row++) {
                    $rowData = $worksheet->rangeToArray("A{$row}:" . chr(65 + count($headers) - 1) . $row, null, true, false, false)[0] ?? [];
                    
                    if (!empty(array_filter($rowData))) {
                        $rowWithHeaders = [];
                        foreach ($headers as $index => $header) {
                            $rowWithHeaders[$header] = $rowData[$index] ?? '';
                        }
                        $sampleData[] = $rowWithHeaders;
                        
                        if (count($sampleData) >= $sampleSize) {
                            break;
                        }
                    }
                }
                
                $allSampleData[$worksheetName] = $sampleData;
                
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                
            } catch (\Exception $e) {
                Log::warning("Failed to load sample data for worksheet {$worksheetName}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $allSampleData;
    }
}