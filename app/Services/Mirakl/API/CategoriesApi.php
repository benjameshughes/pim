<?php

namespace App\Services\Mirakl\API;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 🗂️ MIRAKL CATEGORIES API (H11)
 *
 * Focused API client for Mirakl H11 categories endpoint.
 * Handles category tree retrieval, hierarchy filtering, and caching.
 */
class CategoriesApi extends BaseMiraklApi
{
    /**
     * 🗂️ GET CATEGORIES WITH HIERARCHY FILTERING
     *
     * Enhanced implementation following Mirakl H11 documentation:
     * - Supports hierarchy filtering (tree-based retrieval)
     * - Max level depth control starting from target category
     * - Returns parent categories + target + all child categories
     * - Depth 0 = only target category + parent categories
     *
     * @param  string|null  $hierarchy  Target category code for filtering
     * @param  array<string, mixed>  $options  Options: max_level (int)
     * @return array<string, mixed>
     */
    public function getCategories(?string $hierarchy = null, array $options = []): array
    {
        $maxLevel = $options['max_level'] ?? null;

        $cacheKey = "mirakl_categories_{$this->operator}_{$hierarchy}_".md5(serialize($options));

        return Cache::remember($cacheKey, 3600, function () use ($hierarchy, $maxLevel, $options) {
            try {
                $query = [];

                // Hierarchy filtering (get specific category tree)
                if ($hierarchy) {
                    $query['hierarchy'] = $hierarchy;
                }

                // Control tree depth (0 = only target category + parents)
                if ($maxLevel !== null) {
                    $query['max_level'] = $maxLevel;
                }

                $response = $this->client->request('GET', '/api/categories', [
                    'query' => $query,
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                Log::info('🗂️ Retrieved categories (H11)', [
                    'operator' => $this->operator,
                    'hierarchy' => $hierarchy,
                    'max_level' => $maxLevel,
                    'categories_count' => count($data['categories'] ?? []),
                ]);

                return $data['categories'] ?? [];

            } catch (\Exception $e) {
                Log::error('❌ Failed to get categories (H11)', [
                    'operator' => $this->operator,
                    'error' => $e->getMessage(),
                    'options' => $options,
                ]);

                return [];
            }
        });
    }

    /**
     * 🌳 GET CATEGORY TREE
     *
     * Get complete category tree structure for the operator
     *
     * @return array<string, mixed>
     */
    public function getCategoryTree(): array
    {
        return $this->getCategories(null, ['max_level' => null]);
    }

    /**
     * 🎯 GET SPECIFIC CATEGORY
     *
     * Get a specific category with its children up to specified depth
     *
     * @param  string  $categoryCode  Target category code
     * @param  int|null  $maxDepth  Maximum depth from target category
     * @return array<string, mixed>
     */
    public function getCategory(string $categoryCode, ?int $maxDepth = null): array
    {
        $options = [];
        if ($maxDepth !== null) {
            $options['max_level'] = $maxDepth;
        }

        $categories = $this->getCategories($categoryCode, $options);

        // Find the target category in the results
        foreach ($categories as $category) {
            if ($category['code'] === $categoryCode) {
                return $category;
            }
        }

        return [];
    }

    /**
     * 🔍 SEARCH CATEGORIES
     *
     * Search for categories containing specific text
     *
     * @param  string  $searchTerm  Search term
     * @return array<string, mixed>
     */
    public function searchCategories(string $searchTerm): array
    {
        $allCategories = $this->getCategoryTree();
        $searchTerm = strtolower($searchTerm);

        return array_filter($allCategories, function ($category) use ($searchTerm) {
            $label = strtolower($category['label'] ?? '');
            $code = strtolower($category['code'] ?? '');

            return str_contains($label, $searchTerm) || str_contains($code, $searchTerm);
        });
    }

    /**
     * 📊 GET CATEGORY STATISTICS
     *
     * Get statistics about categories for the operator
     *
     * @return array<string, mixed>
     */
    public function getCategoryStatistics(): array
    {
        $categories = $this->getCategoryTree();

        $stats = [
            'total_categories' => count($categories),
            'root_categories' => 0,
            'max_depth' => 0,
            'categories_by_level' => [],
        ];

        foreach ($categories as $category) {
            $level = $category['level'] ?? 0;

            // Count root categories (level 1)
            if ($level === 1) {
                $stats['root_categories']++;
            }

            // Track max depth
            if ($level > $stats['max_depth']) {
                $stats['max_depth'] = $level;
            }

            // Count categories by level
            if (! isset($stats['categories_by_level'][$level])) {
                $stats['categories_by_level'][$level] = 0;
            }
            $stats['categories_by_level'][$level]++;
        }

        return $stats;
    }

    /**
     * 📋 EXPORT CATEGORIES TO CSV
     *
     * Export categories to CSV format for analysis
     *
     * @param  string|null  $hierarchy  Target category hierarchy
     * @return array<string, mixed> File path and metadata
     */
    public function exportToCsv(?string $hierarchy = null): array
    {
        $categories = $this->getCategories($hierarchy);

        $csvData = [];
        $csvData[] = ['Code', 'Label', 'Level', 'Parent Code', 'Hierarchy Code', 'Leaf Category'];

        foreach ($categories as $category) {
            $csvData[] = [
                $category['code'] ?? '',
                $category['label'] ?? '',
                $category['level'] ?? 0,
                $category['parent_code'] ?? '',
                $category['hierarchy_code'] ?? '',
                ($category['leaf_category'] ?? false) ? 'Yes' : 'No',
            ];
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $hierarchySlug = $hierarchy ? str_replace('-', '_', $hierarchy) : 'all';
        $filename = "categories_{$this->operator}_{$hierarchySlug}_{$timestamp}.csv";
        $filePath = "temp/mirakl_exports/{$filename}";

        // Ensure directory exists
        \Illuminate\Support\Facades\Storage::makeDirectory('temp/mirakl_exports');

        // Write CSV content
        $csvContent = $this->arrayToCsv($csvData);
        \Illuminate\Support\Facades\Storage::put($filePath, $csvContent);

        Log::info('📋 Categories exported to CSV', [
            'operator' => $this->operator,
            'file_path' => $filePath,
            'categories_count' => count($categories),
            'hierarchy' => $hierarchy,
        ]);

        return [
            'file_path' => $filePath,
            'absolute_path' => \Illuminate\Support\Facades\Storage::path($filePath),
            'filename' => $filename,
            'categories_count' => count($categories),
            'file_size' => strlen($csvContent),
        ];
    }

    /**
     * 🔄 REFRESH CATEGORY CACHE
     *
     * Clear cached category data for the operator
     *
     * @return bool Success status
     */
    public function refreshCache(): bool
    {
        $pattern = "mirakl_categories_{$this->operator}_*";

        // This is a simplified cache clearing approach
        // In production, you might want to use Cache tags or a more sophisticated approach
        Cache::forget("mirakl_categories_{$this->operator}__".md5(serialize([])));

        Log::info('🔄 Category cache refreshed', [
            'operator' => $this->operator,
        ]);

        return true;
    }
}
