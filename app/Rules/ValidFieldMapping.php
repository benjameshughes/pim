<?php

namespace App\Rules;

use App\Constants\ImportDefaults;
use App\Enums\MappingFieldType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 🗺️✨ VALID FIELD MAPPING RULE ✨🗺️
 *
 * Custom validation rule for ensuring field mappings are valid
 */
class ValidFieldMapping implements ValidationRule
{
    /**
     * 🔍 Run the validation rule
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // Allow empty values (unmapped fields)
        }

        // Allow special mapping types
        if ($value === MappingFieldType::SKIP->value) {
            return;
        }

        // Check against available field names
        $availableFields = array_merge(
            ImportDefaults::STRING_FIELDS,
            ImportDefaults::NUMERIC_FIELDS,
            ImportDefaults::URL_FIELDS
        );

        if (! in_array($value, $availableFields)) {
            $fail("'{$value}' is not a valid field name.");
        }
    }
}
