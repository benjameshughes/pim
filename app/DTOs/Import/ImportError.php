<?php

namespace App\DTOs\Import;

/**
 * Represents a single import error with full context and recovery capabilities
 */
class ImportError
{
    private string $message;
    private int $code;
    private ?int $rowNumber;
    private ?string $fieldName;
    private array $rowData;
    private string $severity;
    private string $exceptionType;
    private string $stackTrace;
    private \DateTime $timestamp;
    private string $category;
    private bool $critical;
    private bool $recoverable;
    private bool $recovered = false;
    private ?string $recoveryStrategy = null;
    private array $context = [];
    
    public function __construct(
        string $message,
        int $code = 0,
        ?int $rowNumber = null,
        ?string $fieldName = null,
        array $rowData = [],
        string $severity = 'error',
        string $exceptionType = 'Exception',
        string $stackTrace = '',
        string $category = 'general',
        bool $critical = null,
        bool $recoverable = null,
        array $context = []
    ) {
        $this->message = $message;
        $this->code = $code;
        $this->rowNumber = $rowNumber;
        $this->fieldName = $fieldName;
        $this->rowData = $rowData;
        $this->severity = $severity;
        $this->exceptionType = $exceptionType;
        $this->stackTrace = $stackTrace;
        $this->timestamp = new \DateTime();
        $this->category = $category;
        $this->critical = $critical ?? ($severity === 'critical');
        $this->recoverable = $recoverable ?? in_array($severity, ['warning', 'error']);
        $this->context = $context;
    }
    
    public function getMessage(): string
    {
        return $this->message;
    }
    
    public function getCode(): int
    {
        return $this->code;
    }
    
    public function getRowNumber(): ?int
    {
        return $this->rowNumber;
    }
    
    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }
    
    public function getRowData(): array
    {
        return $this->rowData;
    }
    
    public function getSeverity(): string
    {
        return $this->severity;
    }
    
    public function getExceptionType(): string
    {
        return $this->exceptionType;
    }
    
    public function getStackTrace(): string
    {
        return $this->stackTrace;
    }
    
    public function getTimestamp(): \DateTime
    {
        return $this->timestamp;
    }
    
    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }
    
    public function getCategory(): string
    {
        return $this->category;
    }
    
    public function getContext(): array
    {
        return $this->context;
    }
    
    public function addContext(string $key, $value): void
    {
        $this->context[$key] = $value;
    }
    
    public function isCritical(): bool
    {
        return $this->critical;
    }
    
    public function isRecoverable(): bool
    {
        return $this->recoverable;
    }
    
    public function isRecovered(): bool
    {
        return $this->recovered;
    }
    
    public function markAsRecovered(string $strategy): void
    {
        $this->recovered = true;
        $this->recoveryStrategy = $strategy;
    }
    
    public function getRecoveryStrategy(): ?string
    {
        return $this->recoveryStrategy;
    }
    
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'code' => $this->code,
            'row_number' => $this->rowNumber,
            'field_name' => $this->fieldName,
            'row_data' => $this->rowData,
            'severity' => $this->severity,
            'exception_type' => $this->exceptionType,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'category' => $this->category,
            'context' => $this->context,
            'is_critical' => $this->isCritical(),
            'is_recoverable' => $this->isRecoverable(),
            'is_recovered' => $this->isRecovered(),
            'recovery_strategy' => $this->recoveryStrategy
        ];
    }
}