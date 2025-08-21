<?php

namespace App\Http\Requests\Import;

use App\Constants\ImportDefaults;
use App\Enums\MappingFieldType;
use App\Rules\RequiredFieldsMapped;
use App\Rules\UniqueFieldMappings;
use App\Rules\ValidFieldMapping;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 🗺️✨ MAPPING CONFIGURATION REQUEST VALIDATION ✨🗺️
 *
 * Laravel Form Request for validating field mapping configurations,
 * replacing manual validation logic with proper Laravel conventions
 */
class MappingConfigurationRequest extends FormRequest
{
    /**
     * 🔐 Determine if the user is authorized to make this request
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * 📋 Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
            'mapping' => [
                'required',
                'array',
                'min:1',
                'max:'.ImportDefaults::MAX_HEADER_COUNT,
                new UniqueFieldMappings,
                new RequiredFieldsMapped,
            ],
            'mapping.*' => [
                'nullable',
                'string',
                'max:'.ImportDefaults::MAX_FIELD_NAME_LENGTH,
                new ValidFieldMapping,
            ],
            'skip_columns' => [
                'nullable',
                'array',
                'max:'.ImportDefaults::MAX_HEADER_COUNT,
            ],
            'skip_columns.*' => [
                'string',
                'max:255',
            ],
            'template_id' => [
                'nullable',
                'string',
                'max:50',
            ],
            'auto_map_confident' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * 🎨 Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'mapping.required' => 'Field mapping configuration is required.',
            'mapping.array' => 'Mapping must be a valid configuration object.',
            'mapping.min' => 'At least one field mapping is required.',
            'mapping.max' => 'Too many field mappings. Maximum allowed: '.ImportDefaults::MAX_HEADER_COUNT.'.',
            'mapping.*.max' => 'Field name is too long. Maximum length: '.ImportDefaults::MAX_FIELD_NAME_LENGTH.' characters.',
            'skip_columns.array' => 'Skip columns must be a valid list.',
            'skip_columns.max' => 'Too many columns to skip. Maximum allowed: '.ImportDefaults::MAX_HEADER_COUNT.'.',
            'skip_columns.*.string' => 'Column name must be a valid string.',
            'skip_columns.*.max' => 'Column name is too long.',
            'template_id.max' => 'Template ID is too long.',
            'auto_map_confident.boolean' => 'Auto-map setting must be true or false.',
        ];
    }

    /**
     * 🏷️ Get custom attribute names for validation messages
     */
    public function attributes(): array
    {
        return [
            'mapping' => 'field mapping',
            'mapping.*' => 'field mapping',
            'skip_columns' => 'skip columns',
            'skip_columns.*' => 'column name',
            'template_id' => 'template',
            'auto_map_confident' => 'auto-mapping setting',
        ];
    }

    /**
     * 🛠️ Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateMappingConsistency($validator);
            $this->validateSkipColumnConsistency($validator);
            $this->validateRequiredFieldCoverage($validator);
        });
    }

    /**
     * 🔍 Validate mapping consistency
     */
    private function validateMappingConsistency($validator): void
    {
        $mapping = $this->input('mapping', []);
        $skipColumns = $this->input('skip_columns', []);

        // Check for conflicts between mapping and skip columns
        $mappedHeaders = collect(array_keys($mapping))->filter(fn ($key) => ! empty($mapping[$key]));
        $skippedHeaders = collect($skipColumns);

        $conflicts = $mappedHeaders->intersect($skippedHeaders);
        if ($conflicts->isNotEmpty()) {
            $validator->errors()->add('mapping',
                'The following headers cannot be both mapped and skipped: '.$conflicts->join(', ')
            );
        }
    }

    /**
     * 🔍 Validate skip column consistency
     */
    private function validateSkipColumnConsistency($validator): void
    {
        $mapping = $this->input('mapping', []);
        $skipColumns = $this->input('skip_columns', []);

        // Ensure skip columns aren't mapped to actual fields
        foreach ($skipColumns as $header) {
            if (isset($mapping[$header]) &&
                $mapping[$header] !== MappingFieldType::SKIP->value &&
                ! empty($mapping[$header])) {
                $validator->errors()->add('skip_columns',
                    "Header '{$header}' is marked to skip but also has a field mapping."
                );
            }
        }
    }

    /**
     * 🎯 Validate required field coverage
     */
    private function validateRequiredFieldCoverage($validator): void
    {
        $mapping = $this->input('mapping', []);
        $mappedFields = collect(array_values($mapping))
            ->filter(fn ($field) => ! empty($field) && $field !== MappingFieldType::SKIP->value);

        $requiredFields = collect(ImportDefaults::REQUIRED_FIELDS);
        $missingFields = $requiredFields->diff($mappedFields);

        if ($missingFields->isNotEmpty()) {
            $validator->errors()->add('mapping',
                'The following required fields must be mapped: '.$missingFields->join(', ')
            );
        }
    }

    /**
     * 📊 Get processed and cleaned mapping data
     */
    public function getCleanedMapping(): array
    {
        return collect($this->validated('mapping'))
            ->reject(fn ($value) => empty($value) || $value === MappingFieldType::SKIP->value)
            ->toArray();
    }

    /**
     * 🚫 Get list of columns to skip during import
     */
    public function getSkipColumns(): array
    {
        $explicitSkips = $this->validated('skip_columns') ?? [];
        $mappingSkips = collect($this->validated('mapping'))
            ->filter(fn ($value) => $value === MappingFieldType::SKIP->value)
            ->keys()
            ->toArray();

        return array_unique(array_merge($explicitSkips, $mappingSkips));
    }

    /**
     * 📋 Get mapping statistics
     */
    public function getMappingStatistics(): array
    {
        $mapping = $this->input('mapping', []);
        $skipColumns = $this->getSkipColumns();

        $totalHeaders = count($mapping);
        $mappedHeaders = count($this->getCleanedMapping());
        $skippedHeaders = count($skipColumns);
        $unmappedHeaders = $totalHeaders - $mappedHeaders - $skippedHeaders;

        return [
            'total_headers' => $totalHeaders,
            'mapped_headers' => $mappedHeaders,
            'skipped_headers' => $skippedHeaders,
            'unmapped_headers' => max(0, $unmappedHeaders),
            'mapping_percentage' => $totalHeaders > 0
                ? round(($mappedHeaders / $totalHeaders) * 100, 1)
                : 0,
            'completion_status' => $this->getCompletionStatus($mappedHeaders, $totalHeaders),
        ];
    }

    /**
     * 🎯 Get completion status based on mapping coverage
     */
    private function getCompletionStatus(int $mapped, int $total): string
    {
        if ($total === 0) {
            return 'empty';
        }

        $percentage = ($mapped / $total) * 100;

        return match (true) {
            $percentage >= 90 => 'excellent',
            $percentage >= 70 => 'good',
            $percentage >= 50 => 'fair',
            $percentage >= 25 => 'poor',
            default => 'incomplete'
        };
    }

    /**
     * 🔍 Get field mapping conflicts
     */
    public function getMappingConflicts(): array
    {
        $mapping = $this->getCleanedMapping();
        $fieldCounts = array_count_values($mapping);

        return collect($fieldCounts)
            ->filter(fn ($count) => $count > 1)
            ->keys()
            ->map(fn ($field) => [
                'field' => $field,
                'headers' => array_keys($mapping, $field),
                'count' => $fieldCounts[$field],
            ])
            ->values()
            ->toArray();
    }

    /**
     * ✅ Check if mapping configuration is valid for import
     */
    public function isReadyForImport(): bool
    {
        $statistics = $this->getMappingStatistics();
        $conflicts = $this->getMappingConflicts();

        return $statistics['mapped_headers'] >= ImportDefaults::MIN_REQUIRED_FIELDS &&
               empty($conflicts) &&
               $this->hasRequiredFields();
    }

    /**
     * 🎯 Check if all required fields are mapped
     */
    public function hasRequiredFields(): bool
    {
        $mappedFields = collect($this->getCleanedMapping())->values();
        $requiredFields = collect(ImportDefaults::REQUIRED_FIELDS);

        return $requiredFields->every(fn ($field) => $mappedFields->contains($field));
    }

    /**
     * 📋 Get validation summary for UI display
     */
    public function getValidationSummary(): array
    {
        return [
            'is_valid' => $this->isReadyForImport(),
            'statistics' => $this->getMappingStatistics(),
            'conflicts' => $this->getMappingConflicts(),
            'missing_required' => $this->getMissingRequiredFields(),
            'recommendations' => $this->getRecommendations(),
        ];
    }

    /**
     * 📋 Get missing required fields
     */
    public function getMissingRequiredFields(): array
    {
        $mappedFields = collect($this->getCleanedMapping())->values();
        $requiredFields = collect(ImportDefaults::REQUIRED_FIELDS);

        return $requiredFields->diff($mappedFields)->values()->toArray();
    }

    /**
     * 💡 Get recommendations for improving the mapping
     */
    public function getRecommendations(): array
    {
        $recommendations = [];
        $statistics = $this->getMappingStatistics();

        if ($statistics['unmapped_headers'] > 0) {
            $recommendations[] = [
                'type' => 'unmapped_headers',
                'message' => "You have {$statistics['unmapped_headers']} unmapped headers. Consider mapping them or marking them to skip.",
                'action' => 'Review unmapped headers',
            ];
        }

        if ($statistics['mapping_percentage'] < 50) {
            $recommendations[] = [
                'type' => 'low_coverage',
                'message' => 'Low mapping coverage. Consider using a template or auto-mapping feature.',
                'action' => 'Use auto-mapping or template',
            ];
        }

        $conflicts = $this->getMappingConflicts();
        if (! empty($conflicts)) {
            $recommendations[] = [
                'type' => 'conflicts',
                'message' => 'Some fields are mapped multiple times. Please resolve conflicts before importing.',
                'action' => 'Fix duplicate mappings',
            ];
        }

        return $recommendations;
    }
}
