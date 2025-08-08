<?php

namespace App\Services;

use App\DTOs\Import\SecurityValidationResult;
use Illuminate\Support\Facades\Log;

/**
 * Security validation service for Excel import data
 * Prevents injection attacks, validates file integrity, and sanitizes dangerous content
 */
class ImportSecurityService
{
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB

    private const MAX_ROWS_PER_SHEET = 100000;

    private const MAX_COLUMNS_PER_SHEET = 100;

    private const MAX_CELL_LENGTH = 65535;

    private array $dangerousPatterns;

    private array $allowedFileTypes;

    private array $suspiciousStrings;

    public function __construct()
    {
        $this->initializeSecurityPatterns();
    }

    /**
     * Perform comprehensive security validation on uploaded file
     */
    public function validateFileUpload($file): SecurityValidationResult
    {
        $result = new SecurityValidationResult;

        Log::info('Starting security validation', [
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        // File size validation
        $this->validateFileSize($file, $result);

        // File type validation
        $this->validateFileType($file, $result);

        // File name validation
        $this->validateFileName($file->getClientOriginalName(), $result);

        // File content validation
        $this->validateFileContent($file, $result);

        return $result;
    }

    /**
     * Validate import data for security threats
     */
    public function validateImportData(array $importData): SecurityValidationResult
    {
        $result = new SecurityValidationResult;

        Log::info('Starting import data security validation', [
            'total_rows' => count($importData),
        ]);

        foreach ($importData as $rowIndex => $rowData) {
            $this->validateRowSecurity($rowData, $rowIndex + 1, $result);
        }

        Log::info('Import data security validation completed', [
            'threats_found' => $result->getThreatCount(),
            'warnings' => count($result->getWarnings()),
        ]);

        return $result;
    }

    /**
     * Sanitize potentially dangerous content while preserving legitimate data
     */
    public function sanitizeImportData(array $importData): array
    {
        $sanitized = [];

        foreach ($importData as $rowIndex => $rowData) {
            $sanitizedRow = [];

            foreach ($rowData as $field => $value) {
                $sanitizedRow[$field] = $this->sanitizeValue($value, $field);
            }

            $sanitized[] = $sanitizedRow;
        }

        return $sanitized;
    }

    /**
     * Validate file size
     */
    private function validateFileSize($file, SecurityValidationResult $result): void
    {
        $fileSize = $file->getSize();

        if ($fileSize > self::MAX_FILE_SIZE) {
            $result->addThreat(
                'file_size_exceeded',
                "File size ({$fileSize} bytes) exceeds maximum allowed size (".self::MAX_FILE_SIZE.' bytes)',
                'high'
            );
        }

        if ($fileSize === 0) {
            $result->addThreat(
                'empty_file',
                'File appears to be empty',
                'medium'
            );
        }
    }

    /**
     * Validate file type and MIME type
     */
    private function validateFileType($file, SecurityValidationResult $result): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        if (! in_array($extension, $this->allowedFileTypes)) {
            $result->addThreat(
                'invalid_file_type',
                "File extension '{$extension}' is not allowed",
                'high'
            );
        }

        // Check for MIME type spoofing
        $allowedMimeTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'text/csv',
            'text/plain',
        ];

        if (! in_array($mimeType, $allowedMimeTypes)) {
            $result->addThreat(
                'mime_type_mismatch',
                "MIME type '{$mimeType}' doesn't match expected types for spreadsheet files",
                'medium'
            );
        }
    }

    /**
     * Validate file name for path traversal and dangerous characters
     */
    private function validateFileName(string $filename, SecurityValidationResult $result): void
    {
        // Check for path traversal attempts
        if (strpos($filename, '../') !== false || strpos($filename, '..\\') !== false) {
            $result->addThreat(
                'path_traversal_attempt',
                'Filename contains path traversal sequences',
                'high'
            );
        }

        // Check for dangerous characters
        $dangerousChars = ['<', '>', ':', '"', '|', '?', '*', "\0"];
        foreach ($dangerousChars as $char) {
            if (strpos($filename, $char) !== false) {
                $result->addThreat(
                    'dangerous_filename_character',
                    "Filename contains dangerous character: '{$char}'",
                    'medium'
                );
                break;
            }
        }

        // Check filename length
        if (strlen($filename) > 255) {
            $result->addThreat(
                'filename_too_long',
                'Filename exceeds maximum length of 255 characters',
                'medium'
            );
        }
    }

    /**
     * Validate file content structure
     */
    private function validateFileContent($file, SecurityValidationResult $result): void
    {
        try {
            // Basic file header validation
            $handle = fopen($file->getRealPath(), 'rb');
            $header = fread($handle, 512);
            fclose($handle);

            // Check for executable file signatures
            $executableSignatures = [
                "\x4D\x5A", // PE executable
                "\x7F\x45\x4C\x46", // ELF executable
                "\xFE\xED\xFA", // Mach-O executable
            ];

            foreach ($executableSignatures as $signature) {
                if (strpos($header, $signature) === 0) {
                    $result->addThreat(
                        'executable_file_detected',
                        'File appears to be an executable, not a spreadsheet',
                        'critical'
                    );
                    break;
                }
            }

        } catch (\Exception $e) {
            $result->addWarning('Could not validate file content structure: '.$e->getMessage());
        }
    }

    /**
     * Validate individual row for security threats
     */
    private function validateRowSecurity(array $rowData, int $rowNumber, SecurityValidationResult $result): void
    {
        foreach ($rowData as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $stringValue = (string) $value;

            // Check cell length
            if (strlen($stringValue) > self::MAX_CELL_LENGTH) {
                $result->addThreat(
                    'cell_too_large',
                    "Row {$rowNumber}, field '{$field}': Cell content exceeds maximum length",
                    'medium'
                );
            }

            // Check for injection patterns
            $this->checkForInjectionPatterns($stringValue, $field, $rowNumber, $result);

            // Check for suspicious strings
            $this->checkForSuspiciousContent($stringValue, $field, $rowNumber, $result);

            // Check for binary content
            if (! mb_check_encoding($stringValue, 'UTF-8')) {
                $result->addThreat(
                    'binary_content_detected',
                    "Row {$rowNumber}, field '{$field}': Contains binary or invalid UTF-8 content",
                    'high'
                );
            }
        }
    }

    /**
     * Check for various injection attack patterns
     */
    private function checkForInjectionPatterns(string $value, string $field, int $rowNumber, SecurityValidationResult $result): void
    {
        foreach ($this->dangerousPatterns as $patternType => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern['regex'], $value)) {
                    $result->addThreat(
                        $patternType,
                        "Row {$rowNumber}, field '{$field}': {$pattern['description']}",
                        $pattern['severity']
                    );
                }
            }
        }
    }

    /**
     * Check for suspicious content that might indicate malicious intent
     */
    private function checkForSuspiciousContent(string $value, string $field, int $rowNumber, SecurityValidationResult $result): void
    {
        $lowerValue = strtolower($value);

        foreach ($this->suspiciousStrings as $suspicious) {
            if (strpos($lowerValue, strtolower($suspicious)) !== false) {
                $result->addWarning(
                    "Row {$rowNumber}, field '{$field}': Contains potentially suspicious content: '{$suspicious}'"
                );
            }
        }
    }

    /**
     * Sanitize a single value while preserving legitimate content
     */
    private function sanitizeValue($value, string $field): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $stringValue = (string) $value;

        // Remove null bytes
        $stringValue = str_replace("\0", '', $stringValue);

        // Handle specific field types
        switch ($field) {
            case 'image_urls':
                return $this->sanitizeUrls($stringValue);

            case 'description':
                return $this->sanitizeText($stringValue);

            case 'variant_sku':
                return $this->sanitizeSku($stringValue);

            case 'barcode':
                return $this->sanitizeBarcode($stringValue);

            default:
                return $this->sanitizeGeneral($stringValue);
        }
    }

    /**
     * Sanitize URLs
     */
    private function sanitizeUrls(string $value): string
    {
        $urls = explode(',', $value);
        $sanitizedUrls = [];

        foreach ($urls as $url) {
            $url = trim($url);

            // Basic URL validation
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $parsed = parse_url($url);

                // Only allow HTTP and HTTPS
                if (in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
                    $sanitizedUrls[] = $url;
                }
            }
        }

        return implode(',', $sanitizedUrls);
    }

    /**
     * Sanitize text content
     */
    private function sanitizeText(string $value): string
    {
        // Remove dangerous HTML/script tags
        $value = strip_tags($value);

        // Decode HTML entities
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove control characters except newlines and tabs
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        return $value;
    }

    /**
     * Sanitize SKU values
     */
    private function sanitizeSku(string $value): string
    {
        // Only allow alphanumeric characters and hyphens
        return preg_replace('/[^a-zA-Z0-9\-]/', '', $value);
    }

    /**
     * Sanitize barcode values
     */
    private function sanitizeBarcode(string $value): string
    {
        // Only allow numeric characters
        return preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * General sanitization for most fields
     */
    private function sanitizeGeneral(string $value): string
    {
        // Remove control characters
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        // Normalize whitespace
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    /**
     * Initialize security patterns and configurations
     */
    private function initializeSecurityPatterns(): void
    {
        $this->dangerousPatterns = [
            'sql_injection' => [
                [
                    'regex' => '/\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|CREATE|ALTER)\b/i',
                    'description' => 'Potential SQL injection detected',
                    'severity' => 'high',
                ],
                [
                    'regex' => '/(\'\s*(OR|AND)\s*\'\s*=\s*\')|(\'\s*;\s*--)/i',
                    'description' => 'SQL injection pattern detected',
                    'severity' => 'high',
                ],
            ],
            'script_injection' => [
                [
                    'regex' => '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i',
                    'description' => 'Script tag detected',
                    'severity' => 'high',
                ],
                [
                    'regex' => '/javascript\s*:/i',
                    'description' => 'JavaScript protocol detected',
                    'severity' => 'high',
                ],
            ],
            'command_injection' => [
                [
                    'regex' => '/(\||\&\&|\;|\$\(|\`)/i',
                    'description' => 'Command injection characters detected',
                    'severity' => 'medium',
                ],
            ],
            'path_traversal' => [
                [
                    'regex' => '/(\.\.[\/\\\\]|\.\.[\/\\\\])/i',
                    'description' => 'Path traversal pattern detected',
                    'severity' => 'high',
                ],
            ],
        ];

        $this->allowedFileTypes = ['xlsx', 'xls', 'csv'];

        $this->suspiciousStrings = [
            'eval(', 'exec(', 'system(', 'shell_exec',
            'file_get_contents', 'include', 'require',
            'base64_decode', 'gzinflate', 'str_rot13',
        ];
    }
}
