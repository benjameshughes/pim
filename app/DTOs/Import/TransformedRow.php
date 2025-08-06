<?php

namespace App\DTOs\Import;

/**
 * Represents a single transformed and validated row of import data
 * Enhanced with comprehensive data handling, type safety, and transformation tracking
 */
class TransformedRow
{
    private array $fields = [];
    private array $originalData = [];
    private int $rowNumber;
    private array $warnings = [];
    private array $errors = [];
    private array $transformationLog = [];
    private bool $isValid = true;
    
    public function __construct(int $rowNumber, array $originalData = [])
    {
        $this->rowNumber = $rowNumber;
        $this->originalData = $originalData;
        $this->fields = $originalData; // Start with original data
    }
    
    public function setField(string $field, $value): void
    {
        $originalValue = $this->fields[$field] ?? null;
        $this->fields[$field] = $value;
        
        // Log transformation if value changed
        if ($originalValue !== $value) {
            $this->logTransformation($field, $originalValue, $value);
        }
    }
    
    public function getField(string $field, $default = null)
    {
        return $this->fields[$field] ?? $default;
    }
    
    public function getAllFields(): array
    {
        return $this->fields;
    }
    
    public function getOriginalData(): array
    {
        return $this->originalData;
    }
    
    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }
    
    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }
    
    public function addError(string $field, string $message): void
    {
        $this->errors[] = [
            'field' => $field,
            'message' => $message,
            'value' => $this->getField($field)
        ];
        $this->isValid = false;
    }
    
    public function getWarnings(): array
    {
        return $this->warnings;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }
    
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
    
    public function isValid(): bool
    {
        return $this->isValid;
    }
    
    public function hasField(string $field): bool
    {
        return array_key_exists($field, $this->fields);
    }
    
    public function removeField(string $field): void
    {
        unset($this->fields[$field]);
    }
    
    /**
     * Get a string field with safe handling
     */
    public function getString(string $field, string $default = ''): string
    {
        $value = $this->getField($field, $default);
        return is_string($value) ? trim($value) : (string) $value;
    }
    
    /**
     * Get an integer field with validation
     */
    public function getInteger(string $field, int $default = 0): int
    {
        $value = $this->getField($field, $default);
        
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        $this->addWarning("Non-numeric value '{$value}' in field '{$field}' converted to {$default}");
        return $default;
    }
    
    /**
     * Get a float field with validation
     */
    public function getFloat(string $field, float $default = 0.0): float
    {
        $value = $this->getField($field, $default);
        
        if (is_numeric($value)) {
            return (float) $value;
        }
        
        $this->addWarning("Non-numeric value '{$value}' in field '{$field}' converted to {$default}");
        return $default;
    }
    
    /**
     * Get a decimal field formatted for database storage
     */
    public function getDecimal(string $field, int $precision = 2, float $default = 0.0): string
    {
        $value = $this->getFloat($field, $default);
        return number_format($value, $precision, '.', '');
    }
    
    /**
     * Get a sanitized SKU field
     */
    public function getSku(string $field, string $default = ''): string
    {
        $sku = $this->getString($field, $default);
        
        if (empty($sku)) {
            return $default;
        }
        
        // Clean SKU - remove special characters except hyphens and underscores
        $cleanSku = preg_replace('/[^a-zA-Z0-9\-_]/', '', $sku);
        
        if ($sku !== $cleanSku) {
            $this->setField($field, $cleanSku);
            $this->addWarning("SKU '{$sku}' sanitized to '{$cleanSku}' in field '{$field}'");
        }
        
        return $cleanSku;
    }
    
    /**
     * Get a URL-friendly slug field
     */
    public function getSlug(string $field, string $default = ''): string
    {
        $value = $this->getString($field, $default);
        
        if (empty($value)) {
            return $default;
        }
        
        // Create URL-friendly slug
        $slug = strtolower($value);
        $slug = preg_replace('/[^a-z0-9\-_\s]/', '', $slug);
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        if ($value !== $slug) {
            $this->setField($field, $slug);
            $this->addWarning("Value '{$value}' converted to URL-friendly slug '{$slug}' in field '{$field}'");
        }
        
        return $slug;
    }
    
    /**
     * Check if field is empty
     */
    public function isEmpty(string $field): bool
    {
        $value = $this->getField($field);
        return empty($value) && $value !== '0' && $value !== 0;
    }
    
    /**
     * Log a transformation
     */
    private function logTransformation(string $field, $originalValue, $newValue, string $reason = 'Value transformed'): void
    {
        $this->transformationLog[] = [
            'field' => $field,
            'original_value' => $originalValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'timestamp' => microtime(true)
        ];
    }
    
    /**
     * Get transformation log
     */
    public function getTransformationLog(): array
    {
        return $this->transformationLog;
    }
    
    /**
     * Check if any transformations were applied
     */
    public function wasTransformed(): bool
    {
        return !empty($this->transformationLog);
    }
    
    /**
     * Get fields that were transformed
     */
    public function getTransformedFields(): array
    {
        return array_unique(array_column($this->transformationLog, 'field'));
    }
    
    public function toArray(): array
    {
        return [
            'row_number' => $this->rowNumber,
            'fields' => $this->fields,
            'original_data' => $this->originalData,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'transformation_log' => $this->transformationLog,
            'is_valid' => $this->isValid,
            'was_transformed' => $this->wasTransformed(),
            'transformed_fields' => $this->getTransformedFields()
        ];
    }
    
    /**
     * Create from array (for caching/deserialization)
     */
    public static function fromArray(array $data): self
    {
        $row = new self($data['row_number'], $data['original_data'] ?? []);
        $row->fields = $data['fields'] ?? [];
        $row->warnings = $data['warnings'] ?? [];
        $row->errors = $data['errors'] ?? [];
        $row->transformationLog = $data['transformation_log'] ?? [];
        $row->isValid = $data['is_valid'] ?? true;
        
        return $row;
    }
}