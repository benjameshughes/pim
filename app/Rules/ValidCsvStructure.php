<?php

namespace App\Rules;

use App\Constants\ImportDefaults;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * 📊✨ CSV STRUCTURE VALIDATION RULE ✨📊
 *
 * Custom Laravel validation rule for validating CSV file structure,
 * ensuring files have proper headers and data format
 */
class ValidCsvStructure implements ValidationRule
{
    /**
     * 🔍 Run the validation rule
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The file must be a valid upload.');

            return;
        }

        // Skip validation for Excel files as they have different structure requirements
        if ($this->isExcelFile($value)) {
            return;
        }

        try {
            $analysis = $this->analyzeFile($value);

            if (! $analysis['is_valid']) {
                $fail($analysis['error_message']);

                return;
            }

            // Additional structural validations
            $this->validateHeaders($analysis, $fail);
            $this->validateDataRows($analysis, $fail);
            $this->validateFileSize($analysis, $fail);

        } catch (\Exception $e) {
            $fail('Unable to analyze file structure: '.$e->getMessage());
        }
    }

    /**
     * 📊 Analyze CSV file structure
     */
    private function analyzeFile(UploadedFile $file): array
    {
        $analysis = [
            'is_valid' => false,
            'error_message' => '',
            'headers' => [],
            'row_count' => 0,
            'header_count' => 0,
            'has_data' => false,
            'encoding' => 'UTF-8',
            'delimiter' => ',',
        ];

        try {
            $handle = fopen($file->getPathname(), 'r');

            if (! $handle) {
                $analysis['error_message'] = 'Unable to open file for reading.';

                return $analysis;
            }

            // Detect delimiter and encoding
            $analysis['delimiter'] = $this->detectDelimiter($handle);
            rewind($handle);

            // Read and validate headers
            $headers = fgetcsv($handle, 0, $analysis['delimiter']);

            if ($headers === false || empty($headers)) {
                $analysis['error_message'] = 'File appears to be empty or has no readable headers.';
                fclose($handle);

                return $analysis;
            }

            $analysis['headers'] = array_map('trim', $headers);
            $analysis['header_count'] = count(array_filter($analysis['headers']));

            // Count data rows and check for content
            $dataRows = 0;
            $hasActualData = false;

            while (($row = fgetcsv($handle, 0, $analysis['delimiter'])) !== false) {
                $dataRows++;

                // Check if row has meaningful data (not just empty cells)
                $nonEmptyValues = array_filter($row, fn ($cell) => ! empty(trim($cell)));
                if (! empty($nonEmptyValues)) {
                    $hasActualData = true;
                }

                // Don't process too many rows for validation
                if ($dataRows > 100) {
                    break;
                }
            }

            $analysis['row_count'] = $dataRows;
            $analysis['has_data'] = $hasActualData;
            $analysis['is_valid'] = true;

            fclose($handle);

        } catch (\Exception $e) {
            $analysis['error_message'] = 'Error reading file: '.$e->getMessage();
            if (isset($handle)) {
                fclose($handle);
            }
        }

        return $analysis;
    }

    /**
     * 🔍 Detect CSV delimiter
     */
    private function detectDelimiter($handle): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $firstLine = fgets($handle);

        $delimiter = ','; // default
        $maxCount = 0;

        foreach ($delimiters as $d) {
            $count = substr_count($firstLine, $d);
            if ($count > $maxCount) {
                $maxCount = $count;
                $delimiter = $d;
            }
        }

        rewind($handle);

        return $delimiter;
    }

    /**
     * 📋 Validate headers structure
     */
    private function validateHeaders(array $analysis, Closure $fail): void
    {
        // Check minimum header count
        if ($analysis['header_count'] < ImportDefaults::MIN_HEADER_COUNT) {
            $fail('File must have at least '.ImportDefaults::MIN_HEADER_COUNT.' valid header(s).');

            return;
        }

        // Check maximum header count
        if ($analysis['header_count'] > ImportDefaults::MAX_HEADER_COUNT) {
            $fail('File has too many headers. Maximum allowed: '.ImportDefaults::MAX_HEADER_COUNT.'.');

            return;
        }

        // Check for empty or invalid headers
        $emptyHeaders = array_filter($analysis['headers'], fn ($header) => empty(trim($header)));
        if (count($emptyHeaders) > 0) {
            $fail('File contains empty header columns. Please ensure all columns have valid names.');

            return;
        }

        // Check for duplicate headers
        $uniqueHeaders = array_unique($analysis['headers']);
        if (count($uniqueHeaders) !== count($analysis['headers'])) {
            $duplicates = array_diff_assoc($analysis['headers'], $uniqueHeaders);
            $fail('File contains duplicate header names: '.implode(', ', array_unique($duplicates)));

            return;
        }

        // Check header length
        $longHeaders = array_filter($analysis['headers'],
            fn ($header) => strlen($header) > ImportDefaults::MAX_FIELD_NAME_LENGTH
        );
        if (! empty($longHeaders)) {
            $fail('Some headers are too long. Maximum length: '.ImportDefaults::MAX_FIELD_NAME_LENGTH.' characters.');

            return;
        }
    }

    /**
     * 📊 Validate data rows
     */
    private function validateDataRows(array $analysis, Closure $fail): void
    {
        // Check if file has data rows
        if ($analysis['row_count'] === 0) {
            $fail('File contains no data rows. Please ensure the file has data beyond the header row.');

            return;
        }

        // Check if data rows have meaningful content
        if (! $analysis['has_data']) {
            $fail('File appears to contain only empty rows. Please ensure your data has actual values.');

            return;
        }

        // Check maximum row limit for large files
        if ($analysis['row_count'] > ImportDefaults::MAX_ROWS_PER_IMPORT) {
            $fail('File is too large. Maximum allowed rows: '.number_format(ImportDefaults::MAX_ROWS_PER_IMPORT).'.');

            return;
        }
    }

    /**
     * 📏 Validate file size constraints
     */
    private function validateFileSize(array $analysis, Closure $fail): void
    {
        $estimatedMemoryUsage = $analysis['row_count'] * $analysis['header_count'] * 100; // rough estimate
        $maxMemoryUsage = 50 * 1024 * 1024; // 50MB

        if ($estimatedMemoryUsage > $maxMemoryUsage) {
            $fail('File is too complex to process. Please split into smaller files or reduce the number of columns.');

            return;
        }
    }

    /**
     * 🔍 Check if file is Excel format
     */
    private function isExcelFile(UploadedFile $file): bool
    {
        return in_array(
            strtolower($file->getClientOriginalExtension()),
            ['xlsx', 'xls']
        );
    }

    /**
     * 📋 Get validation suggestions based on file analysis
     */
    public function getValidationSuggestions(UploadedFile $file): array
    {
        try {
            $analysis = $this->analyzeFile($file);

            return [
                'delimiter' => $analysis['delimiter'],
                'encoding' => $analysis['encoding'],
                'estimated_processing_time' => $this->estimateProcessingTime($analysis['row_count']),
                'suggested_chunk_size' => $this->suggestChunkSize($analysis['row_count']),
                'warnings' => $this->getWarnings($analysis),
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Unable to analyze file: '.$e->getMessage(),
            ];
        }
    }

    /**
     * ⏱️ Estimate processing time
     */
    private function estimateProcessingTime(int $rows): int
    {
        // Rough estimate: 100 rows per second
        return max(5, ceil($rows / 100));
    }

    /**
     * 📦 Suggest optimal chunk size
     */
    private function suggestChunkSize(int $rows): int
    {
        return match (true) {
            $rows > 10000 => ImportDefaults::MAX_CHUNK_SIZE,
            $rows > 1000 => 500,
            default => ImportDefaults::DEFAULT_CHUNK_SIZE
        };
    }

    /**
     * ⚠️ Get warnings about the file
     */
    private function getWarnings(array $analysis): array
    {
        $warnings = [];

        if ($analysis['row_count'] > 5000) {
            $warnings[] = 'Large file detected. Processing may take several minutes.';
        }

        if ($analysis['header_count'] > 20) {
            $warnings[] = 'Many columns detected. Consider mapping only necessary fields.';
        }

        if ($analysis['delimiter'] !== ',') {
            $warnings[] = 'Non-standard delimiter detected ('.$analysis['delimiter'].').';
        }

        return $warnings;
    }
}
