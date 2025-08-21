<?php

namespace App\Rules;

use App\Enums\MappingFieldType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 🔄✨ UNIQUE FIELD MAPPINGS RULE ✨🔄
 *
 * Custom validation rule ensuring no field is mapped multiple times
 */
class UniqueFieldMappings implements ValidationRule
{
    /**
     * 🔍 Run the validation rule
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        // Filter out empty values and skip columns
        $mappedFields = collect($value)
            ->filter(fn ($field) => ! empty($field) && $field !== MappingFieldType::SKIP->value)
            ->values();

        // Find duplicates
        $duplicates = $mappedFields
            ->duplicates()
            ->unique()
            ->values();

        if ($duplicates->isNotEmpty()) {
            $duplicatesList = $duplicates->join(', ');
            $fail("The following fields are mapped multiple times: {$duplicatesList}");
        }
    }
}
