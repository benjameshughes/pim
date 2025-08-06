<?php

namespace App\Services;

use App\DTOs\Import\TransformedRow;
use App\DTOs\Import\DataTransformationResult;
use App\Exceptions\Import\DataTransformationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Production-ready data transformation service for Excel imports
 * Handles sanitization, validation, type casting, and encoding normalization
 */
class DataTransformationService
{
    private const MAX_STRING_LENGTH = 255;
    private const MAX_TEXT_LENGTH = 65535;
    private const DECIMAL_PRECISION = 2;
    
    private array $transformationRules;
    private array $sanitizationConfig;
    
    public function __construct()
    {
        $this->initializeTransformationRules();
        $this->initializeSanitizationConfig();
    }
    
    /**
     * Transform and sanitize raw Excel data into clean, validated format
     */
    public function transformImportData(array $rawData): DataTransformationResult
    {
        $result = new DataTransformationResult();
        $transformedRows = [];
        
        Log::info('Starting data transformation', [
            'total_rows' => count($rawData),
            'transformation_rules' => count($this->transformationRules)
        ]);
        
        foreach ($rawData as $index => $rawRow) {
            try {
                $transformedRow = $this->transformSingleRow($rawRow, $index + 1);
                $transformedRows[] = $transformedRow;
                $result->incrementSuccessCount();
                
            } catch (DataTransformationException $e) {
                $result->addError($index + 1, $e->getMessage(), $rawRow);
                Log::warning('Row transformation failed', [
                    'row_number' => $index + 1,
                    'error' => $e->getMessage(),
                    'raw_data' => $this->sanitizeForLogging($rawRow)
                ]);
            }
        }
        
        $result->setTransformedData($transformedRows);
        
        Log::info('Data transformation completed', [
            'successful_rows' => $result->getSuccessCount(),
            'failed_rows' => $result->getErrorCount(),
            'total_errors' => count($result->getErrors())
        ]);
        
        return $result;
    }
    
    /**
     * Transform a single row of data with comprehensive sanitization
     */
    private function transformSingleRow(array $rawRow, int $rowNumber): TransformedRow
    {
        $transformed = new TransformedRow($rowNumber);
        
        foreach ($rawRow as $field => $value) {
            if (!isset($this->transformationRules[$field])) {
                // Skip unmapped fields but log for debugging
                Log::debug("Unmapped field encountered", ['field' => $field, 'row' => $rowNumber]);
                continue;
            }
            
            $rule = $this->transformationRules[$field];
            
            try {
                $cleanValue = $this->sanitizeValue($value, $rule);
                $typedValue = $this->castValue($cleanValue, $rule);
                $validatedValue = $this->validateValue($typedValue, $rule, $field);
                
                $transformed->setField($field, $validatedValue);
                
            } catch (\Exception $e) {
                throw new DataTransformationException(
                    "Field '{$field}' transformation failed: {$e->getMessage()}"
                );
            }
        }
        
        // Run cross-field validation
        $this->validateCrossFieldRules($transformed);
        
        return $transformed;
    }
    
    /**
     * Comprehensive value sanitization
     */
    private function sanitizeValue($value, array $rule): mixed
    {
        if ($value === null || $value === '') {
            return $rule['nullable'] ?? false ? null : '';
        }
        
        // Convert to string for sanitization
        $stringValue = (string) $value;
        
        // Remove invisible characters and normalize whitespace
        $stringValue = $this->removeInvisibleCharacters($stringValue);
        $stringValue = $this->normalizeWhitespace($stringValue);
        
        // Handle encoding issues
        $stringValue = $this->normalizeEncoding($stringValue);
        
        // Remove potential XSS vectors
        $stringValue = $this->sanitizeForSecurity($stringValue);
        
        // Apply field-specific sanitization
        if (isset($rule['sanitizers'])) {
            foreach ($rule['sanitizers'] as $sanitizer) {
                $stringValue = $this->applySanitizer($stringValue, $sanitizer);
            }
        }
        
        return $stringValue;
    }
    
    /**
     * Remove invisible characters including zero-width spaces, RTL marks, etc.
     */
    private function removeInvisibleCharacters(string $value): string
    {
        // Remove common invisible characters
        $invisibleChars = [
            "\xE2\x80\x8B", // Zero-width space
            "\xE2\x80\x8C", // Zero-width non-joiner
            "\xE2\x80\x8D", // Zero-width joiner
            "\xE2\x80\x8E", // Left-to-right mark
            "\xE2\x80\x8F", // Right-to-left mark
            "\xEF\xBB\xBF", // Byte order mark
            "\xC2\xA0",     // Non-breaking space
        ];
        
        $cleaned = str_replace($invisibleChars, '', $value);
        
        // Remove control characters except tabs and newlines
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleaned);
        
        return $cleaned;
    }
    
    /**
     * Normalize whitespace and trim
     */
    private function normalizeWhitespace(string $value): string
    {
        // Replace multiple whitespace with single spaces
        $normalized = preg_replace('/\s+/', ' ', $value);
        
        // Trim leading/trailing whitespace
        return trim($normalized);
    }
    
    /**
     * Handle encoding normalization
     */
    private function normalizeEncoding(string $value): string
    {
        // Detect and convert encoding to UTF-8
        $encoding = mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        
        if ($encoding && $encoding !== 'UTF-8') {
            $value = mb_convert_encoding($value, 'UTF-8', $encoding);
            
            Log::debug('Encoding conversion performed', [
                'from' => $encoding,
                'original_length' => strlen($value),
                'converted_length' => mb_strlen($value, 'UTF-8')
            ]);
        }
        
        // Normalize Unicode characters
        if (class_exists('Normalizer')) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_C);
        }
        
        return $value;
    }
    
    /**
     * Sanitize for security (prevent injection attacks)
     */
    private function sanitizeForSecurity(string $value): string
    {
        // Remove potentially dangerous characters
        $dangerous = ['<script', '</script', 'javascript:', 'vbscript:', 'data:', 'file:'];
        
        foreach ($dangerous as $danger) {
            $value = str_ireplace($danger, '', $value);
        }
        
        // Escape HTML entities but preserve normal characters
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
        
        return $value;
    }
    
    /**
     * Apply specific sanitizer functions
     */
    private function applySanitizer(string $value, string $sanitizer): string
    {
        return match($sanitizer) {
            'alphanumeric' => preg_replace('/[^a-zA-Z0-9\s]/', '', $value),
            'numeric_only' => preg_replace('/[^0-9.-]/', '', $value),
            'alpha_only' => preg_replace('/[^a-zA-Z\s]/', '', $value),
            'slug' => Str::slug($value),
            'email' => filter_var($value, FILTER_SANITIZE_EMAIL),
            'url' => filter_var($value, FILTER_SANITIZE_URL),
            'phone' => preg_replace('/[^0-9+\-\(\)\s]/', '', $value),
            'sku' => strtoupper(preg_replace('/[^a-zA-Z0-9\-]/', '', $value)),
            default => $value
        };
    }
    
    /**
     * Cast values to appropriate types with validation
     */
    private function castValue($value, array $rule): mixed
    {
        if ($value === null || $value === '') {
            return $rule['nullable'] ?? false ? null : $this->getDefaultValue($rule['type']);
        }
        
        return match($rule['type']) {
            'string' => $this->castToString($value, $rule),
            'text' => $this->castToText($value, $rule),
            'integer' => $this->castToInteger($value, $rule),
            'decimal' => $this->castToDecimal($value, $rule),
            'boolean' => $this->castToBoolean($value),
            'email' => $this->castToEmail($value),
            'url' => $this->castToUrl($value),
            'date' => $this->castToDate($value),
            'json' => $this->castToJson($value),
            default => (string) $value
        };
    }
    
    /**
     * Cast to string with length validation
     */
    private function castToString($value, array $rule): string
    {
        $stringValue = (string) $value;
        $maxLength = $rule['max_length'] ?? self::MAX_STRING_LENGTH;
        
        if (mb_strlen($stringValue, 'UTF-8') > $maxLength) {
            $stringValue = mb_substr($stringValue, 0, $maxLength, 'UTF-8');
            Log::warning('String truncated', [
                'original_length' => mb_strlen($value, 'UTF-8'),
                'truncated_to' => $maxLength
            ]);
        }
        
        return $stringValue;
    }
    
    /**
     * Cast to text (longer strings)
     */
    private function castToText($value, array $rule): string
    {
        $stringValue = (string) $value;
        $maxLength = $rule['max_length'] ?? self::MAX_TEXT_LENGTH;
        
        if (mb_strlen($stringValue, 'UTF-8') > $maxLength) {
            $stringValue = mb_substr($stringValue, 0, $maxLength, 'UTF-8');
        }
        
        return $stringValue;
    }
    
    /**
     * Cast to integer with range validation
     */
    private function castToInteger($value, array $rule): int
    {
        // Handle Excel's scientific notation
        if (is_string($value) && preg_match('/^\d+\.?\d*E[+-]?\d+$/i', $value)) {
            $value = number_format((float) $value, 0, '', '');
        }
        
        $intValue = (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        
        if (isset($rule['min']) && $intValue < $rule['min']) {
            throw new \InvalidArgumentException("Value {$intValue} below minimum {$rule['min']}");
        }
        
        if (isset($rule['max']) && $intValue > $rule['max']) {
            throw new \InvalidArgumentException("Value {$intValue} above maximum {$rule['max']}");
        }
        
        return $intValue;
    }
    
    /**
     * Cast to decimal with precision control
     */
    private function castToDecimal($value, array $rule): string
    {
        $floatValue = (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $precision = $rule['precision'] ?? self::DECIMAL_PRECISION;
        
        return number_format($floatValue, $precision, '.', '');
    }
    
    /**
     * Cast to boolean with flexible input handling
     */
    private function castToBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        $stringValue = strtolower(trim((string) $value));
        
        return in_array($stringValue, ['1', 'true', 'yes', 'on', 'active']);
    }
    
    /**
     * Cast and validate email
     */
    private function castToEmail($value): ?string
    {
        $email = filter_var($value, FILTER_SANITIZE_EMAIL);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email format: {$value}");
        }
        
        return $email;
    }
    
    /**
     * Cast and validate URL
     */
    private function castToUrl($value): ?string
    {
        $url = filter_var($value, FILTER_SANITIZE_URL);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid URL format: {$value}");
        }
        
        return $url;
    }
    
    /**
     * Cast to date with multiple format support
     */
    private function castToDate($value): ?string
    {
        if (is_numeric($value)) {
            // Handle Excel date serial numbers
            $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
            return $date->format('Y-m-d');
        }
        
        try {
            $date = new \DateTime($value);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid date format: {$value}");
        }
    }
    
    /**
     * Cast to JSON with validation
     */
    private function castToJson($value): ?string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }
        
        if (is_string($value)) {
            json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return $value;
        }
        
        return null;
    }
    
    /**
     * Validate transformed value against business rules
     */
    private function validateValue($value, array $rule, string $field): mixed
    {
        // Required field validation
        if (($rule['required'] ?? false) && ($value === null || $value === '')) {
            throw new \InvalidArgumentException("Field '{$field}' is required");
        }
        
        // Custom validation rules
        if (isset($rule['validators'])) {
            foreach ($rule['validators'] as $validator => $params) {
                $this->applyValidator($value, $validator, $params, $field);
            }
        }
        
        return $value;
    }
    
    /**
     * Apply custom validators
     */
    private function applyValidator($value, string $validator, $params, string $field): void
    {
        switch ($validator) {
            case 'unique_sku':
                // Would integrate with database to check uniqueness
                break;
                
            case 'positive_number':
                if (is_numeric($value) && $value < 0) {
                    throw new \InvalidArgumentException("Field '{$field}' must be positive");
                }
                break;
                
            case 'in_list':
                if (!in_array($value, $params)) {
                    throw new \InvalidArgumentException("Field '{$field}' must be one of: " . implode(', ', $params));
                }
                break;
        }
    }
    
    /**
     * Cross-field validation rules
     */
    private function validateCrossFieldRules(TransformedRow $row): void
    {
        // Example: Variant must have SKU if not parent
        if (!$row->getField('is_parent') && empty($row->getField('variant_sku'))) {
            throw new DataTransformationException('Variant rows must have a SKU');
        }
        
        // Example: Price validation
        $retailPrice = $row->getField('retail_price');
        $wholesalePrice = $row->getField('wholesale_price');
        
        if ($retailPrice && $wholesalePrice && $retailPrice < $wholesalePrice) {
            throw new DataTransformationException('Retail price cannot be less than wholesale price');
        }
    }
    
    /**
     * Get default value for type
     */
    private function getDefaultValue(string $type): mixed
    {
        return match($type) {
            'string', 'text', 'email', 'url' => '',
            'integer' => 0,
            'decimal' => '0.00',
            'boolean' => false,
            'date' => null,
            'json' => null,
            default => null
        };
    }
    
    /**
     * Sanitize data for safe logging
     */
    private function sanitizeForLogging(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value) && mb_strlen($value) > 100) {
                $sanitized[$key] = mb_substr($value, 0, 100) . '...';
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Initialize transformation rules for each field
     */
    private function initializeTransformationRules(): void
    {
        $this->transformationRules = [
            'variant_sku' => [
                'type' => 'string',
                'max_length' => 50,
                'sanitizers' => ['sku'],
                'validators' => ['unique_sku' => true],
                'required' => false,
                'nullable' => false
            ],
            'product_name' => [
                'type' => 'string',
                'max_length' => 255,
                'sanitizers' => [],
                'required' => true,
                'nullable' => false
            ],
            'description' => [
                'type' => 'text',
                'max_length' => 65535,
                'sanitizers' => [],
                'required' => false,
                'nullable' => true
            ],
            'variant_color' => [
                'type' => 'string',
                'max_length' => 50,
                'sanitizers' => ['alpha_only'],
                'required' => false,
                'nullable' => true
            ],
            'variant_size' => [
                'type' => 'string',
                'max_length' => 20,
                'sanitizers' => ['alphanumeric'],
                'required' => false,
                'nullable' => true
            ],
            'retail_price' => [
                'type' => 'decimal',
                'precision' => 2,
                'validators' => ['positive_number' => true],
                'required' => false,
                'nullable' => true,
                'min' => 0
            ],
            'stock_quantity' => [
                'type' => 'integer',
                'validators' => ['positive_number' => true],
                'required' => false,
                'nullable' => false,
                'min' => 0,
                'max' => 999999
            ],
            'weight' => [
                'type' => 'decimal',
                'precision' => 3,
                'validators' => ['positive_number' => true],
                'required' => false,
                'nullable' => true
            ],
            'barcode' => [
                'type' => 'string',
                'max_length' => 20,
                'sanitizers' => ['numeric_only'],
                'required' => false,
                'nullable' => true
            ],
            'is_parent' => [
                'type' => 'boolean',
                'required' => false,
                'nullable' => false
            ],
            'image_urls' => [
                'type' => 'text',
                'sanitizers' => ['url'],
                'required' => false,
                'nullable' => true
            ]
        ];
    }
    
    /**
     * Initialize sanitization configuration
     */
    private function initializeSanitizationConfig(): void
    {
        $this->sanitizationConfig = [
            'remove_invisible_chars' => true,
            'normalize_whitespace' => true,
            'convert_encoding' => true,
            'security_sanitization' => true,
            'log_transformations' => config('app.debug', false)
        ];
    }
}