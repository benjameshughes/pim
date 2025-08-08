<?php

namespace App\Exceptions\Import;

use Exception;

/**
 * Exception thrown during data transformation process
 */
class DataTransformationException extends Exception
{
    private ?array $contextData = null;

    private ?int $rowNumber = null;

    private ?string $fieldName = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?array $contextData = null,
        ?int $rowNumber = null,
        ?string $fieldName = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->contextData = $contextData;
        $this->rowNumber = $rowNumber;
        $this->fieldName = $fieldName;
    }

    public function getContextData(): ?array
    {
        return $this->contextData;
    }

    public function getRowNumber(): ?int
    {
        return $this->rowNumber;
    }

    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'row_number' => $this->rowNumber,
            'field_name' => $this->fieldName,
            'context_data' => $this->contextData,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }

    /**
     * Create exception for field validation failure
     */
    public static function fieldValidationFailed(
        string $fieldName,
        $value,
        string $reason,
        int $rowNumber
    ): self {
        return new self(
            "Field '{$fieldName}' validation failed: {$reason}",
            422,
            null,
            ['field' => $fieldName, 'value' => $value, 'reason' => $reason],
            $rowNumber,
            $fieldName
        );
    }

    /**
     * Create exception for type casting failure
     */
    public static function typeCastingFailed(
        string $fieldName,
        $value,
        string $expectedType,
        int $rowNumber
    ): self {
        return new self(
            "Failed to cast field '{$fieldName}' to {$expectedType}",
            422,
            null,
            ['field' => $fieldName, 'value' => $value, 'expected_type' => $expectedType],
            $rowNumber,
            $fieldName
        );
    }

    /**
     * Create exception for sanitization failure
     */
    public static function sanitizationFailed(
        string $fieldName,
        $value,
        string $sanitizer,
        int $rowNumber
    ): self {
        return new self(
            "Failed to sanitize field '{$fieldName}' using {$sanitizer}",
            422,
            null,
            ['field' => $fieldName, 'value' => $value, 'sanitizer' => $sanitizer],
            $rowNumber,
            $fieldName
        );
    }
}
