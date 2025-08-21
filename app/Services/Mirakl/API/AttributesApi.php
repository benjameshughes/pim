<?php

namespace App\Services\Mirakl\API;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 📋 MIRAKL ATTRIBUTES API (PM11)
 *
 * Focused API client for Mirakl PM11 product attributes endpoint.
 * Handles attribute retrieval, hierarchy filtering, and role-based filtering.
 */
class AttributesApi extends BaseMiraklApi
{
    /**
     * 📋 GET ATTRIBUTES WITH FILTERING
     *
     * Enhanced implementation following Mirakl PM11 documentation:
     * - Supports hierarchy filtering
     * - Max level depth control
     * - Sales channel attribute filtering
     * - Role-based attribute filtering
     *
     * @param  string|null  $hierarchy  Target category code for filtering
     * @param  array<string, mixed>  $options  Options: max_level, with_roles, all_operator_attributes
     * @return array<string, mixed>
     */
    public function getAttributes(?string $hierarchy = null, array $options = []): array
    {
        $maxLevel = $options['max_level'] ?? null;
        $withRoles = $options['with_roles'] ?? false;
        $allOperatorAttributes = $options['all_operator_attributes'] ?? false;

        $cacheKey = "mirakl_attributes_{$this->operator}_{$hierarchy}_".md5(serialize($options));

        return Cache::remember($cacheKey, 3600, function () use ($hierarchy, $maxLevel, $withRoles, $allOperatorAttributes, $options) {
            try {
                $query = [];

                // Hierarchy filtering (category-specific attributes)
                if ($hierarchy) {
                    $query['hierarchy'] = $hierarchy;
                }

                // Control tree depth (0 = only target category + parents)
                if ($maxLevel !== null) {
                    $query['max_level'] = $maxLevel;
                }

                // Get only attributes with roles
                if ($withRoles) {
                    $query['with_roles'] = 'true';
                }

                // Control attribute scope (sales channel vs all operator attributes)
                $query['all_operator_attributes'] = $allOperatorAttributes ? 'true' : 'false';

                $response = $this->client->request('GET', '/api/products/attributes', [
                    'query' => $query,
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                Log::info('📋 Retrieved attributes (PM11)', [
                    'operator' => $this->operator,
                    'hierarchy' => $hierarchy,
                    'max_level' => $maxLevel,
                    'with_roles' => $withRoles,
                    'all_operator_attributes' => $allOperatorAttributes,
                    'attributes_count' => count($data['attributes'] ?? []),
                ]);

                return $data['attributes'] ?? [];

            } catch (\Exception $e) {
                Log::error('❌ Failed to get attributes (PM11)', [
                    'operator' => $this->operator,
                    'error' => $e->getMessage(),
                    'options' => $options,
                ]);

                return [];
            }
        });
    }

    /**
     * ✅ GET REQUIRED ATTRIBUTES
     *
     * Get only required attributes for a category
     *
     * @param  string|null  $hierarchy  Target category code
     * @return array<string, mixed>
     */
    public function getRequiredAttributes(?string $hierarchy = null): array
    {
        $attributes = $this->getAttributes($hierarchy);

        return array_filter($attributes, function ($attribute) {
            return $attribute['required'] ?? false;
        });
    }

    /**
     * 🔗 GET OPTIONAL ATTRIBUTES
     *
     * Get only optional attributes for a category
     *
     * @param  string|null  $hierarchy  Target category code
     * @return array<string, mixed>
     */
    public function getOptionalAttributes(?string $hierarchy = null): array
    {
        $attributes = $this->getAttributes($hierarchy);

        return array_filter($attributes, function ($attribute) {
            return ! ($attribute['required'] ?? false);
        });
    }

    /**
     * 📊 GET ATTRIBUTES BY TYPE
     *
     * Group attributes by their type (TEXT, LIST, NUMERIC, etc.)
     *
     * @param  string|null  $hierarchy  Target category code
     * @return array<string, array<string, mixed>>
     */
    public function getAttributesByType(?string $hierarchy = null): array
    {
        $attributes = $this->getAttributes($hierarchy);
        $grouped = [];

        foreach ($attributes as $attribute) {
            $type = $attribute['type'] ?? 'UNKNOWN';
            if (! isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $attribute;
        }

        return $grouped;
    }

    /**
     * 📋 GET LIST ATTRIBUTES
     *
     * Get all attributes that require value lists (LIST, LIST_MULTIPLE_VALUES)
     *
     * @param  string|null  $hierarchy  Target category code
     * @return array<string, mixed>
     */
    public function getListAttributes(?string $hierarchy = null): array
    {
        $attributes = $this->getAttributes($hierarchy);

        return array_filter($attributes, function ($attribute) {
            $type = $attribute['type'] ?? '';

            return in_array($type, ['LIST', 'LIST_MULTIPLE_VALUES']);
        });
    }

    /**
     * 🎯 GET SPECIFIC ATTRIBUTE
     *
     * Get details for a specific attribute by code
     *
     * @param  string  $attributeCode  Attribute code to find
     * @param  string|null  $hierarchy  Target category code
     * @return array<string, mixed>
     */
    public function getAttribute(string $attributeCode, ?string $hierarchy = null): array
    {
        $attributes = $this->getAttributes($hierarchy);

        foreach ($attributes as $attribute) {
            if ($attribute['code'] === $attributeCode) {
                return $attribute;
            }
        }

        return [];
    }

    /**
     * 🔍 SEARCH ATTRIBUTES
     *
     * Search for attributes by label or code
     *
     * @param  string  $searchTerm  Search term
     * @param  string|null  $hierarchy  Target category code
     * @return array<string, mixed>
     */
    public function searchAttributes(string $searchTerm, ?string $hierarchy = null): array
    {
        $attributes = $this->getAttributes($hierarchy);
        $searchTerm = strtolower($searchTerm);

        return array_filter($attributes, function ($attribute) use ($searchTerm) {
            $label = strtolower($attribute['label'] ?? '');
            $code = strtolower($attribute['code'] ?? '');

            return str_contains($label, $searchTerm) || str_contains($code, $searchTerm);
        });
    }

    /**
     * 📊 GET ATTRIBUTE STATISTICS
     *
     * Get statistics about attributes for the operator
     *
     * @param  string|null  $hierarchy  Target category code
     * @return array<string, mixed>
     */
    public function getAttributeStatistics(?string $hierarchy = null): array
    {
        $attributes = $this->getAttributes($hierarchy);

        $stats = [
            'total_attributes' => count($attributes),
            'required_attributes' => 0,
            'optional_attributes' => 0,
            'by_type' => [],
            'with_roles' => 0,
            'list_attributes' => 0,
        ];

        foreach ($attributes as $attribute) {
            // Count required vs optional
            if ($attribute['required'] ?? false) {
                $stats['required_attributes']++;
            } else {
                $stats['optional_attributes']++;
            }

            // Count by type
            $type = $attribute['type'] ?? 'UNKNOWN';
            if (! isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = 0;
            }
            $stats['by_type'][$type]++;

            // Count attributes with roles
            if (! empty($attribute['roles'])) {
                $stats['with_roles']++;
            }

            // Count list attributes
            if (in_array($type, ['LIST', 'LIST_MULTIPLE_VALUES'])) {
                $stats['list_attributes']++;
            }
        }

        return $stats;
    }

    /**
     * 📋 EXPORT ATTRIBUTES TO CSV
     *
     * Export attributes to CSV format for analysis
     *
     * @param  string|null  $hierarchy  Target category hierarchy
     * @return array<string, mixed> File path and metadata
     */
    public function exportToCsv(?string $hierarchy = null): array
    {
        $attributes = $this->getAttributes($hierarchy);

        $csvData = [];
        $csvData[] = [
            'Code', 'Label', 'Type', 'Required', 'Default Value',
            'Description', 'Roles', 'Validation Pattern', 'Max Length',
        ];

        foreach ($attributes as $attribute) {
            $csvData[] = [
                $attribute['code'] ?? '',
                $attribute['label'] ?? '',
                $attribute['type'] ?? '',
                ($attribute['required'] ?? false) ? 'Yes' : 'No',
                $attribute['default_value'] ?? '',
                $attribute['description'] ?? '',
                implode(', ', $attribute['roles'] ?? []),
                $attribute['validation_pattern'] ?? '',
                $attribute['max_length'] ?? '',
            ];
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $hierarchySlug = $hierarchy ? str_replace('-', '_', $hierarchy) : 'all';
        $filename = "attributes_{$this->operator}_{$hierarchySlug}_{$timestamp}.csv";
        $filePath = "temp/mirakl_exports/{$filename}";

        // Ensure directory exists
        \Illuminate\Support\Facades\Storage::makeDirectory('temp/mirakl_exports');

        // Write CSV content
        $csvContent = $this->arrayToCsv($csvData);
        \Illuminate\Support\Facades\Storage::put($filePath, $csvContent);

        Log::info('📋 Attributes exported to CSV', [
            'operator' => $this->operator,
            'file_path' => $filePath,
            'attributes_count' => count($attributes),
            'hierarchy' => $hierarchy,
        ]);

        return [
            'file_path' => $filePath,
            'absolute_path' => \Illuminate\Support\Facades\Storage::path($filePath),
            'filename' => $filename,
            'attributes_count' => count($attributes),
            'file_size' => strlen($csvContent),
        ];
    }

    /**
     * 🏗️ BUILD FIELD MAPPING
     *
     * Build field mapping for CSV generation based on attributes
     *
     * @param  string|null  $hierarchy  Target category code
     * @return array<string, string> Mapping of field codes to labels
     */
    public function buildFieldMapping(?string $hierarchy = null): array
    {
        $attributes = $this->getAttributes($hierarchy);
        $mapping = [];

        foreach ($attributes as $attribute) {
            $code = $attribute['code'] ?? '';
            $label = $attribute['label'] ?? $code;

            if (! empty($code)) {
                $mapping[$code] = $label;
            }
        }

        return $mapping;
    }

    /**
     * 🔄 REFRESH ATTRIBUTE CACHE
     *
     * Clear cached attribute data for the operator
     *
     * @return bool Success status
     */
    public function refreshCache(): bool
    {
        // This is a simplified cache clearing approach
        Cache::forget("mirakl_attributes_{$this->operator}__".md5(serialize([])));

        Log::info('🔄 Attribute cache refreshed', [
            'operator' => $this->operator,
        ]);

        return true;
    }
}
