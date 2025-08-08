<?php

namespace App\Services\Import\Conflicts;

use Illuminate\Support\Facades\Log;

class UniqueConstraintResolver implements ConflictResolverInterface
{
    private string $defaultStrategy;
    private array $fieldStrategies;

    public function __construct(array $config = [])
    {
        $this->defaultStrategy = $config['default_strategy'] ?? 'skip';
        $this->fieldStrategies = $config['field_strategies'] ?? [];
    }

    public function canResolve(array $conflictData): bool
    {
        // Catch-all resolver for any unique constraint violations
        return isset($conflictData['constraint']) 
            && strpos(strtolower($conflictData['constraint']), 'unique') !== false;
    }

    public function resolve(array $conflictData, array $context = []): ConflictResolution
    {
        $constraint = $conflictData['constraint'];
        $conflictingValue = $conflictData['conflicting_value'];
        
        Log::debug('Resolving generic unique constraint conflict', [
            'constraint' => $constraint,
            'conflicting_value' => $conflictingValue,
            'strategy' => $this->defaultStrategy,
        ]);

        // Try to identify the field from constraint name
        $field = $this->extractFieldFromConstraint($constraint);
        
        // Use field-specific strategy if available
        $strategy = $this->fieldStrategies[$field] ?? $this->defaultStrategy;

        switch ($strategy) {
            case 'skip':
                return ConflictResolution::skip(
                    "Unique constraint violation, skipping: {$constraint}",
                    [
                        'constraint' => $constraint,
                        'field' => $field,
                        'conflicting_value' => $conflictingValue,
                    ]
                );

            case 'generate_unique':
                return $this->attemptUniqueGeneration($field, $conflictingValue, $context);

            case 'append_suffix':
                return $this->appendSuffix($field, $conflictingValue, $context);

            case 'remove_field':
                return ConflictResolution::retryWithModifiedData(
                    [$field => null],
                    "Removed conflicting field: {$field}",
                    [
                        'original_value' => $conflictingValue,
                        'field_removed' => $field,
                    ]
                );

            case 'fail':
                return ConflictResolution::failed(
                    "Unique constraint violation: {$constraint}",
                    [
                        'constraint' => $constraint,
                        'field' => $field,
                        'conflicting_value' => $conflictingValue,
                    ]
                );

            default:
                Log::warning('Unknown unique constraint strategy', [
                    'strategy' => $strategy,
                    'constraint' => $constraint,
                ]);
                
                return ConflictResolution::skip(
                    "Unknown resolution strategy '{$strategy}' for constraint: {$constraint}"
                );
        }
    }

    private function extractFieldFromConstraint(string $constraint): string
    {
        // Try to extract field name from constraint name
        // Common patterns: table_field_unique, field_unique_idx, etc.
        
        $parts = explode('_', $constraint);
        
        // Remove common suffixes
        $suffixes = ['unique', 'idx', 'index', 'key'];
        $cleanParts = array_filter($parts, function($part) use ($suffixes) {
            return !in_array(strtolower($part), $suffixes);
        });

        // Remove table name (usually first part)
        if (count($cleanParts) > 1) {
            array_shift($cleanParts);
        }

        return implode('_', $cleanParts) ?: 'unknown_field';
    }

    private function attemptUniqueGeneration(string $field, ?string $value, array $context): ConflictResolution
    {
        if (!$value) {
            return ConflictResolution::failed("Cannot generate unique value for null field: {$field}");
        }

        $uniqueValue = $this->generateUniqueValue($field, $value);
        
        return ConflictResolution::retryWithModifiedData(
            [$field => $uniqueValue],
            "Generated unique value for field {$field}: {$uniqueValue}",
            [
                'original_value' => $value,
                'generated_value' => $uniqueValue,
                'field' => $field,
            ]
        );
    }

    private function appendSuffix(string $field, ?string $value, array $context): ConflictResolution
    {
        if (!$value) {
            return ConflictResolution::failed("Cannot append suffix to null field: {$field}");
        }

        $suffixedValue = $value . '-' . uniqid();
        
        return ConflictResolution::retryWithModifiedData(
            [$field => $suffixedValue],
            "Appended suffix to field {$field}: {$suffixedValue}",
            [
                'original_value' => $value,
                'suffixed_value' => $suffixedValue,
                'field' => $field,
            ]
        );
    }

    private function generateUniqueValue(string $field, string $baseValue): string
    {
        $timestamp = now()->format('YmdHis');
        $random = substr(uniqid(), -6);
        
        // Different strategies based on field type
        switch ($field) {
            case 'slug':
                return $baseValue . '-' . $timestamp;
                
            case 'email':
                $parts = explode('@', $baseValue);
                if (count($parts) === 2) {
                    return $parts[0] . '+' . $random . '@' . $parts[1];
                }
                return $baseValue . '.' . $random;
                
            case 'name':
            case 'title':
                return $baseValue . ' (' . $timestamp . ')';
                
            default:
                return $baseValue . '-' . $random;
        }
    }

    /**
     * Get configuration suggestions for common constraint types
     */
    public static function getRecommendedConfig(): array
    {
        return [
            'default_strategy' => 'skip',
            'field_strategies' => [
                'sku' => 'generate_unique',
                'barcode' => 'skip',
                'email' => 'generate_unique',
                'slug' => 'generate_unique',
                'name' => 'append_suffix',
                'title' => 'append_suffix',
            ]
        ];
    }
}