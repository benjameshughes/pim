<?php

namespace App\Services;

use App\DTOs\Import\ErrorReport;
use App\DTOs\Import\ImportError;
use App\Exceptions\Import\DataTransformationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Comprehensive error handling service for Excel imports
 * Provides detailed error tracking, reporting, and recovery mechanisms
 */
class ImportErrorHandlingService
{
    private const ERROR_LOG_PATH = 'imports/errors';

    private const MAX_ERROR_DETAILS = 1000; // Maximum number of detailed errors to track

    private array $errors = [];

    private array $warnings = [];

    private array $context = [];

    private int $errorCount = 0;

    private int $warningCount = 0;

    private string $importId;

    public function __construct(?string $importId = null)
    {
        $this->importId = $importId ?? uniqid('import_', true);
    }

    /**
     * Record an error with full context and stack trace
     */
    public function recordError(
        Throwable $exception,
        ?int $rowNumber = null,
        ?string $fieldName = null,
        array $rowData = [],
        string $severity = 'error'
    ): ImportError {
        $error = new ImportError(
            $exception->getMessage(),
            $exception->getCode(),
            $rowNumber,
            $fieldName,
            $rowData,
            $severity,
            get_class($exception),
            $exception->getTraceAsString()
        );

        // Only store detailed errors up to the limit
        if (count($this->errors) < self::MAX_ERROR_DETAILS) {
            $this->errors[] = $error;
        }

        $this->errorCount++;

        // Log the error with appropriate level
        $this->logError($error, $exception);

        return $error;
    }

    /**
     * Record a warning
     */
    public function recordWarning(
        string $message,
        ?int $rowNumber = null,
        ?string $fieldName = null,
        array $contextData = []
    ): void {
        $warning = [
            'message' => $message,
            'row_number' => $rowNumber,
            'field_name' => $fieldName,
            'context' => $contextData,
            'timestamp' => now(),
            'import_id' => $this->importId,
        ];

        $this->warnings[] = $warning;
        $this->warningCount++;

        Log::warning('Import warning recorded', $warning);
    }

    /**
     * Handle transformation errors with intelligent recovery
     */
    public function handleTransformationError(
        DataTransformationException $exception,
        array $rowData,
        int $rowNumber
    ): ?array {
        $this->recordError($exception, $rowNumber, $exception->getFieldName(), $rowData);

        // Attempt intelligent recovery based on error type
        return $this->attemptErrorRecovery($exception, $rowData, $rowNumber);
    }

    /**
     * Handle database constraint errors
     */
    public function handleConstraintError(
        Throwable $exception,
        array $rowData,
        int $rowNumber,
        string $operation = 'insert'
    ): bool {
        $this->recordError($exception, $rowNumber, null, $rowData, 'constraint_violation');

        // Analyze constraint violation for potential solutions
        $constraintInfo = $this->analyzeConstraintViolation($exception->getMessage());

        if ($constraintInfo) {
            $this->recordWarning(
                "Constraint violation analysis: {$constraintInfo['suggestion']}",
                $rowNumber,
                $constraintInfo['field'],
                ['constraint_type' => $constraintInfo['type']]
            );
        }

        return false; // Cannot recover from constraint violations
    }

    /**
     * Handle general import errors
     */
    public function handleGeneralError(
        Throwable $exception,
        string $operation,
        array $context = []
    ): void {
        $this->recordError($exception, null, null, $context, 'general');

        // Add operation context
        $this->addContext('last_operation', $operation);
        $this->addContext('operation_context', $context);
    }

    /**
     * Generate comprehensive error report
     */
    public function generateErrorReport(): ErrorReport
    {
        $report = new ErrorReport($this->importId);

        // Set basic statistics
        $report->setTotalErrors($this->errorCount);
        $report->setTotalWarnings($this->warningCount);
        $report->setErrors($this->errors);
        $report->setWarnings($this->warnings);
        $report->setContext($this->context);

        // Analyze error patterns
        $this->analyzeErrorPatterns($report);

        // Generate recommendations
        $this->generateRecommendations($report);

        // Save report to storage
        $this->saveErrorReport($report);

        return $report;
    }

    /**
     * Attempt intelligent error recovery
     */
    private function attemptErrorRecovery(
        DataTransformationException $exception,
        array $rowData,
        int $rowNumber
    ): ?array {
        $fieldName = $exception->getFieldName();
        $message = $exception->getMessage();

        // Type casting recovery
        if (strpos($message, 'type casting') !== false) {
            return $this->recoverFromTypeCastingError($fieldName, $rowData, $rowNumber);
        }

        // Length validation recovery
        if (strpos($message, 'length') !== false || strpos($message, 'truncated') !== false) {
            return $this->recoverFromLengthError($fieldName, $rowData, $rowNumber);
        }

        // Range validation recovery
        if (strpos($message, 'range') !== false || strpos($message, 'minimum') !== false) {
            return $this->recoverFromRangeError($fieldName, $rowData, $rowNumber);
        }

        return null; // No recovery possible
    }

    /**
     * Recover from type casting errors
     */
    private function recoverFromTypeCastingError(string $fieldName, array $rowData, int $rowNumber): ?array
    {
        if (! isset($rowData[$fieldName])) {
            return null;
        }

        $originalValue = $rowData[$fieldName];
        $recoveredValue = null;

        // Attempt different recovery strategies based on field type
        switch ($fieldName) {
            case 'retail_price':
            case 'wholesale_price':
                // Try to extract numeric value from string
                if (preg_match('/[\d.]+/', (string) $originalValue, $matches)) {
                    $recoveredValue = (float) $matches[0];
                }
                break;

            case 'stock_quantity':
                // Extract integer from mixed content
                if (preg_match('/\d+/', (string) $originalValue, $matches)) {
                    $recoveredValue = (int) $matches[0];
                }
                break;

            case 'is_parent':
                // Convert various formats to boolean
                $lower = strtolower(trim((string) $originalValue));
                $recoveredValue = in_array($lower, ['1', 'true', 'yes', 'y', 'parent']);
                break;
        }

        if ($recoveredValue !== null) {
            $rowData[$fieldName] = $recoveredValue;

            $this->recordWarning(
                "Recovered from type casting error by converting '{$originalValue}' to '{$recoveredValue}'",
                $rowNumber,
                $fieldName,
                ['original_value' => $originalValue, 'recovered_value' => $recoveredValue]
            );

            return $rowData;
        }

        return null;
    }

    /**
     * Recover from length validation errors
     */
    private function recoverFromLengthError(string $fieldName, array $rowData, int $rowNumber): ?array
    {
        if (! isset($rowData[$fieldName])) {
            return null;
        }

        $originalValue = (string) $rowData[$fieldName];
        $maxLengths = [
            'product_name' => 255,
            'variant_sku' => 50,
            'variant_color' => 50,
            'variant_size' => 20,
            'barcode' => 20,
        ];

        if (isset($maxLengths[$fieldName])) {
            $truncatedValue = mb_substr($originalValue, 0, $maxLengths[$fieldName]);
            $rowData[$fieldName] = $truncatedValue;

            $this->recordWarning(
                "Truncated field '{$fieldName}' from {mb_strlen($originalValue)} to {mb_strlen($truncatedValue)} characters",
                $rowNumber,
                $fieldName,
                ['original_length' => mb_strlen($originalValue), 'truncated_length' => mb_strlen($truncatedValue)]
            );

            return $rowData;
        }

        return null;
    }

    /**
     * Recover from range validation errors
     */
    private function recoverFromRangeError(string $fieldName, array $rowData, int $rowNumber): ?array
    {
        if (! isset($rowData[$fieldName])) {
            return null;
        }

        $originalValue = $rowData[$fieldName];
        $ranges = [
            'retail_price' => ['min' => 0, 'max' => 999999.99],
            'stock_quantity' => ['min' => 0, 'max' => 999999],
            'weight' => ['min' => 0, 'max' => 999.999],
        ];

        if (isset($ranges[$fieldName])) {
            $range = $ranges[$fieldName];
            $recoveredValue = max($range['min'], min($range['max'], $originalValue));

            if ($recoveredValue !== $originalValue) {
                $rowData[$fieldName] = $recoveredValue;

                $this->recordWarning(
                    "Adjusted field '{$fieldName}' from {$originalValue} to {$recoveredValue} to fit valid range",
                    $rowNumber,
                    $fieldName,
                    ['original_value' => $originalValue, 'adjusted_value' => $recoveredValue]
                );

                return $rowData;
            }
        }

        return null;
    }

    /**
     * Analyze constraint violation for helpful suggestions
     */
    private function analyzeConstraintViolation(string $errorMessage): ?array
    {
        $constraintPatterns = [
            'UNIQUE constraint failed: product_variants.sku' => [
                'type' => 'unique_sku',
                'field' => 'variant_sku',
                'suggestion' => 'SKU already exists in database. Consider using update mode or modifying SKU.',
            ],
            'UNIQUE constraint failed: product_variants.barcode' => [
                'type' => 'unique_barcode',
                'field' => 'barcode',
                'suggestion' => 'Barcode already exists. Remove duplicate barcode or use unique identifier.',
            ],
            'UNIQUE constraint failed' => [
                'type' => 'unique_general',
                'field' => 'unknown',
                'suggestion' => 'Duplicate data detected. Review import data for unique constraint violations.',
            ],
        ];

        foreach ($constraintPatterns as $pattern => $info) {
            if (strpos($errorMessage, $pattern) !== false) {
                return $info;
            }
        }

        return null;
    }

    /**
     * Analyze error patterns to identify common issues
     */
    private function analyzeErrorPatterns(ErrorReport $report): void
    {
        $errorTypes = [];
        $fieldErrors = [];
        $rowErrors = [];

        foreach ($this->errors as $error) {
            // Count error types
            $errorTypes[$error->getExceptionType()] = ($errorTypes[$error->getExceptionType()] ?? 0) + 1;

            // Count field-specific errors
            if ($error->getFieldName()) {
                $fieldErrors[$error->getFieldName()] = ($fieldErrors[$error->getFieldName()] ?? 0) + 1;
            }

            // Track rows with multiple errors
            if ($error->getRowNumber()) {
                $rowErrors[$error->getRowNumber()] = ($rowErrors[$error->getRowNumber()] ?? 0) + 1;
            }
        }

        $report->setErrorPatterns([
            'error_types' => $errorTypes,
            'field_errors' => $fieldErrors,
            'problematic_rows' => array_filter($rowErrors, fn ($count) => $count > 1),
        ]);
    }

    /**
     * Generate actionable recommendations based on error analysis
     */
    private function generateRecommendations(ErrorReport $report): void
    {
        $recommendations = [];
        $patterns = $report->getErrorPatterns();

        // Recommendations based on error types
        if (isset($patterns['error_types']['DataTransformationException']) &&
            $patterns['error_types']['DataTransformationException'] > 10) {
            $recommendations[] = 'Consider reviewing column mapping - many data transformation errors detected';
        }

        // Recommendations based on field errors
        if (isset($patterns['field_errors']['variant_sku']) &&
            $patterns['field_errors']['variant_sku'] > 5) {
            $recommendations[] = 'SKU field has many errors - verify SKU format and uniqueness';
        }

        if (isset($patterns['field_errors']['retail_price']) &&
            $patterns['field_errors']['retail_price'] > 3) {
            $recommendations[] = 'Price field formatting issues detected - ensure numeric format (e.g., 19.99)';
        }

        // General recommendations
        if ($this->errorCount > ($this->errorCount + $this->warningCount) * 0.5) {
            $recommendations[] = 'High error rate detected - consider using dry run mode to validate data first';
        }

        if (count($patterns['problematic_rows']) > 0) {
            $recommendations[] = 'Some rows have multiple errors - focus on fixing data quality in these rows first';
        }

        $report->setRecommendations($recommendations);
    }

    /**
     * Log error with appropriate context
     */
    private function logError(ImportError $error, Throwable $exception): void
    {
        $context = [
            'import_id' => $this->importId,
            'row_number' => $error->getRowNumber(),
            'field_name' => $error->getFieldName(),
            'exception_type' => $error->getExceptionType(),
            'severity' => $error->getSeverity(),
        ];

        // Add row data context if not too large
        if (! empty($error->getRowData()) && count($error->getRowData()) < 20) {
            $context['row_data'] = $error->getRowData();
        }

        match ($error->getSeverity()) {
            'critical' => Log::critical($error->getMessage(), $context),
            'error' => Log::error($error->getMessage(), $context),
            'warning' => Log::warning($error->getMessage(), $context),
            default => Log::info($error->getMessage(), $context)
        };
    }

    /**
     * Save error report to storage
     */
    private function saveErrorReport(ErrorReport $report): void
    {
        try {
            $filename = self::ERROR_LOG_PATH.'/'.$this->importId.'_error_report.json';
            Storage::put($filename, json_encode($report->toArray(), JSON_PRETTY_PRINT));

            Log::info('Error report saved', [
                'import_id' => $this->importId,
                'filename' => $filename,
                'total_errors' => $this->errorCount,
                'total_warnings' => $this->warningCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save error report', [
                'import_id' => $this->importId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Add context information
     */
    public function addContext(string $key, $value): void
    {
        $this->context[$key] = $value;
    }

    /**
     * Get error statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_errors' => $this->errorCount,
            'total_warnings' => $this->warningCount,
            'detailed_errors_tracked' => count($this->errors),
            'import_id' => $this->importId,
        ];
    }
}
