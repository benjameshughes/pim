<?php

namespace App\DTOs\Import;

/**
 * Comprehensive error report for import operations
 */
class ErrorReport
{
    private string $importId;
    private int $totalErrors = 0;
    private int $totalWarnings = 0;
    private array $errors = [];
    private array $warnings = [];
    private array $context = [];
    private array $errorPatterns = [];
    private array $recommendations = [];
    private \DateTime $timestamp;
    
    public function __construct(string $importId)
    {
        $this->importId = $importId;
        $this->timestamp = new \DateTime();
    }
    
    public function setTotalErrors(int $count): void
    {
        $this->totalErrors = $count;
    }
    
    public function setTotalWarnings(int $count): void
    {
        $this->totalWarnings = $count;
    }
    
    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }
    
    public function setWarnings(array $warnings): void
    {
        $this->warnings = $warnings;
    }
    
    public function setContext(array $context): void
    {
        $this->context = $context;
    }
    
    public function setErrorPatterns(array $patterns): void
    {
        $this->errorPatterns = $patterns;
    }
    
    public function setRecommendations(array $recommendations): void
    {
        $this->recommendations = $recommendations;
    }
    
    public function getImportId(): string
    {
        return $this->importId;
    }
    
    public function getTotalErrors(): int
    {
        return $this->totalErrors;
    }
    
    public function getTotalWarnings(): int
    {
        return $this->totalWarnings;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    public function getWarnings(): array
    {
        return $this->warnings;
    }
    
    public function getContext(): array
    {
        return $this->context;
    }
    
    public function getErrorPatterns(): array
    {
        return $this->errorPatterns;
    }
    
    public function getRecommendations(): array
    {
        return $this->recommendations;
    }
    
    public function hasErrors(): bool
    {
        return $this->totalErrors > 0;
    }
    
    public function hasWarnings(): bool
    {
        return $this->totalWarnings > 0;
    }
    
    public function getCriticalErrors(): array
    {
        return array_filter($this->errors, function($error) {
            return $error instanceof ImportError && $error->isCritical();
        });
    }
    
    public function getRecoverableErrors(): array
    {
        return array_filter($this->errors, function($error) {
            return $error instanceof ImportError && $error->isRecoverable();
        });
    }
    
    public function getSummary(): array
    {
        return [
            'import_id' => $this->importId,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'total_errors' => $this->totalErrors,
            'total_warnings' => $this->totalWarnings,
            'critical_errors' => count($this->getCriticalErrors()),
            'recoverable_errors' => count($this->getRecoverableErrors()),
            'has_patterns' => !empty($this->errorPatterns),
            'has_recommendations' => !empty($this->recommendations)
        ];
    }
    
    public function toArray(): array
    {
        return [
            'import_id' => $this->importId,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'summary' => $this->getSummary(),
            'total_errors' => $this->totalErrors,
            'total_warnings' => $this->totalWarnings,
            'errors' => array_map(function($error) {
                return $error instanceof ImportError ? $error->toArray() : $error;
            }, $this->errors),
            'warnings' => $this->warnings,
            'context' => $this->context,
            'error_patterns' => $this->errorPatterns,
            'recommendations' => $this->recommendations
        ];
    }
}