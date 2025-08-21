<?php

namespace App\Services\Mirakl\API;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 📊 MIRAKL VALUE LISTS API (VL11)
 *
 * Focused API client for Mirakl VL11 value lists endpoint.
 * Handles value list retrieval, validation, and caching.
 */
class ValueListsApi extends BaseMiraklApi
{
    /**
     * 📊 GET ALL VALUE LISTS
     *
     * Enhanced implementation following Mirakl VL11 documentation:
     * - Bulk retrieval of all value lists
     * - Optimized caching per value list
     * - Associative array format for easy lookup
     *
     * @return array<string, mixed> Array of value lists keyed by code
     */
    public function getAllValueLists(): array
    {
        $cacheKey = "mirakl_values_lists_all_{$this->operator}";

        return Cache::remember($cacheKey, 3600, function () {
            try {
                $response = $this->client->request('GET', '/api/values_lists');
                $data = json_decode($response->getBody()->getContents(), true);

                // Convert to associative array for easy lookup
                $valueLists = [];
                foreach ($data['values_lists'] ?? [] as $valueList) {
                    $valueLists[$valueList['code']] = $valueList;

                    // Cache individual value lists for faster future access
                    $individualCacheKey = "mirakl_value_list_{$this->operator}_{$valueList['code']}";
                    Cache::put($individualCacheKey, $valueList, 3600);
                }

                Log::info('📊 Retrieved all value lists (VL11)', [
                    'operator' => $this->operator,
                    'value_lists_count' => count($valueLists),
                ]);

                return $valueLists;

            } catch (\Exception $e) {
                Log::error('❌ Failed to get value lists (VL11)', [
                    'operator' => $this->operator,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * 🎯 GET SPECIFIC VALUE LIST
     *
     * Retrieve a single value list by code for optimized performance
     *
     * @param  string  $code  Value list code
     * @return array<string, mixed> Value list data
     */
    public function getValueList(string $code): array
    {
        $cacheKey = "mirakl_value_list_{$this->operator}_{$code}";

        return Cache::remember($cacheKey, 3600, function () use ($code) {
            try {
                $response = $this->client->request('GET', '/api/values_lists', [
                    'query' => ['code' => $code],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                $valueList = $data['values_lists'][0] ?? [];

                Log::info('📊 Retrieved specific value list (VL11)', [
                    'operator' => $this->operator,
                    'code' => $code,
                    'values_count' => count($valueList['values'] ?? []),
                ]);

                return $valueList;

            } catch (\Exception $e) {
                Log::error('❌ Failed to get specific value list (VL11)', [
                    'operator' => $this->operator,
                    'code' => $code,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * 🎯 GET MULTIPLE VALUE LISTS
     *
     * Get multiple specific value lists by codes
     *
     * @param  array<string>  $codes  Array of value list codes
     * @return array<string, mixed> Array of value lists keyed by code
     */
    public function getValueLists(array $codes): array
    {
        $results = [];

        foreach ($codes as $code) {
            $valueList = $this->getValueList($code);
            if (! empty($valueList)) {
                $results[$code] = $valueList;
            }
        }

        return $results;
    }

    /**
     * ✅ GET VALID VALUES FOR FIELD
     *
     * Get all valid values for a specific field/attribute
     *
     * @param  string  $code  Value list code
     * @return array<string> Array of valid value codes
     */
    public function getValidValues(string $code): array
    {
        $valueList = $this->getValueList($code);

        if (empty($valueList['values'])) {
            return [];
        }

        return array_column($valueList['values'], 'code');
    }

    /**
     * 🔍 SEARCH VALUE LISTS
     *
     * Search for value lists by code or label
     *
     * @param  string  $searchTerm  Search term
     * @return array<string, mixed>
     */
    public function searchValueLists(string $searchTerm): array
    {
        $allValueLists = $this->getAllValueLists();
        $searchTerm = strtolower($searchTerm);

        return array_filter($allValueLists, function ($valueList) use ($searchTerm) {
            $label = strtolower($valueList['label'] ?? '');
            $code = strtolower($valueList['code'] ?? '');

            return str_contains($label, $searchTerm) || str_contains($code, $searchTerm);
        });
    }

    /**
     * 🔍 SEARCH VALUES WITHIN LIST
     *
     * Search for specific values within a value list
     *
     * @param  string  $listCode  Value list code
     * @param  string  $searchTerm  Search term
     * @return array<string, mixed>
     */
    public function searchValuesInList(string $listCode, string $searchTerm): array
    {
        $valueList = $this->getValueList($listCode);
        $values = $valueList['values'] ?? [];
        $searchTerm = strtolower($searchTerm);

        return array_filter($values, function ($value) use ($searchTerm) {
            $label = strtolower($value['label'] ?? '');
            $code = strtolower($value['code'] ?? '');

            return str_contains($label, $searchTerm) || str_contains($code, $searchTerm);
        });
    }

    /**
     * ✅ VALIDATE VALUE
     *
     * Check if a value is valid for a specific value list
     *
     * @param  string  $listCode  Value list code
     * @param  string  $value  Value to validate
     * @return bool True if valid
     */
    public function isValidValue(string $listCode, string $value): bool
    {
        $validValues = $this->getValidValues($listCode);

        return in_array($value, $validValues);
    }

    /**
     * 🔧 GET FIRST VALID VALUE
     *
     * Get the first valid value for a value list (useful for fallbacks)
     *
     * @param  string  $listCode  Value list code
     * @return string|null First valid value or null
     */
    public function getFirstValidValue(string $listCode): ?string
    {
        $validValues = $this->getValidValues($listCode);

        return ! empty($validValues) ? $validValues[0] : null;
    }

    /**
     * 📊 GET VALUE LIST STATISTICS
     *
     * Get statistics about value lists for the operator
     *
     * @return array<string, mixed>
     */
    public function getValueListStatistics(): array
    {
        $valueLists = $this->getAllValueLists();

        $stats = [
            'total_value_lists' => count($valueLists),
            'total_values' => 0,
            'largest_list' => null,
            'largest_list_size' => 0,
            'smallest_list' => null,
            'smallest_list_size' => PHP_INT_MAX,
            'average_list_size' => 0,
        ];

        foreach ($valueLists as $code => $valueList) {
            $valueCount = count($valueList['values'] ?? []);
            $stats['total_values'] += $valueCount;

            // Track largest list
            if ($valueCount > $stats['largest_list_size']) {
                $stats['largest_list_size'] = $valueCount;
                $stats['largest_list'] = $code;
            }

            // Track smallest list
            if ($valueCount < $stats['smallest_list_size']) {
                $stats['smallest_list_size'] = $valueCount;
                $stats['smallest_list'] = $code;
            }
        }

        // Calculate average
        if (count($valueLists) > 0) {
            $stats['average_list_size'] = round($stats['total_values'] / count($valueLists), 1);
        }

        return $stats;
    }

    /**
     * 📋 EXPORT VALUE LISTS TO CSV
     *
     * Export value lists to CSV format for analysis
     *
     * @param  array<string>|null  $codes  Specific value list codes to export, or null for all
     * @return array<string, mixed> File path and metadata
     */
    public function exportToCsv(?array $codes = null): array
    {
        if ($codes === null) {
            $valueLists = $this->getAllValueLists();
        } else {
            $valueLists = $this->getValueLists($codes);
        }

        $csvData = [];
        $csvData[] = ['List Code', 'List Label', 'Value Code', 'Value Label'];

        foreach ($valueLists as $listCode => $valueList) {
            $listLabel = $valueList['label'] ?? $listCode;

            foreach ($valueList['values'] ?? [] as $value) {
                $csvData[] = [
                    $listCode,
                    $listLabel,
                    $value['code'] ?? '',
                    $value['label'] ?? '',
                ];
            }
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $codesSlug = $codes ? implode('_', $codes) : 'all';
        $filename = "value_lists_{$this->operator}_{$codesSlug}_{$timestamp}.csv";
        $filePath = "temp/mirakl_exports/{$filename}";

        // Ensure directory exists
        \Illuminate\Support\Facades\Storage::makeDirectory('temp/mirakl_exports');

        // Write CSV content
        $csvContent = $this->arrayToCsv($csvData);
        \Illuminate\Support\Facades\Storage::put($filePath, $csvContent);

        Log::info('📋 Value lists exported to CSV', [
            'operator' => $this->operator,
            'file_path' => $filePath,
            'value_lists_count' => count($valueLists),
            'codes' => $codes,
        ]);

        return [
            'file_path' => $filePath,
            'absolute_path' => \Illuminate\Support\Facades\Storage::path($filePath),
            'filename' => $filename,
            'value_lists_count' => count($valueLists),
            'file_size' => strlen($csvContent),
        ];
    }

    /**
     * 🗂️ EXPORT DETAILED VALUE LISTS
     *
     * Export detailed value lists with metadata
     *
     * @param  array<string>|null  $codes  Specific value list codes
     * @return array<string, mixed> Detailed export data
     */
    public function exportDetailed(?array $codes = null): array
    {
        if ($codes === null) {
            $valueLists = $this->getAllValueLists();
        } else {
            $valueLists = $this->getValueLists($codes);
        }

        $export = [
            'operator' => $this->operator,
            'export_timestamp' => now()->toISOString(),
            'value_lists_count' => count($valueLists),
            'value_lists' => [],
        ];

        foreach ($valueLists as $code => $valueList) {
            $export['value_lists'][$code] = [
                'code' => $code,
                'label' => $valueList['label'] ?? '',
                'values_count' => count($valueList['values'] ?? []),
                'values' => $valueList['values'] ?? [],
            ];
        }

        return $export;
    }

    /**
     * 🔄 REFRESH VALUE LISTS CACHE
     *
     * Clear cached value list data for the operator
     *
     * @return bool Success status
     */
    public function refreshCache(): bool
    {
        // Clear main cache
        Cache::forget("mirakl_values_lists_all_{$this->operator}");

        // Clear individual value list caches (simplified approach)
        $valueLists = $this->getAllValueLists();
        foreach (array_keys($valueLists) as $code) {
            Cache::forget("mirakl_value_list_{$this->operator}_{$code}");
        }

        Log::info('🔄 Value lists cache refreshed', [
            'operator' => $this->operator,
        ]);

        return true;
    }
}
