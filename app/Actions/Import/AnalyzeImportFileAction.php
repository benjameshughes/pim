<?php

namespace App\Actions\Import;

use App\Exceptions\Import\FileNotFoundException;
use App\Exceptions\Import\FileSizeException;
use App\Exceptions\Import\InvalidFileFormatException;
use App\Exceptions\Import\SecurityException;
use App\Rules\SafeFileContentRule;
use App\Services\ImportDataCacheService;
use App\Services\ImportManagerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AnalyzeImportFileAction
{
    public function __construct(
        private ImportManagerService $importManager,
        private ImportDataCacheService $cacheService
    ) {}

    /**
     * Execute file analysis with comprehensive validation
     */
    public function execute(UploadedFile $file): string
    {
        Log::info('Starting file analysis', [
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        // Step 1: Basic file validation
        $this->validateFileBasics($file);

        // Step 2: Security validation
        $this->validateFileSecurity($file);

        // Step 3: File integrity check
        $this->validateFileIntegrity($file);

        // Step 4: Analyze file structure
        try {
            $analysis = $this->importManager->analyzeFile($file);
            $analysisArray = $analysis->toArray();

            // Step 5: Cache the results
            $cacheKey = $this->cacheService->storeWorksheetAnalysis($analysisArray);

            Log::info('File analysis completed successfully', [
                'cache_key' => $cacheKey,
                'worksheets_found' => count($analysisArray['worksheets'] ?? []),
            ]);

            return $cacheKey;

        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            throw new InvalidFileFormatException(
                $file->getClientOriginalExtension(),
                $file->getClientOriginalName(),
                $e
            );
        } catch (\Exception $e) {
            Log::error('Unexpected error during file analysis', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filename' => $file->getClientOriginalName(),
            ]);

            throw new \Exception('Failed to analyze file: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate basic file properties
     */
    private function validateFileBasics(UploadedFile $file): void
    {
        // Check if file exists and is readable
        if (! $file->isValid()) {
            throw new FileNotFoundException($file->getClientOriginalName());
        }

        // Check file size
        $maxSize = 100 * 1024 * 1024; // 100MB
        if ($file->getSize() > $maxSize) {
            throw new FileSizeException($file->getSize(), $maxSize);
        }

        // Check file extension
        $allowedExtensions = ['xlsx', 'xls', 'csv'];
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, $allowedExtensions)) {
            throw new InvalidFileFormatException($extension, $file->getClientOriginalName());
        }
    }

    /**
     * Validate file security
     */
    private function validateFileSecurity(UploadedFile $file): void
    {
        $validator = Validator::make(
            ['file' => $file],
            ['file' => [new SafeFileContentRule]]
        );

        if ($validator->fails()) {
            $error = $validator->errors()->first('file');
            throw new SecurityException($error, $file->getClientOriginalName());
        }
    }

    /**
     * Validate file integrity
     */
    private function validateFileIntegrity(UploadedFile $file): void
    {
        $filePath = $file->getRealPath();

        // Check if file is readable
        if (! is_readable($filePath)) {
            throw new FileNotFoundException($file->getClientOriginalName());
        }

        // Check if file has content
        if (filesize($filePath) === 0) {
            throw new InvalidFileFormatException(
                'empty',
                $file->getClientOriginalName()
            );
        }

        // For Excel files, do a quick structure check
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, ['xlsx', 'xls'])) {
            $this->validateExcelStructure($filePath, $extension);
        }
    }

    /**
     * Basic Excel file structure validation
     */
    private function validateExcelStructure(string $filePath, string $extension): void
    {
        try {
            // Try to create a reader for the file
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);

            // Set read-only mode for faster validation
            $reader->setReadDataOnly(true);

            // Try to get worksheet names (this will fail if file is corrupted)
            if ($extension !== 'csv') {
                $worksheetNames = $reader->listWorksheetNames($filePath);

                if (empty($worksheetNames)) {
                    throw new InvalidFileFormatException($extension, basename($filePath));
                }
            }

        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            throw new InvalidFileFormatException($extension, basename($filePath), $e);
        }
    }
}
