<?php

namespace App\Services;

use App\DTOs\Import\WorksheetAnalysis;
use App\DTOs\Import\WorksheetInfo;
use App\Jobs\ExcelDataStreamingJob;
use App\Jobs\ExcelFileAnalysisJob;
use App\Models\FileProcessingProgress;
use App\Traits\ChunkedExcelReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AsyncExcelProcessingService
{
    use ChunkedExcelReader;

    /**
     * Analyze Excel file asynchronously and return progress tracking ID
     */
    public function analyzeFileAsync(UploadedFile $file): string
    {
        // Store file temporarily
        $fileName = $file->getClientOriginalName();
        $filePath = $this->storeFileTemporarily($file);
        
        // Create progress tracking record
        $progress = FileProcessingProgress::createForFileAnalysis(
            Auth::id(),
            $fileName,
            $filePath
        );

        // Dispatch background job
        ExcelFileAnalysisJob::dispatch($filePath, $fileName, $progress->id);
        
        Log::info('File analysis job dispatched', [
            'file' => $fileName,
            'progress_id' => $progress->id,
            'user_id' => Auth::id()
        ]);

        return $progress->id;
    }

    /**
     * Load sample data asynchronously
     */
    public function loadSampleDataAsync(UploadedFile $file, array $selectedWorksheets): string
    {
        $fileName = $file->getClientOriginalName();
        $filePath = $this->storeFileTemporarily($file);
        
        $progress = FileProcessingProgress::createForDataLoading(
            Auth::id(),
            $fileName,
            'sample'
        );

        // Dispatch job with small chunk size and row limit for samples
        ExcelDataStreamingJob::dispatch(
            $filePath,
            $selectedWorksheets,
            $progress->id,
            'sample',
            100, // Small chunk size for samples
            10   // Only first 10 rows for sample
        );

        Log::info('Sample data loading job dispatched', [
            'file' => $fileName,
            'worksheets' => count($selectedWorksheets),
            'progress_id' => $progress->id
        ]);

        return $progress->id;
    }

    /**
     * Load data for dry run validation asynchronously
     */
    public function loadDataForDryRunAsync(UploadedFile $file, array $selectedWorksheets, int $maxRows = 1000): string
    {
        $fileName = $file->getClientOriginalName();
        $filePath = $this->storeFileTemporarily($file);
        
        $progress = FileProcessingProgress::createForDataLoading(
            Auth::id(),
            $fileName,
            'dry_run'
        );

        ExcelDataStreamingJob::dispatch(
            $filePath,
            $selectedWorksheets,
            $progress->id,
            'dry_run',
            500, // Medium chunk size for dry runs
            $maxRows
        );

        Log::info('Dry run data loading job dispatched', [
            'file' => $fileName,
            'worksheets' => count($selectedWorksheets),
            'max_rows' => $maxRows,
            'progress_id' => $progress->id
        ]);

        return $progress->id;
    }

    /**
     * Load all data for full import asynchronously
     */
    public function loadAllDataForImportAsync(UploadedFile $file, array $selectedWorksheets): string
    {
        $fileName = $file->getClientOriginalName();
        $filePath = $this->storeFileTemporarily($file);
        
        $progress = FileProcessingProgress::createForDataLoading(
            Auth::id(),
            $fileName,
            'full'
        );

        ExcelDataStreamingJob::dispatch(
            $filePath,
            $selectedWorksheets,
            $progress->id,
            'full',
            1000 // Larger chunk size for full imports
        );

        Log::info('Full import data loading job dispatched', [
            'file' => $fileName,
            'worksheets' => count($selectedWorksheets),
            'progress_id' => $progress->id
        ]);

        return $progress->id;
    }

    /**
     * Get progress status for a job
     */
    public function getProgress(string $progressId): ?array
    {
        // Try to get from cache first for real-time updates
        $cachedProgress = FileProcessingProgress::getCachedProgress($progressId, Auth::id());
        
        if ($cachedProgress) {
            return $cachedProgress;
        }

        // Fall back to database
        $progress = FileProcessingProgress::find($progressId);
        
        if (!$progress || $progress->user_id !== Auth::id()) {
            return null;
        }

        return [
            'id' => $progress->id,
            'status' => $progress->status,
            'progress_percent' => $progress->getProgressPercentage(),
            'message' => $progress->message,
            'current_step_description' => $progress->getCurrentStepDescription(),
            'elapsed_time' => $progress->getElapsedTime(),
            'is_active' => $progress->isActive(),
            'is_completed' => $progress->isCompleted(),
            'has_failed' => $progress->hasFailed(),
            'error_message' => $progress->error_message,
            'result_data' => $progress->result_data,
            'updated_at' => $progress->updated_at->toISOString()
        ];
    }

    /**
     * Get completed analysis results
     */
    public function getAnalysisResults(string $progressId): ?WorksheetAnalysis
    {
        $progress = FileProcessingProgress::find($progressId);
        
        if (!$progress || 
            $progress->user_id !== Auth::id() || 
            !$progress->isCompleted() ||
            $progress->processing_type !== FileProcessingProgress::TYPE_FILE_ANALYSIS) {
            return null;
        }

        $resultData = $progress->result_data;
        
        if (!isset($resultData['analysis']['worksheets'])) {
            return null;
        }

        // Reconstruct WorksheetAnalysis from stored data
        $worksheets = [];
        foreach ($resultData['analysis']['worksheets'] as $worksheetData) {
            $worksheets[] = new WorksheetInfo(
                index: $worksheetData['index'],
                name: $worksheetData['name'],
                headers: $worksheetData['headers'],
                rowCount: $worksheetData['rows'],
                preview: $worksheetData['preview']
            );
        }

        return new WorksheetAnalysis($worksheets);
    }

    /**
     * Get completed data loading results
     */
    public function getDataResults(string $progressId): ?array
    {
        $progress = FileProcessingProgress::find($progressId);
        
        if (!$progress || 
            $progress->user_id !== Auth::id() || 
            !$progress->isCompleted()) {
            return null;
        }

        $resultData = $progress->result_data;

        // Handle different types of data results
        switch ($progress->processing_type) {
            case FileProcessingProgress::TYPE_SAMPLE_DATA:
                return $resultData['sample_data'] ?? [];
                
            case FileProcessingProgress::TYPE_DRY_RUN_DATA:
                return $this->loadTemporaryData($resultData['validation_data_key'] ?? null);
                
            case FileProcessingProgress::TYPE_FULL_IMPORT_DATA:
                return $this->loadTemporaryData($resultData['import_data_key'] ?? null);
                
            default:
                return null;
        }
    }

    /**
     * Synchronous method for quick header extraction (fallback for immediate needs)
     */
    public function extractHeadersSync(UploadedFile $file, array $selectedWorksheets): array
    {
        $filePath = $file->getRealPath();
        
        try {
            Log::info('Extracting headers synchronously', [
                'file' => $file->getClientOriginalName(),
                'worksheets' => count($selectedWorksheets)
            ]);

            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            // Get worksheet names
            if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'csv') {
                $worksheetNames = ['Sheet1'];
            } else {
                $worksheetNames = $reader->listWorksheetNames($filePath);
            }

            // Use first selected worksheet
            $firstWorksheetIndex = $selectedWorksheets[0];
            $firstWorksheetName = $worksheetNames[$firstWorksheetIndex];

            // Load only the specific worksheet
            if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'csv') {
                $reader->setLoadSheetsOnly([$firstWorksheetName]);
            }

            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Extract headers efficiently
            $headers = $this->extractHeaders($worksheet);
            
            // Get a small sample of data for preview
            $sampleData = $this->peekSampleData($worksheet, $headers, 3);

            // Clean up
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return [
                'headers' => $headers,
                'sampleData' => $sampleData
            ];

        } catch (\Exception $e) {
            Log::error('Synchronous header extraction failed', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception('Failed to extract headers: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a running job
     */
    public function cancelJob(string $progressId): bool
    {
        $progress = FileProcessingProgress::find($progressId);
        
        if (!$progress || $progress->user_id !== Auth::id()) {
            return false;
        }

        if ($progress->isActive()) {
            $progress->updateStatus(FileProcessingProgress::STATUS_CANCELLED, 'Job cancelled by user');
            
            // Note: We can't actually stop the running job, but we mark it as cancelled
            // The job should check for cancellation status periodically
            
            Log::info('Job cancellation requested', [
                'progress_id' => $progressId,
                'user_id' => Auth::id()
            ]);
            
            return true;
        }

        return false;
    }

    /**
     * Clean up temporary files and old progress records
     */
    public function cleanup(int $daysOld = 1): void
    {
        // Clean up old progress records
        $deletedRecords = FileProcessingProgress::cleanupOldRecords($daysOld);
        
        // Clean up temporary files
        $tempFiles = Storage::disk('local')->files('temp_uploads');
        $deletedFiles = 0;
        
        foreach ($tempFiles as $file) {
            $lastModified = Storage::disk('local')->lastModified($file);
            if ($lastModified < now()->subDays($daysOld)->timestamp) {
                Storage::disk('local')->delete($file);
                $deletedFiles++;
            }
        }
        
        // Clean up temporary data files
        $tempDataFiles = Storage::disk('local')->files('temp_data');
        $deletedDataFiles = 0;
        
        foreach ($tempDataFiles as $file) {
            $lastModified = Storage::disk('local')->lastModified($file);
            if ($lastModified < now()->subDays($daysOld)->timestamp) {
                Storage::disk('local')->delete($file);
                $deletedDataFiles++;
            }
        }

        Log::info('Cleanup completed', [
            'deleted_progress_records' => $deletedRecords,
            'deleted_temp_files' => $deletedFiles,
            'deleted_data_files' => $deletedDataFiles,
            'days_old' => $daysOld
        ]);
    }

    /**
     * Store uploaded file temporarily for background processing
     */
    private function storeFileTemporarily(UploadedFile $file): string
    {
        $fileName = 'temp_uploads/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($fileName, Storage::disk('local'));
        
        Log::info('File stored temporarily', [
            'original_name' => $file->getClientOriginalName(),
            'temp_path' => $path,
            'size' => $file->getSize()
        ]);
        
        return $path;
    }

    /**
     * Load temporary data from storage
     */
    private function loadTemporaryData(?string $dataKey): ?array
    {
        if (!$dataKey) {
            return null;
        }

        $fileName = "temp_data/{$dataKey}.json";
        
        if (!Storage::disk('local')->exists($fileName)) {
            Log::warning('Temporary data file not found', ['key' => $dataKey]);
            return null;
        }

        try {
            $jsonContent = Storage::disk('local')->get($fileName);
            return json_decode($jsonContent, true);
        } catch (\Exception $e) {
            Log::error('Failed to load temporary data', [
                'key' => $dataKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get active jobs for current user
     */
    public function getActiveJobs(): array
    {
        $activeProgress = FileProcessingProgress::getActiveForUser(Auth::id());
        
        return $activeProgress->map(function ($progress) {
            return [
                'id' => $progress->id,
                'file_name' => $progress->file_name,
                'processing_type' => $progress->processing_type,
                'status' => $progress->status,
                'progress_percent' => $progress->getProgressPercentage(),
                'message' => $progress->message,
                'elapsed_time' => $progress->getElapsedTime(),
                'created_at' => $progress->created_at->toISOString()
            ];
        })->toArray();
    }
}