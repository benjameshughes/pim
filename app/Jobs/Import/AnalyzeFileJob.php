<?php

namespace App\Jobs\Import;

use App\Models\ImportSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AnalyzeFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    public function __construct(
        private ImportSession $session
    ) {}

    public function handle(): void
    {
        Log::info('Starting file analysis', [
            'session_id' => $this->session->session_id,
            'file_name' => $this->session->original_filename,
        ]);

        try {
            $this->session->updateProgress(
                stage: 'analyzing_file',
                operation: 'Reading file structure',
                percentage: 10
            );

            $fileAnalysis = $this->analyzeFile();
            
            $this->session->update([
                'file_analysis' => $fileAnalysis,
                'total_rows' => $fileAnalysis['total_rows'] ?? 0,
            ]);

            $this->session->updateProgress(
                stage: 'analyzing_file', 
                operation: 'File analysis complete',
                percentage: 50
            );

            // Auto-map columns if we can
            $this->performColumnMapping($fileAnalysis);

            $this->session->updateProgress(
                stage: 'awaiting_mapping',
                operation: 'Ready for column mapping review',
                percentage: 75
            );

            // If we have good auto-mapping confidence, proceed to dry run
            if ($this->hasGoodMappingConfidence()) {
                Log::info('Good mapping confidence detected, proceeding to dry run', [
                    'session_id' => $this->session->session_id,
                ]);
                
                DryRunJob::dispatch($this->session)
                    ->onQueue('imports')
                    ->delay(now()->addSeconds(2));
            } else {
                Log::info('Awaiting user input for column mapping', [
                    'session_id' => $this->session->session_id,
                ]);
                
                $this->session->update([
                    'status' => 'awaiting_mapping',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('File analysis failed', [
                'session_id' => $this->session->session_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->session->markAsFailed('File analysis failed: ' . $e->getMessage());
        }
    }

    private function analyzeFile(): array
    {
        $filePath = Storage::path($this->session->file_path);
        
        if (!file_exists($filePath)) {
            throw new \Exception('Import file not found');
        }

        if ($this->session->file_type === 'csv') {
            return $this->analyzeCsvFile($filePath);
        } else {
            return $this->analyzeExcelFile($filePath);
        }
    }

    private function analyzeCsvFile(string $filePath): array
    {
        $this->session->updateProgress(
            stage: 'analyzing_file',
            operation: 'Analyzing CSV structure',
            percentage: 20
        );

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception('Could not open CSV file');
        }

        // Read headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new \Exception('CSV file has no headers');
        }

        // Count total rows
        $totalRows = 0;
        while (fgetcsv($handle) !== false) {
            $totalRows++;
        }
        fclose($handle);

        // Get sample data
        $sampleData = [];
        $handle = fopen($filePath, 'r');
        fgetcsv($handle); // Skip headers
        
        for ($i = 0; $i < 5 && ($row = fgetcsv($handle)) !== false; $i++) {
            $sampleData[] = $row;
        }
        fclose($handle);

        return [
            'file_type' => 'csv',
            'worksheets' => [
                [
                    'index' => 0,
                    'name' => 'CSV Data',
                    'headers' => $headers,
                    'sample_data' => $sampleData,
                    'total_rows' => $totalRows,
                    'header_count' => count($headers),
                ]
            ],
            'total_rows' => $totalRows,
            'total_worksheets' => 1,
            'analysis_timestamp' => now()->toISOString(),
        ];
    }

    private function analyzeExcelFile(string $filePath): array
    {
        $this->session->updateProgress(
            stage: 'analyzing_file',
            operation: 'Analyzing Excel structure',
            percentage: 20
        );

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        
        // Get worksheet names first
        $worksheetNames = $reader->listWorksheetNames($filePath);
        
        $worksheets = [];
        $totalRows = 0;
        
        foreach ($worksheetNames as $index => $name) {
            $this->session->updateProgress(
                stage: 'analyzing_file',
                operation: "Analyzing worksheet: {$name}",
                percentage: 20 + (($index + 1) / count($worksheetNames)) * 30
            );

            try {
                $reader->setLoadSheetsOnly([$name]);
                $spreadsheet = $reader->load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                
                // Get headers
                $headers = [];
                $highestColumn = $worksheet->getHighestColumn();
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $cellValue = $worksheet->getCell($col . '1')->getCalculatedValue();
                    if ($cellValue !== null && trim($cellValue) !== '') {
                        $headers[] = (string) $cellValue;
                    } else {
                        break; // Stop at first empty header
                    }
                }
                
                $worksheetRows = $worksheet->getHighestRow() - 1; // Exclude header
                $totalRows += $worksheetRows;
                
                // Get sample data
                $sampleData = [];
                $maxSampleRows = min(5, $worksheet->getHighestRow());
                for ($row = 2; $row <= $maxSampleRows + 1; $row++) {
                    $rowData = [];
                    for ($colIndex = 0; $colIndex < count($headers); $colIndex++) {
                        $col = chr(ord('A') + $colIndex);
                        $cellValue = $worksheet->getCell($col . $row)->getCalculatedValue();
                        $rowData[] = (string) $cellValue;
                    }
                    $sampleData[] = $rowData;
                }
                
                $worksheets[] = [
                    'index' => $index,
                    'name' => $name,
                    'headers' => $headers,
                    'sample_data' => $sampleData,
                    'total_rows' => $worksheetRows,
                    'header_count' => count($headers),
                ];
                
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                
            } catch (\Exception $e) {
                Log::warning('Could not analyze worksheet', [
                    'session_id' => $this->session->session_id,
                    'worksheet' => $name,
                    'error' => $e->getMessage(),
                ]);
                
                $worksheets[] = [
                    'index' => $index,
                    'name' => $name,
                    'error' => $e->getMessage(),
                    'headers' => [],
                    'sample_data' => [],
                    'total_rows' => 0,
                    'header_count' => 0,
                ];
            }
        }

        return [
            'file_type' => 'xlsx',
            'worksheets' => $worksheets,
            'total_rows' => $totalRows,
            'total_worksheets' => count($worksheets),
            'analysis_timestamp' => now()->toISOString(),
        ];
    }

    private function performColumnMapping(array $fileAnalysis): void
    {
        $this->session->updateProgress(
            stage: 'analyzing_file',
            operation: 'Auto-mapping columns',
            percentage: 60
        );

        $mappingService = app(\App\Services\Import\ColumnMappingService::class);
        $columnMapping = [];
        
        foreach ($fileAnalysis['worksheets'] as $worksheet) {
            if (empty($worksheet['headers'])) {
                continue;
            }
            
            $worksheetMapping = $mappingService->autoMapColumns($worksheet['headers']);
            
            // For now, use mapping from the first non-empty worksheet
            if (empty($columnMapping) && !empty($worksheetMapping)) {
                $columnMapping = $worksheetMapping;
            }
        }
        
        $this->session->update([
            'column_mapping' => $columnMapping,
        ]);

        Log::info('Column auto-mapping completed', [
            'session_id' => $this->session->session_id,
            'mapped_fields' => count(array_filter($columnMapping)),
            'total_columns' => count($columnMapping),
        ]);
    }

    private function hasGoodMappingConfidence(): bool
    {
        $columnMapping = $this->session->column_mapping ?? [];
        
        if (empty($columnMapping)) {
            return false;
        }
        
        // Check if we have the essential mappings
        $essentialFields = ['product_name', 'variant_sku'];
        $mappedFields = array_values($columnMapping);
        
        $essentialMapped = array_intersect($essentialFields, $mappedFields);
        
        // We need at least the essential fields mapped
        if (count($essentialMapped) < count($essentialFields)) {
            return false;
        }
        
        // Check overall mapping percentage
        $nonEmptyMappings = count(array_filter($columnMapping));
        $totalColumns = count($columnMapping);
        $mappingPercentage = $totalColumns > 0 ? ($nonEmptyMappings / $totalColumns) : 0;
        
        return $mappingPercentage >= 0.3; // At least 30% of columns mapped
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeFileJob failed', [
            'session_id' => $this->session->session_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->session->markAsFailed('File analysis failed: ' . $exception->getMessage());
    }
}