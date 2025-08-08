<?php

namespace App\Jobs;

use App\Models\FileProcessingProgress;
use App\Traits\ChunkedExcelReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelDataStreamingJob implements ShouldQueue
{
    use ChunkedExcelReader, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes for large files

    public int $maxExceptions = 3;

    public function __construct(
        private string $filePath,
        private array $selectedWorksheets,
        private string $progressId,
        private string $dataType = 'sample', // 'sample', 'dry_run', or 'full'
        private int $chunkSize = 1000,
        private ?int $maxRows = null
    ) {
        $this->onQueue('data-processing');
    }

    public function handle(): void
    {
        $progress = FileProcessingProgress::find($this->progressId);

        if (! $progress) {
            Log::error('File processing progress record not found', ['id' => $this->progressId]);

            return;
        }

        try {
            $progress->updateStatus('processing', "Starting {$this->dataType} data loading...");

            // Verify file exists
            if (! Storage::disk('local')->exists($this->filePath)) {
                throw new \Exception('File not found: '.$this->filePath);
            }

            $fullPath = Storage::disk('local')->path($this->filePath);

            Log::info('Starting Excel data streaming', [
                'data_type' => $this->dataType,
                'worksheets' => count($this->selectedWorksheets),
                'chunk_size' => $this->chunkSize,
                'max_rows' => $this->maxRows,
                'progress_id' => $this->progressId,
            ]);

            // Process worksheets with chunking
            $allData = $this->processWorksheetsInChunks($fullPath, $progress);

            // Store results based on data type
            $this->storeProcessedData($progress, $allData);

            Log::info('Excel data streaming completed', [
                'total_rows' => count($allData),
                'data_type' => $this->dataType,
                'progress_id' => $this->progressId,
            ]);

        } catch (\Exception $e) {
            Log::error('Excel data streaming failed', [
                'error' => $e->getMessage(),
                'data_type' => $this->dataType,
                'progress_id' => $this->progressId,
            ]);

            $progress->updateStatus('failed', 'Data loading failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function processWorksheetsInChunks(string $filePath, FileProcessingProgress $progress): array
    {
        $allData = [];
        $totalWorksheets = count($this->selectedWorksheets);
        $processedWorksheets = 0;

        foreach ($this->selectedWorksheets as $worksheetName) {
            $progress->updateStatus('processing', "Processing worksheet: {$worksheetName}");

            try {
                $worksheetData = $this->processWorksheetWithChunking($filePath, $worksheetName, $progress);
                $allData = array_merge($allData, $worksheetData);

                $processedWorksheets++;
                $worksheetProgress = ($processedWorksheets / $totalWorksheets) * 100;
                $progress->updateProgress($worksheetProgress);

                Log::info('Worksheet processed', [
                    'worksheet' => $worksheetName,
                    'rows_loaded' => count($worksheetData),
                    'total_rows_so_far' => count($allData),
                    'progress_id' => $this->progressId,
                ]);

            } catch (\Exception $e) {
                Log::warning('Failed to process worksheet', [
                    'worksheet' => $worksheetName,
                    'error' => $e->getMessage(),
                    'progress_id' => $this->progressId,
                ]);
                // Continue with other worksheets
            }
        }

        return $allData;
    }

    private function processWorksheetWithChunking(string $filePath, string $worksheetName, FileProcessingProgress $progress): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        // Only set sheet loading for Excel files, not CSV
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'csv') {
            $reader->setLoadSheetsOnly([$worksheetName]);
        }

        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        // Get headers
        $headers = $this->extractHeaders($worksheet);

        if (empty($headers)) {
            Log::warning('No headers found in worksheet', [
                'worksheet' => $worksheetName,
                'progress_id' => $this->progressId,
            ]);
            $spreadsheet->disconnectWorksheets();

            return [];
        }

        // Determine row processing limits
        $highestRow = $worksheet->getHighestRow();
        $maxRowsToProcess = $this->getMaxRowsToProcess($highestRow);

        Log::info('Processing worksheet rows', [
            'worksheet' => $worksheetName,
            'highest_row' => $highestRow,
            'max_rows_to_process' => $maxRowsToProcess,
            'headers_count' => count($headers),
            'progress_id' => $this->progressId,
        ]);

        // Process data in chunks
        $worksheetData = $this->readWorksheetInChunks($worksheet, $headers, $maxRowsToProcess, $progress, $worksheetName);

        // Clean up memory
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $worksheetData;
    }

    private function getMaxRowsToProcess(int $highestRow): int
    {
        $dataRows = max(0, $highestRow - 1); // Subtract header row

        return match ($this->dataType) {
            'sample' => min($dataRows, 10), // Only first 10 rows for sample
            'dry_run' => $this->maxRows ? min($dataRows, $this->maxRows) : min($dataRows, 1000), // Limited for dry run
            'full' => $this->maxRows ? min($dataRows, $this->maxRows) : $dataRows, // All data for full import
            default => min($dataRows, 100)
        };
    }

    private function readWorksheetInChunks($worksheet, array $headers, int $maxRows, FileProcessingProgress $progress, string $worksheetName): array
    {
        $allData = [];
        $processedRows = 0;
        $startRow = 2; // Skip header row
        $endColumn = chr(65 + count($headers) - 1);

        while ($processedRows < $maxRows) {
            $chunkEndRow = min($startRow + $this->chunkSize - 1, $maxRows + 1);

            // Read chunk
            $chunkData = $this->readDataChunk($worksheet, $startRow, $chunkEndRow, $endColumn, $headers);

            if (empty($chunkData)) {
                break; // No more data
            }

            $allData = array_merge($allData, $chunkData);
            $processedRows += count($chunkData);

            // Update progress
            $chunkProgress = ($processedRows / $maxRows) * 100;
            $progress->updateStatus('processing', "Processing {$worksheetName}: {$processedRows}/{$maxRows} rows");

            // Memory management - allow garbage collection
            if ($processedRows % ($this->chunkSize * 5) === 0) {
                gc_collect_cycles();
            }

            $startRow = $chunkEndRow + 1;
        }

        Log::info('Worksheet chunk processing completed', [
            'worksheet' => $worksheetName,
            'total_rows_processed' => $processedRows,
            'data_rows_loaded' => count($allData),
            'progress_id' => $this->progressId,
        ]);

        return $allData;
    }

    private function readDataChunk($worksheet, int $startRow, int $endRow, string $endColumn, array $headers): array
    {
        $chunkData = [];

        for ($row = $startRow; $row <= $endRow; $row++) {
            try {
                $rowData = $worksheet->rangeToArray("A{$row}:{$endColumn}{$row}", null, true, false, false)[0] ?? [];

                // Skip empty rows
                if (empty(array_filter($rowData))) {
                    continue;
                }

                // Pad or trim to match header count
                $rowData = array_pad(array_slice($rowData, 0, count($headers)), count($headers), null);

                $chunkData[] = [
                    'data' => $rowData,
                    'headers' => $headers,
                    'row_number' => $row,
                ];

            } catch (\Exception $e) {
                Log::warning('Failed to read row', [
                    'row' => $row,
                    'error' => $e->getMessage(),
                    'progress_id' => $this->progressId,
                ]);

                continue;
            }
        }

        return $chunkData;
    }

    private function storeProcessedData(FileProcessingProgress $progress, array $data): void
    {
        $resultData = [
            'data_type' => $this->dataType,
            'total_rows' => count($data),
            'worksheets_processed' => count($this->selectedWorksheets),
            'chunk_size_used' => $this->chunkSize,
        ];

        // Store different data based on type
        switch ($this->dataType) {
            case 'sample':
                $resultData['sample_data'] = $this->formatSampleData($data);
                break;
            case 'dry_run':
                // For dry run, we store the data temporarily for validation
                $resultData['validation_data_key'] = $this->storeTemporaryData($data);
                break;
            case 'full':
                // For full import, we store the data key for processing
                $resultData['import_data_key'] = $this->storeTemporaryData($data);
                break;
        }

        $progress->updateStatus('completed', 'Data loading completed successfully', $resultData);
    }

    private function formatSampleData(array $data): array
    {
        // Group sample data by worksheet and limit to first few rows
        $sampleData = [];

        foreach ($this->selectedWorksheets as $worksheetName) {
            $worksheetRows = array_filter($data, function ($row) {
                // Assuming we add worksheet info to each row during processing
                return true; // Simplified for now
            });

            $sampleData[$worksheetName] = array_slice($worksheetRows, 0, 5);
        }

        return $sampleData;
    }

    private function storeTemporaryData(array $data): string
    {
        // Store large datasets temporarily in cache or storage
        $dataKey = 'excel_data_'.$this->progressId.'_'.time();

        // Use file storage for large datasets
        $fileName = "temp_data/{$dataKey}.json";
        Storage::disk('local')->put($fileName, json_encode($data));

        Log::info('Temporary data stored', [
            'key' => $dataKey,
            'file' => $fileName,
            'size' => count($data),
            'progress_id' => $this->progressId,
        ]);

        return $dataKey;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ExcelDataStreamingJob failed', [
            'data_type' => $this->dataType,
            'worksheets' => count($this->selectedWorksheets),
            'progress_id' => $this->progressId,
            'error' => $exception->getMessage(),
        ]);

        if ($progress = FileProcessingProgress::find($this->progressId)) {
            $progress->updateStatus('failed', 'Data loading failed: '.$exception->getMessage());
        }
    }
}
