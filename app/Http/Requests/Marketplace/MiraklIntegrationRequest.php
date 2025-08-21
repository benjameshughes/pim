<?php

namespace App\Http\Requests\Marketplace;

/**
 * 🏢 MIRAKL INTEGRATION REQUEST
 *
 * Form validation for Mirakl marketplace integrations with operator support.
 * Handles B&Q, Debenhams, and Freemans operator configurations.
 */
class MiraklIntegrationRequest extends BaseMarketplaceRequest
{
    /**
     * 📋 GET VALIDATION RULES
     */
    public function rules(): array
    {
        $baseRules = [
            'marketplace_type' => ['required', 'in:mirakl'],
            'marketplace_subtype' => ['required', 'in:bq,debenhams,freemans'],
            'display_name' => ['required', 'string', 'max:255'],
            'credentials' => ['required', 'array'],
            'credentials.operator_type' => ['required', 'in:bq,debenhams,freemans'],
            'credentials.api_url' => ['required', 'url'],
            'credentials.api_key' => ['required', 'string'],
        ];

        // Add operator-specific validation rules
        $operatorRules = $this->getOperatorSpecificRules();

        // Add marketplace-specific validation rules
        $marketplaceRules = $this->getMarketplaceValidationRules();

        return array_merge($baseRules, $operatorRules, $marketplaceRules);
    }

    /**
     * 🏢 GET OPERATOR-SPECIFIC VALIDATION RULES
     */
    private function getOperatorSpecificRules(): array
    {
        $operator = $this->getMarketplaceOperator();

        return match ($operator) {
            'bq' => [
                'credentials.category_mapping' => ['required', 'array'],
                'credentials.eco_compliance' => ['required', 'boolean'],
                'credentials.lead_time_days' => ['required', 'integer', 'min:1', 'max:30'],
            ],
            'debenhams' => [
                'credentials.color_system' => ['required', 'in:standard,enhanced'],
                'credentials.barcode_type' => ['required', 'in:UPC,EAN'],
                'credentials.performance_metrics' => ['required', 'boolean'],
            ],
            'freemans' => [
                // Freemans has no specific requirements yet (investigation needed)
            ],
            default => [],
        };
    }

    /**
     * 📝 CUSTOM VALIDATION MESSAGES
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'marketplace_subtype.required' => 'Please select a Mirakl operator (B&Q, Debenhams, or Freemans).',
            'marketplace_subtype.in' => 'Invalid Mirakl operator selected.',
            'credentials.operator_type.required' => 'Operator type is required for Mirakl integrations.',
            'credentials.operator_type.in' => 'Invalid operator type.',
            'credentials.api_url.required' => 'Mirakl API URL is required.',
            'credentials.api_url.url' => 'API URL must be a valid URL.',
            'credentials.api_key.required' => 'Mirakl API key is required.',

            // B&Q specific messages
            'credentials.category_mapping.required' => 'Category mapping is required for B&Q integration.',
            'credentials.eco_compliance.required' => 'Eco compliance setting is required for B&Q.',
            'credentials.lead_time_days.required' => 'Lead time in days is required for B&Q.',
            'credentials.lead_time_days.min' => 'Lead time must be at least 1 day.',
            'credentials.lead_time_days.max' => 'Lead time cannot exceed 30 days.',

            // Debenhams specific messages
            'credentials.color_system.required' => 'Color system type is required for Debenhams.',
            'credentials.color_system.in' => 'Color system must be either standard or enhanced.',
            'credentials.barcode_type.required' => 'Barcode type is required for Debenhams.',
            'credentials.barcode_type.in' => 'Barcode type must be either UPC or EAN.',
            'credentials.performance_metrics.required' => 'Performance metrics setting is required for Debenhams.',
        ]);
    }

    /**
     * 🏷️ CUSTOM ATTRIBUTE NAMES
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'marketplace_subtype' => 'Mirakl operator',
            'credentials.operator_type' => 'operator type',
            'credentials.api_url' => 'API URL',
            'credentials.api_key' => 'API key',
            'credentials.category_mapping' => 'category mapping',
            'credentials.eco_compliance' => 'eco compliance',
            'credentials.lead_time_days' => 'lead time (days)',
            'credentials.color_system' => 'color system',
            'credentials.barcode_type' => 'barcode type',
            'credentials.performance_metrics' => 'performance metrics',
        ]);
    }

    /**
     * 🔧 PREPARE FOR VALIDATION
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // Sync marketplace_subtype with credentials.operator_type
        if ($this->has('marketplace_subtype')) {
            $operatorType = $this->input('marketplace_subtype');

            $this->merge([
                'credentials' => array_merge($this->input('credentials', []), [
                    'operator_type' => $operatorType,
                ]),
            ]);
        }

        // Normalize API URL
        if ($this->has('credentials.api_url')) {
            $apiUrl = $this->input('credentials.api_url');
            $apiUrl = rtrim($apiUrl, '/');

            $this->merge([
                'credentials' => array_merge($this->input('credentials', []), [
                    'api_url' => $apiUrl,
                ]),
            ]);
        }
    }

    /**
     * 🏢 GET OPERATOR DISPLAY NAMES
     */
    public function getOperatorDisplayNames(): array
    {
        return [
            'bq' => 'B&Q Marketplace',
            'debenhams' => 'Debenhams Marketplace',
            'freemans' => 'Freemans (Frasers Group)',
        ];
    }

    /**
     * 📋 GET OPERATOR REQUIREMENTS
     */
    public function getOperatorRequirements(): array
    {
        return [
            'bq' => [
                'description' => 'B&Q home improvement marketplace',
                'currency' => 'GBP',
                'categories_count' => 18,
                'required_fields' => ['category_mapping', 'eco_compliance', 'lead_time_days'],
                'notes' => 'Requires category mapping and eco-compliance documentation.',
            ],
            'debenhams' => [
                'description' => 'Debenhams fashion and lifestyle marketplace',
                'currency' => 'GBP',
                'required_fields' => ['color_system', 'barcode_type', 'performance_metrics'],
                'notes' => 'Supports both standard and enhanced color systems.',
            ],
            'freemans' => [
                'description' => 'Freemans catalog and online marketplace',
                'currency' => 'GBP',
                'required_fields' => [],
                'status' => 'investigation_needed',
                'notes' => 'Integration specifications are still being investigated.',
            ],
        ];
    }
}
