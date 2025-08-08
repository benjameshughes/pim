<?php

namespace App\DTOs\Import;

/**
 * Contains the results of data transformation including errors and transformed data
 * Enhanced with comprehensive validation, security tracking, and performance metrics
 */
class DataTransformationResult
{
    private array $transformedData = [];

    private array $errors = [];

    private int $successCount = 0;

    private int $errorCount = 0;

    private array $warnings = [];

    private array $statistics = [];

    private array $securityThreats = [];

    private array $transformationSummary = [];

    private float $processingTime = 0.0;

    private int $memoryUsed = 0;

    public function setTransformedData(array $data): void
    {
        $this->transformedData = $data;
    }

    public function getTransformedData(): array
    {
        return $this->transformedData;
    }

    public function addError(int $rowNumber, string $message, array $rawData = []): void
    {
        $this->errors[] = [
            'row_number' => $rowNumber,
            'message' => $message,
            'raw_data' => $rawData,
            'timestamp' => now(),
        ];
        $this->errorCount++;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function incrementSuccessCount(): void
    {
        $this->successCount++;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function addWarning(string $warning): void
    {
        $this->warnings[] = [
            'message' => $warning,
            'timestamp' => now(),
        ];
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    public function addStatistic(string $key, $value): void
    {
        $this->statistics[$key] = $value;
    }

    public function getStatistics(): array
    {
        return $this->statistics;
    }

    public function getTotalProcessed(): int
    {
        return $this->successCount + $this->errorCount;
    }

    public function getSuccessRate(): float
    {
        $total = $this->getTotalProcessed();

        return $total > 0 ? ($this->successCount / $total) * 100 : 0;
    }

    public function isSuccessful(): bool
    {
        return $this->errorCount === 0;
    }

    /**
     * Add security threat
     */
    public function addSecurityThreat(string $type, string $description, ?int $rowNumber = null): void
    {
        $this->securityThreats[] = [
            'type' => $type,
            'description' => $description,
            'row_number' => $rowNumber,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Get security threats
     */
    public function getSecurityThreats(): array
    {
        return $this->securityThreats;
    }

    /**
     * Check if security threats were detected
     */
    public function hasSecurityThreats(): bool
    {
        return ! empty($this->securityThreats);
    }

    /**
     * Set transformation summary
     */
    public function setTransformationSummary(array $summary): void
    {
        $this->transformationSummary = $summary;
    }

    /**
     * Get transformation summary
     */
    public function getTransformationSummary(): array
    {
        return $this->transformationSummary;
    }

    /**
     * Set processing metrics
     */
    public function setProcessingMetrics(float $processingTime, int $memoryUsed): void
    {
        $this->processingTime = $processingTime;
        $this->memoryUsed = $memoryUsed;
    }

    /**
     * Get processing time
     */
    public function getProcessingTime(): float
    {
        return $this->processingTime;
    }

    /**
     * Get memory used
     */
    public function getMemoryUsed(): int
    {
        return $this->memoryUsed;
    }

    /**
     * Add a transformed row to the result
     */
    public function addTransformedRow(TransformedRow $row): void
    {
        $this->transformedData[] = $row;

        if ($row->isValid()) {
            $this->successCount++;
        } else {
            $this->errorCount++;
        }

        // Add row warnings to result warnings
        foreach ($row->getWarnings() as $warning) {
            $this->addWarning($warning);
        }

        // Add row errors to result errors
        foreach ($row->getErrors() as $error) {
            $this->addError(
                $row->getRowNumber(),
                $error['message'],
                $error['value'] ?? []
            );
        }
    }

    /**
     * Get all transformed rows that are valid
     */
    public function getValidTransformedRows(): array
    {
        return array_filter($this->transformedData, function ($row) {
            return $row instanceof TransformedRow && $row->isValid();
        });
    }

    /**
     * Get all transformed rows that have errors
     */
    public function getInvalidTransformedRows(): array
    {
        return array_filter($this->transformedData, function ($row) {
            return $row instanceof TransformedRow && ! $row->isValid();
        });
    }

    /**
     * Get summary of field transformations
     */
    public function getFieldTransformationSummary(): array
    {
        $fieldStats = [];

        foreach ($this->transformedData as $row) {
            if ($row instanceof TransformedRow && $row->wasTransformed()) {
                foreach ($row->getTransformedFields() as $field) {
                    $fieldStats[$field] = ($fieldStats[$field] ?? 0) + 1;
                }
            }
        }

        return $fieldStats;
    }

    /**
     * Get comprehensive quality metrics
     */
    public function getQualityMetrics(): array
    {
        $total = $this->getTotalProcessed();
        $validRows = count($this->getValidTransformedRows());
        $transformedFieldsCount = count($this->getFieldTransformationSummary());

        return [
            'data_quality_score' => $total > 0 ? ($validRows / $total) * 100 : 0,
            'transformation_rate' => $total > 0 ? ($transformedFieldsCount / $total) * 100 : 0,
            'security_threat_rate' => $total > 0 ? (count($this->securityThreats) / $total) * 100 : 0,
            'warning_rate' => $total > 0 ? (count($this->warnings) / $total) * 100 : 0,
            'processing_efficiency' => $total > 0 ? $total / max(0.001, $this->processingTime) : 0,
            'memory_efficiency' => $total > 0 ? ($this->memoryUsed / 1024 / 1024) / $total : 0, // MB per row
        ];
    }

    public function toArray(): array
    {
        return [
            'success_count' => $this->successCount,
            'error_count' => $this->errorCount,
            'total_processed' => $this->getTotalProcessed(),
            'success_rate' => $this->getSuccessRate(),
            'has_errors' => $this->hasErrors(),
            'has_warnings' => $this->hasWarnings(),
            'has_security_threats' => $this->hasSecurityThreats(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'security_threats' => $this->securityThreats,
            'statistics' => $this->statistics,
            'transformation_summary' => $this->transformationSummary,
            'processing_metrics' => [
                'processing_time' => $this->processingTime,
                'memory_used' => $this->memoryUsed,
                'memory_used_mb' => round($this->memoryUsed / 1024 / 1024, 2),
            ],
            'quality_metrics' => $this->getQualityMetrics(),
            'field_transformation_summary' => $this->getFieldTransformationSummary(),
        ];
    }
}
