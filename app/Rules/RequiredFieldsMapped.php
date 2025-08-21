<?php

namespace App\Rules;

use App\Constants\ImportDefaults;
use App\Enums\MappingFieldType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 🎯✨ REQUIRED FIELDS MAPPED RULE ✨🎯
 *
 * Custom validation rule ensuring all required fields are mapped
 */
class RequiredFieldsMapped implements ValidationRule
{
    /**
     * 🔍 Run the validation rule
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        // Get mapped fields (excluding skipped ones)
        $mappedFields = collect($value)
            ->filter(fn ($field) => ! empty($field) && $field !== MappingFieldType::SKIP->value)
            ->values();

        // Check required fields
        $requiredFields = collect(ImportDefaults::REQUIRED_FIELDS);
        $missingFields = $requiredFields->diff($mappedFields);

        if ($missingFields->isNotEmpty()) {
            $missingList = $missingFields->join(', ');
            $fail("The following required fields must be mapped: {$missingList}");
        }
    }
}
