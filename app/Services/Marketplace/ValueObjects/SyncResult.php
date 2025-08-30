<?php

namespace App\Services\Marketplace\ValueObjects;

/**
 * ✅ SYNC RESULT VALUE OBJECT
 *
 * Standardized result object for all marketplace operations.
 * Provides consistent success/failure information across all marketplaces.
 */
class SyncResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message = '',
        public readonly array $data = [],
        public readonly array $errors = [],
        public readonly array $metadata = []
    ) {}

    /**
     * Create a successful result
     */
    public static function success(string $message = 'Operation successful', array $data = [], array $metadata = []): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
            metadata: $metadata
        );
    }

    /**
     * Create a failed result
     */
    public static function failure(string $message = 'Operation failed', array $errors = [], array $metadata = []): self
    {
        return new self(
            success: false,
            message: $message,
            errors: $errors,
            metadata: $metadata
        );
    }

    /**
     * Check if the operation was successful
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if the operation failed
     */
    public function isFailure(): bool
    {
        return !$this->success;
    }

    /**
     * Get the result message
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get the result data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get specific data value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Get the errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the first error message
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Get metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Convert to array for easy serialization
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'errors' => $this->errors,
            'metadata' => $this->metadata,
        ];
    }
}