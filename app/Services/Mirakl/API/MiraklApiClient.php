<?php

namespace App\Services\Mirakl\API;

use Illuminate\Support\Facades\Log;

/**
 * 🌐 UNIFIED MIRAKL API CLIENT
 *
 * Complete Mirakl API client providing access to all three core APIs:
 * - H11 Categories API
 * - PM11 Attributes API
 * - VL11 Value Lists API
 *
 * This client provides a unified interface for Mirakl catalog structure operations.
 */
class MiraklApiClient
{
    protected string $operator;

    protected CategoriesApi $categoriesApi;

    protected AttributesApi $attributesApi;

    protected ValueListsApi $valueListsApi;

    public function __construct(string $operator)
    {
        $this->operator = $operator;
        $this->categoriesApi = new CategoriesApi($operator);
        $this->attributesApi = new AttributesApi($operator);
        $this->valueListsApi = new ValueListsApi($operator);
    }

    /**
     * 🏭 STATIC FACTORY METHOD
     */
    public static function for(string $operator): static
    {
        return new static($operator);
    }

    /**
     * 🗂️ CATEGORIES API ACCESS
     *
     * Get access to the H11 Categories API
     */
    public function categories(): CategoriesApi
    {
        return $this->categoriesApi;
    }

    /**
     * 📋 ATTRIBUTES API ACCESS
     *
     * Get access to the PM11 Attributes API
     */
    public function attributes(): AttributesApi
    {
        return $this->attributesApi;
    }

    /**
     * 📊 VALUE LISTS API ACCESS
     *
     * Get access to the VL11 Value Lists API
     */
    public function valueLists(): ValueListsApi
    {
        return $this->valueListsApi;
    }

    /**
     * 🔍 EXPORT COMPLETE CATALOG STRUCTURE
     *
     * Export complete catalog structure for an operator including:
     * - Categories (H11)
     * - Attributes (PM11)
     * - Value lists (VL11)
     *
     * @param  string|null  $hierarchy  Target category for filtering
     * @param  array<string, mixed>  $options  Options for all APIs
     * @return array<string, mixed> Complete catalog structure
     */
    public function exportCompleteCatalogStructure(?string $hierarchy = null, array $options = []): array
    {
        Log::info('🔍 Exporting complete catalog structure', [
            'operator' => $this->operator,
            'hierarchy' => $hierarchy,
            'options' => $options,
        ]);

        $startTime = microtime(true);

        $catalogStructure = [
            'operator' => $this->operator,
            'hierarchy' => $hierarchy,
            'export_timestamp' => now()->toISOString(),
            'categories' => $this->categoriesApi->getCategories($hierarchy, $options),
            'attributes' => $this->attributesApi->getAttributes($hierarchy, $options),
            'value_lists' => $this->valueListsApi->getAllValueLists(),
        ];

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('✅ Complete catalog structure exported', [
            'operator' => $this->operator,
            'duration_ms' => $duration,
            'categories_count' => count($catalogStructure['categories']),
            'attributes_count' => count($catalogStructure['attributes']),
            'value_lists_count' => count($catalogStructure['value_lists']),
        ]);

        return $catalogStructure;
    }

    /**
     * 📊 GET COMPLETE STATISTICS
     *
     * Get comprehensive statistics across all APIs
     *
     * @param  string|null  $hierarchy  Target category for filtering
     * @return array<string, mixed> Complete statistics
     */
    public function getCompleteStatistics(?string $hierarchy = null): array
    {
        Log::info('📊 Generating complete statistics', [
            'operator' => $this->operator,
            'hierarchy' => $hierarchy,
        ]);

        return [
            'operator' => $this->operator,
            'hierarchy' => $hierarchy,
            'generated_at' => now()->toISOString(),
            'categories' => $this->categoriesApi->getCategoryStatistics(),
            'attributes' => $this->attributesApi->getAttributeStatistics($hierarchy),
            'value_lists' => $this->valueListsApi->getValueListStatistics(),
        ];
    }

    /**
     * 📋 EXPORT ALL TO CSV
     *
     * Export all catalog data to separate CSV files
     *
     * @param  string|null  $hierarchy  Target category for filtering
     * @return array<string, mixed> Export results with file paths
     */
    public function exportAllToCsv(?string $hierarchy = null): array
    {
        Log::info('📋 Exporting all data to CSV', [
            'operator' => $this->operator,
            'hierarchy' => $hierarchy,
        ]);

        $startTime = microtime(true);

        $exports = [
            'categories' => $this->categoriesApi->exportToCsv($hierarchy),
            'attributes' => $this->attributesApi->exportToCsv($hierarchy),
            'value_lists' => $this->valueListsApi->exportToCsv(),
        ];

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('✅ All data exported to CSV', [
            'operator' => $this->operator,
            'duration_ms' => $duration,
            'exports' => array_map(fn ($export) => $export['filename'], $exports),
        ]);

        return [
            'operator' => $this->operator,
            'hierarchy' => $hierarchy,
            'export_timestamp' => now()->toISOString(),
            'duration_ms' => $duration,
            'exports' => $exports,
            'total_files' => count($exports),
        ];
    }

    /**
     * 🔄 REFRESH ALL CACHES
     *
     * Clear all cached data across all APIs
     *
     * @return array<string, mixed> Refresh results
     */
    public function refreshAllCaches(): array
    {
        Log::info('🔄 Refreshing all caches', [
            'operator' => $this->operator,
        ]);

        $results = [
            'categories' => $this->categoriesApi->refreshCache(),
            'attributes' => $this->attributesApi->refreshCache(),
            'value_lists' => $this->valueListsApi->refreshCache(),
        ];

        $allSuccess = ! in_array(false, $results);

        Log::info('✅ All caches refreshed', [
            'operator' => $this->operator,
            'success' => $allSuccess,
            'results' => $results,
        ]);

        return [
            'operator' => $this->operator,
            'success' => $allSuccess,
            'results' => $results,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * ✅ TEST ALL CONNECTIONS
     *
     * Test connectivity to all APIs
     *
     * @return array<string, mixed> Connection test results
     */
    public function testAllConnections(): array
    {
        Log::info('✅ Testing all API connections', [
            'operator' => $this->operator,
        ]);

        $results = [
            'categories' => $this->categoriesApi->testConnection(),
            'attributes' => $this->attributesApi->testConnection(),
            'value_lists' => $this->valueListsApi->testConnection(),
        ];

        $allSuccess = collect($results)->every(fn ($result) => $result['success'] ?? false);

        Log::info('✅ All API connections tested', [
            'operator' => $this->operator,
            'all_success' => $allSuccess,
        ]);

        return [
            'operator' => $this->operator,
            'all_success' => $allSuccess,
            'results' => $results,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * 🔍 GET OPERATOR SUMMARY
     *
     * Get a complete summary of the operator's API capabilities
     *
     * @return array<string, mixed> Operator summary
     */
    public function getOperatorSummary(): array
    {
        return [
            'operator' => $this->operator,
            'operator_info' => $this->categoriesApi->getOperatorInfo(),
            'api_capabilities' => [
                'categories' => 'H11 Categories API - Category tree structure',
                'attributes' => 'PM11 Attributes API - Product attribute definitions',
                'value_lists' => 'VL11 Value Lists API - Valid values for LIST fields',
            ],
            'supported_operations' => [
                'catalog_export' => 'Export complete catalog structure',
                'csv_export' => 'Export data to CSV files',
                'statistics' => 'Generate comprehensive statistics',
                'search' => 'Search across categories, attributes, and values',
                'validation' => 'Validate values against value lists',
                'caching' => 'Smart caching with refresh capabilities',
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * 🎯 BUILD FIELD VALIDATION RULES
     *
     * Build validation rules for CSV generation based on attributes and value lists
     *
     * @param  string|null  $hierarchy  Target category code
     * @return array<string, mixed> Validation rules
     */
    public function buildFieldValidationRules(?string $hierarchy = null): array
    {
        $attributes = $this->attributesApi->getAttributes($hierarchy);
        $allValueLists = $this->valueListsApi->getAllValueLists();

        $validationRules = [];

        foreach ($attributes as $attribute) {
            $code = $attribute['code'] ?? '';
            $type = $attribute['type'] ?? '';
            $required = $attribute['required'] ?? false;

            if (empty($code)) {
                continue;
            }

            $rule = [
                'code' => $code,
                'type' => $type,
                'required' => $required,
                'validation' => [],
            ];

            // Add type-specific validation
            switch ($type) {
                case 'LIST':
                case 'LIST_MULTIPLE_VALUES':
                    if (isset($allValueLists[$code])) {
                        $rule['validation']['valid_values'] = array_column(
                            $allValueLists[$code]['values'] ?? [],
                            'code'
                        );
                    }
                    break;

                case 'NUMERIC':
                    $rule['validation']['type'] = 'numeric';
                    if (isset($attribute['min_value'])) {
                        $rule['validation']['min'] = $attribute['min_value'];
                    }
                    if (isset($attribute['max_value'])) {
                        $rule['validation']['max'] = $attribute['max_value'];
                    }
                    break;

                case 'TEXT':
                    $rule['validation']['type'] = 'string';
                    if (isset($attribute['max_length'])) {
                        $rule['validation']['max_length'] = $attribute['max_length'];
                    }
                    break;
            }

            $validationRules[$code] = $rule;
        }

        return [
            'operator' => $this->operator,
            'hierarchy' => $hierarchy,
            'validation_rules' => $validationRules,
            'rules_count' => count($validationRules),
            'generated_at' => now()->toISOString(),
        ];
    }
}
