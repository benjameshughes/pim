<?php

namespace App\Jobs;

use App\DTOs\Import\WorksheetAnalysis;
use App\DTOs\Import\WorksheetInfo;
use App\Models\FileProcessingProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class ExcelFileAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes

    public int $maxExceptions = 3;

    public function __construct(
        private string $filePath,
        private string $originalFileName,
        private string $progressId
    ) {
        $this->onQueue('file-processing');
    }

    public function handle(): void
    {
        $progress = FileProcessingProgress::find($this->progressId);

        if (! $progress) {
            Log::error('File processing progress record not found', ['id' => $this->progressId]);

            return;
        }

        try {
            $progress->updateStatus('analyzing', 'Starting file analysis...');

            // Verify file exists
            if (! Storage::disk('local')->exists($this->filePath)) {
                throw new \Exception('File not found: '.$this->filePath);
            }

            $fullPath = Storage::disk('local')->path($this->filePath);

            Log::info('Starting Excel file analysis', [
                'file' => $this->originalFileName,
                'path' => $this->filePath,
                'progress_id' => $this->progressId,
            ]);

            // Analyze the file
            $analysis = $this->analyzeExcelFile($fullPath, $progress);

            // Store results
            $progress->updateStatus('completed', 'File analysis completed successfully', [
                'analysis' => $analysis->toArray(),
                'worksheets_found' => $analysis->getWorksheetCount(),
            ]);

            Log::info('Excel file analysis completed', [
                'worksheets' => $analysis->getWorksheetCount(),
                'progress_id' => $this->progressId,
            ]);

        } catch (\Exception $e) {
            Log::error('Excel file analysis failed', [
                'error' => $e->getMessage(),
                'file' => $this->originalFileName,
                'progress_id' => $this->progressId,
            ]);

            $progress->updateStatus('failed', 'File analysis failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function analyzeExcelFile(string $filePath, FileProcessingProgress $progress): WorksheetAnalysis
    {
        try {
            $progress->updateStatus('analyzing', 'Detecting file format...');

            // Detect file type and create appropriate reader
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            // Get worksheet names
            if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'csv') {
                $worksheetNames = ['Sheet1']; // CSV has only one sheet
            } else {
                $worksheetNames = $reader->listWorksheetNames($filePath);
            }

            $progress->updateStatus('analyzing', 'Found '.count($worksheetNames).' worksheet(s), analyzing each...');

            $worksheets = [];
            $totalWorksheets = count($worksheetNames);

            foreach ($worksheetNames as $index => $name) {
                $progress->updateStatus('analyzing', 'Analyzing worksheet: '.$name.' ('.($index + 1).'/'.$totalWorksheets.')');

                $worksheetInfo = $this->analyzeWorksheetEfficiently($filePath, $index, $name);
                if ($worksheetInfo) {
                    $worksheets[] = $worksheetInfo;
                }

                // Update progress
                $progressPercent = (($index + 1) / $totalWorksheets) * 100;
                $progress->updateProgress($progressPercent);

                // Memory management - don't analyze too many at once
                if (count($worksheets) >= 50) {
                    Log::warning('Limiting worksheet analysis to first 50 sheets', [
                        'total_found' => $totalWorksheets,
                        'progress_id' => $this->progressId,
                    ]);
                    break;
                }
            }

            return new WorksheetAnalysis($worksheets);

        } catch (ReaderException $e) {
            throw new \Exception('Failed to read Excel file: '.$e->getMessage());
        }
    }

    private function analyzeWorksheetEfficiently(string $filePath, int $index, string $name): ?WorksheetInfo
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            // Only load specific worksheet for Excel files
            if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'csv') {
                $reader->setLoadSheetsOnly([$name]);
            }

            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Get headers efficiently - limit to first 50 columns
            $headers = [];
            $headerRow = $worksheet->rangeToArray('A1:AX1', null, true, false, false)[0] ?? [];

            foreach ($headerRow as $header) {
                if (! empty($header)) {
                    $headers[] = (string) $header;
                } else {
                    break; // Stop at first empty header
                }
            }

            // Efficient row counting for large files
            $highestRow = $worksheet->getHighestRow();
            $rowCount = $this->getActualRowCount($worksheet, $highestRow, count($headers));

            // Generate preview
            $preview = implode(', ', array_slice($headers, 0, 3));
            if (count($headers) > 3) {
                $preview .= '...';
            }

            // Clean up memory
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
            Log::warning('Failed to analyze worksheet', [
                'name' => $name,
                'error' => $e->getMessage(),
                'progress_id' => $this->progressId,
            ]);

            return null;
        }
    }

    private function getActualRowCount($worksheet, int $highestRow, int $headerCount): int
    {
        if ($highestRow <= 1) {
            return 0; // No data rows
        }

        // For smaller files, count actual data rows
        if ($highestRow <= 1000) {
            $rowCount = 0;
            $endColumn = chr(65 + max(0, $headerCount - 1));

            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = $worksheet->rangeToArray("A{$row}:{$endColumn}{$row}", null, true, false, false)[0] ?? [];
                if (! empty(array_filter($rowData))) {
                    $rowCount++;
                }
            }

            return $rowCount;
        }

        // For large files, use sampling to estimate
        $sampleSize = min(100, intval($highestRow * 0.1)); // Sample 10% or 100 rows, whichever is smaller
        $sampleInterval = max(1, intval(($highestRow - 1) / $sampleSize));

        $nonEmptyRows = 0;
        $sampledRows = 0;
        $endColumn = chr(65 + max(0, $headerCount - 1));

        for ($row = 2; $row <= $highestRow; $row += $sampleInterval) {
            $rowData = $worksheet->rangeToArray("A{$row}:{$endColumn}{$row}", null, true, false, false)[0] ?? [];
            if (! empty(array_filter($rowData))) {
                $nonEmptyRows++;
            }
            $sampledRows++;

            if ($sampledRows >= $sampleSize) {
                break;
            }
        }

        // Estimate total based on sample
        $estimatedRowCount = $sampledRows > 0 ? intval(($nonEmptyRows / $sampledRows) * ($highestRow - 1)) : 0;

        Log::info('Estimated row count for large file', [
            'worksheet' => $worksheet->getTitle(),
            'highest_row' => $highestRow,
            'sampled_rows' => $sampledRows,
            'non_empty_samples' => $nonEmptyRows,
            'estimated_count' => $estimatedRowCount,
            'progress_id' => $this->progressId,
        ]);

        return $estimatedRowCount;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ExcelFileAnalysisJob failed', [
            'file' => $this->originalFileName,
            'progress_id' => $this->progressId,
            'error' => $exception->getMessage(),
        ]);

        if ($progress = FileProcessingProgress::find($this->progressId)) {
            $progress->updateStatus('failed', 'File analysis failed: '.$exception->getMessage());
        }
    }
}
