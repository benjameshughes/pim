<?php

namespace App\Services\Import;

use App\Models\ImportSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportBuilder
{
    private ImportConfigurationBuilder $configBuilder;

    public function __construct()
    {
        $this->configBuilder = new ImportConfigurationBuilder();
    }

    public static function create(): self
    {
        return new self();
    }

    public function fromFile(UploadedFile $file): self
    {
        $this->configBuilder->fromFile($file);
        return $this;
    }

    public function withMode(string $mode): self
    {
        $this->configBuilder->withMode($mode);
        return $this;
    }

    public function extractAttributes(bool $enabled = true): self
    {
        $this->configBuilder->extractAttributes($enabled);
        return $this;
    }

    public function detectMadeToMeasure(bool $enabled = true): self
    {
        $this->configBuilder->detectMadeToMeasure($enabled);
        return $this;
    }

    public function dimensionsDigitsOnly(bool $enabled = true): self
    {
        $this->configBuilder->dimensionsDigitsOnly($enabled);
        return $this;
    }

    public function groupBySku(bool $enabled = true): self
    {
        $this->configBuilder->groupBySku($enabled);
        return $this;
    }

    public function autoGenerateParents(bool $enabled = true): self
    {
        $this->configBuilder->autoGenerateParents($enabled);
        return $this;
    }

    public function assignBarcodes(bool $enabled = true): self
    {
        $this->configBuilder->assignBarcodes($enabled);
        return $this;
    }

    public function processInBackground(bool $enabled = true): self
    {
        $this->configBuilder->processInBackground($enabled);
        return $this;
    }

    public function withChunkSize(int $size): self
    {
        $this->configBuilder->withChunkSize($size);
        return $this;
    }

    public function withMaxProcessingTime(int $minutes): self
    {
        $this->configBuilder->withMaxProcessingTime($minutes);
        return $this;
    }

    public function withExtractionSettings(array $settings): self
    {
        $this->configBuilder->withExtractionSettings($settings);
        return $this;
    }

    public function withValidationRules(array $rules): self
    {
        $this->configBuilder->withValidationRules($rules);
        return $this;
    }

    /**
     * Execute the import and return an ImportSession
     */
    public function execute(): ImportSession
    {
        $configuration = $this->configBuilder->build();
        
        // Store the file
        $filePath = $this->storeFile($configuration->getFile());
        
        // Create import session
        $session = ImportSession::create([
            'user_id' => auth()->id(),
            'original_filename' => $configuration->getFile()->getClientOriginalName(),
            'file_path' => $filePath,
            'file_type' => $configuration->getFile()->getClientOriginalExtension(),
            'file_size' => $configuration->getFile()->getSize(),
            'file_hash' => hash_file('sha256', $configuration->getFile()->getRealPath()),
            'status' => 'initializing',
            'configuration' => $configuration->toArray(),
        ]);

        // Dispatch analyze file job if it exists
        if (class_exists(\App\Jobs\Import\AnalyzeFileJob::class)) {
            \App\Jobs\Import\AnalyzeFileJob::dispatch($session)->onQueue('imports');
        }

        return $session;
    }

    /**
     * Execute synchronously (for testing or small files)
     */
    public function executeSync(): ImportSession
    {
        $this->configBuilder->processInBackground(false);
        return $this->execute();
    }

    /**
     * Store the uploaded file securely
     */
    private function storeFile(UploadedFile $file): string
    {
        $directory = 'imports/' . now()->format('Y/m/d');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        
        return $file->storeAs($directory, $filename, 'local');
    }

    /**
     * Validate file before processing
     */
    public function validateFile(): array
    {
        $configuration = $this->configBuilder->build();
        $file = $configuration->getFile();
        $errors = [];

        // Check file size (max 100MB)
        if ($file->getSize() > 100 * 1024 * 1024) {
            $errors[] = 'File size cannot exceed 100MB';
        }

        // Check file type
        $allowedExtensions = ['xlsx', 'csv'];
        if (!in_array(strtolower($file->getClientOriginalExtension()), $allowedExtensions)) {
            $errors[] = 'File must be Excel (.xlsx) or CSV (.csv) format';
        }

        // Check if file is readable
        if (!is_readable($file->getRealPath())) {
            $errors[] = 'File is not readable';
        }

        // Basic file integrity check
        try {
            if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
                // Try to open with PhpSpreadsheet
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
                $reader->setReadDataOnly(true);
                $reader->setReadEmptyCells(false);
                $reader->listWorksheetNames($file->getRealPath());
            } else {
                // Try to read CSV
                $handle = fopen($file->getRealPath(), 'r');
                if ($handle) {
                    fgetcsv($handle); // Try to read first line
                    fclose($handle);
                }
            }
        } catch (\Exception $e) {
            $errors[] = 'File appears to be corrupted or invalid: ' . $e->getMessage();
        }

        return $errors;
    }

    /**
     * Get a preview of the file contents for column mapping
     */
    public function getFilePreview(int $maxRows = 5): array
    {
        $configuration = $this->configBuilder->build();
        $file = $configuration->getFile();
        
        try {
            if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
                return $this->getExcelPreview($file, $maxRows);
            } else {
                return $this->getCsvPreview($file, $maxRows);
            }
        } catch (\Exception $e) {
            return [
                'error' => 'Could not preview file: ' . $e->getMessage(),
                'worksheets' => [],
            ];
        }
    }

    private function getExcelPreview(UploadedFile $file, int $maxRows): array
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        
        $worksheetNames = $reader->listWorksheetNames($file->getRealPath());
        $worksheets = [];
        
        foreach ($worksheetNames as $index => $name) {
            $reader->setLoadSheetsOnly([$name]);
            $spreadsheet = $reader->load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            
            $data = [];
            $highestRow = min($worksheet->getHighestRow(), $maxRows + 1); // +1 for header
            
            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                $highestColumn = $worksheet->getHighestColumn();
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $cellValue = $worksheet->getCell($col . $row)->getCalculatedValue();
                    $rowData[] = (string) $cellValue;
                }
                $data[] = $rowData;
            }
            
            $worksheets[] = [
                'index' => $index,
                'name' => $name,
                'headers' => $data[0] ?? [],
                'sample_data' => array_slice($data, 1),
                'total_rows' => $worksheet->getHighestRow() - 1, // Exclude header
            ];
            
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
        
        return [
            'worksheets' => $worksheets,
            'file_type' => 'xlsx',
        ];
    }

    private function getCsvPreview(UploadedFile $file, int $maxRows): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            throw new \Exception('Could not open CSV file');
        }
        
        $data = [];
        $rowCount = 0;
        
        while (($row = fgetcsv($handle)) !== false && $rowCount <= $maxRows) {
            $data[] = $row;
            $rowCount++;
        }
        
        fclose($handle);
        
        // Count total rows
        $totalRows = count(file($file->getRealPath())) - 1; // Exclude header
        
        return [
            'worksheets' => [
                [
                    'index' => 0,
                    'name' => 'CSV Data',
                    'headers' => $data[0] ?? [],
                    'sample_data' => array_slice($data, 1),
                    'total_rows' => $totalRows,
                ]
            ],
            'file_type' => 'csv',
        ];
    }
}