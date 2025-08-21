<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceTaxonomy;
use App\Models\SyncAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 🏷️ MARKETPLACE TAXONOMY SERVICE
 *
 * Provides high-level interface for accessing cached marketplace taxonomy data.
 * Optimized for building dynamic forms and validating product attributes.
 *
 * Handles caching, performance optimization, and form building helpers.
 */
class MarketplaceTaxonomyService
{
    protected int $cacheTimeout = 3600; // 1 hour cache

    /**
     * 📁 Get categories for a marketplace
     */
    public function getCategories(SyncAccount $syncAccount, array $options = []): Collection
    {
        $cacheKey = "taxonomy_categories_{$syncAccount->id}";

        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($syncAccount, $options) {
            $query = MarketplaceTaxonomy::categories()
                ->forMarketplace($syncAccount)
                ->active();

            // Apply filters
            if (! empty($options['parent_id'])) {
                $query = $query->where('external_parent_id', $options['parent_id']);
            }

            if (! empty($options['level'])) {
                $query = $query->where('level', $options['level']);
            }

            if (! empty($options['leaf_only'])) {
                $query = $query->where('is_leaf', true);
            }

            return $query->orderBy('level')
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * 🏷️ Get attributes for a marketplace
     */
    public function getAttributes(SyncAccount $syncAccount, array $options = []): Collection
    {
        $cacheKey = "taxonomy_attributes_{$syncAccount->id}";

        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($syncAccount, $options) {
            $query = MarketplaceTaxonomy::attributes()
                ->forMarketplace($syncAccount)
                ->active();

            // Apply filters
            if (! empty($options['required_only'])) {
                $query = $query->where('is_required', true);
            }

            if (! empty($options['data_type'])) {
                $query = $query->where('data_type', $options['data_type']);
            }

            if (! empty($options['category_id'])) {
                // Filter by category if marketplace supports category-specific attributes
                $categoryFilter = $this->buildCategoryAttributeFilter($syncAccount, $options['category_id']);
                if ($categoryFilter) {
                    $query = $query->whereRaw($categoryFilter);
                }
            }

            return $query->orderBy('is_required', 'desc')
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * 🎯 Get values for a specific attribute
     */
    public function getAttributeValues(SyncAccount $syncAccount, string $attributeKey): Collection
    {
        $cacheKey = "taxonomy_values_{$syncAccount->id}_{$attributeKey}";

        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($syncAccount, $attributeKey) {
            return MarketplaceTaxonomy::getValuesForAttribute($syncAccount, $attributeKey);
        });
    }

    /**
     * 🔍 Find specific taxonomy item
     */
    public function findTaxonomyItem(SyncAccount $syncAccount, string $taxonomyType, string $externalId): ?MarketplaceTaxonomy
    {
        return MarketplaceTaxonomy::where([
            'sync_account_id' => $syncAccount->id,
            'taxonomy_type' => $taxonomyType,
            'external_id' => $externalId,
            'is_active' => true,
        ])->first();
    }

    /**
     * 🔍 Search taxonomy items by name or key
     */
    public function searchTaxonomyItems(SyncAccount $syncAccount, string $query, array $options = []): Collection
    {
        $builder = MarketplaceTaxonomy::where('sync_account_id', $syncAccount->id)
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('key', 'LIKE', "%{$query}%")
                    ->orWhere('description', 'LIKE', "%{$query}%");
            });

        // Apply type filter
        if (! empty($options['type'])) {
            $builder = $builder->where('taxonomy_type', $options['type']);
        }

        // Apply limit
        $limit = $options['limit'] ?? 50;
        $builder = $builder->limit($limit);

        return $builder->orderBy('name')->get();
    }

    /**
     * 📋 Build form fields for marketplace attributes
     */
    public function buildFormFields(SyncAccount $syncAccount, array $options = []): array
    {
        $attributes = $this->getAttributes($syncAccount, $options);
        $formFields = [];

        foreach ($attributes as $attribute) {
            $formField = [
                'key' => $attribute->key,
                'name' => $attribute->name,
                'description' => $attribute->description,
                'type' => $this->mapFormFieldType($attribute->data_type),
                'required' => $attribute->is_required,
                'validation_rules' => $attribute->validation_rules ?? [],
                'options' => [],
            ];

            // Add choices/options for list fields
            if ($attribute->hasChoices()) {
                $formField['options'] = $this->formatChoicesForForm($attribute->getChoices());
            } else {
                // Try to get values from separate value records
                $values = $this->getAttributeValues($syncAccount, $attribute->key);
                if ($values->isNotEmpty()) {
                    $formField['options'] = $values->map(function ($value) {
                        return [
                            'value' => $value->external_id,
                            'label' => $value->name,
                            'description' => $value->description,
                        ];
                    })->toArray();
                }
            }

            // Add metadata for complex field types
            $formField['metadata'] = [
                'external_id' => $attribute->external_id,
                'properties' => $attribute->properties ?? [],
                'popularity_score' => $attribute->getPopularityScore(),
            ];

            $formFields[] = $formField;
        }

        return $formFields;
    }

    /**
     * ✅ Validate attribute value against taxonomy rules
     */
    public function validateAttributeValue(SyncAccount $syncAccount, string $attributeKey, $value): array
    {
        $attribute = MarketplaceTaxonomy::where([
            'sync_account_id' => $syncAccount->id,
            'taxonomy_type' => 'attribute',
            'key' => $attributeKey,
            'is_active' => true,
        ])->first();

        if (! $attribute) {
            return [
                'valid' => false,
                'errors' => ["Attribute '{$attributeKey}' not found in marketplace taxonomy"],
            ];
        }

        $errors = [];

        // Check required validation
        if ($attribute->is_required && empty($value)) {
            $errors[] = 'This attribute is required';
        }

        // Check data type validation
        if (! $this->validateDataType($value, $attribute->data_type)) {
            $errors[] = "Invalid data type. Expected: {$attribute->data_type}";
        }

        // Check choices validation
        if ($attribute->hasChoices() && ! empty($value)) {
            $choices = $attribute->getChoices();
            if (! in_array($value, $choices)) {
                $errors[] = 'Value must be one of: '.implode(', ', $choices);
            }
        }

        // Check custom validation rules
        $customErrors = $this->validateCustomRules($value, $attribute->validation_rules ?? []);
        $errors = array_merge($errors, $customErrors);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'attribute' => [
                'name' => $attribute->name,
                'type' => $attribute->data_type,
                'required' => $attribute->is_required,
            ],
        ];
    }

    /**
     * 📊 Get taxonomy statistics for a marketplace
     */
    public function getTaxonomyStats(SyncAccount $syncAccount): array
    {
        $cacheKey = "taxonomy_stats_{$syncAccount->id}";

        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($syncAccount) {
            $stats = MarketplaceTaxonomy::where('sync_account_id', $syncAccount->id)
                ->selectRaw('
                    taxonomy_type,
                    COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN is_required = 1 THEN 1 ELSE 0 END) as required_count,
                    MAX(last_synced_at) as last_synced
                ')
                ->groupBy('taxonomy_type')
                ->get()
                ->keyBy('taxonomy_type');

            $lastSync = $stats->max('last_synced');

            return [
                'sync_account' => [
                    'id' => $syncAccount->id,
                    'name' => $syncAccount->name,
                    'channel' => $syncAccount->channel,
                ],
                'last_sync' => $lastSync,
                'is_stale' => $lastSync ? now()->diffInDays($lastSync) > 35 : true,
                'categories' => $this->formatStatRow($stats->get('category')),
                'attributes' => $this->formatStatRow($stats->get('attribute')),
                'values' => $this->formatStatRow($stats->get('value')),
                'total_items' => $stats->sum('total'),
                'total_active' => $stats->sum('active'),
            ];
        });
    }

    /**
     * 🧹 Clear taxonomy cache for a marketplace
     */
    public function clearTaxonomyCache(SyncAccount $syncAccount): void
    {
        $patterns = [
            "taxonomy_categories_{$syncAccount->id}",
            "taxonomy_attributes_{$syncAccount->id}",
            "taxonomy_values_{$syncAccount->id}_*",
            "taxonomy_stats_{$syncAccount->id}",
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // For wildcard patterns, we'd need to implement cache tag-based clearing
                // For now, just clear the specific known keys
                continue;
            }
            Cache::forget($pattern);
        }

        Log::info("🧹 Cleared taxonomy cache for {$syncAccount->name}");
    }

    /**
     * 📋 Get marketplace taxonomy health report
     */
    public function getHealthReport(SyncAccount $syncAccount): array
    {
        $stats = $this->getTaxonomyStats($syncAccount);
        $healthScore = 100;
        $issues = [];
        $recommendations = [];

        // Check data freshness
        if ($stats['is_stale']) {
            $healthScore -= 30;
            $issues[] = 'Taxonomy data is stale (last sync > 35 days ago)';
            $recommendations[] = 'Run taxonomy sync to refresh data';
        }

        // Check data completeness
        if ($stats['categories']['total'] === 0) {
            $healthScore -= 25;
            $issues[] = 'No categories found';
            $recommendations[] = 'Verify marketplace connection and category sync';
        }

        if ($stats['attributes']['total'] === 0) {
            $healthScore -= 25;
            $issues[] = 'No attributes found';
            $recommendations[] = 'Verify marketplace connection and attribute sync';
        }

        // Check data quality
        $inactivePercentage = $stats['total_items'] > 0
            ? (($stats['total_items'] - $stats['total_active']) / $stats['total_items']) * 100
            : 0;

        if ($inactivePercentage > 20) {
            $healthScore -= 20;
            $issues[] = sprintf('High percentage of inactive items (%.1f%%)', $inactivePercentage);
            $recommendations[] = 'Consider running cleanup to remove outdated taxonomy items';
        }

        return [
            'health_score' => max(0, $healthScore),
            'status' => $this->getHealthStatus($healthScore),
            'issues' => $issues,
            'recommendations' => $recommendations,
            'stats' => $stats,
            'checked_at' => now()->toISOString(),
        ];
    }

    // ==================== HELPER METHODS ====================

    /**
     * 🏗️ Build category-specific attribute filter
     */
    protected function buildCategoryAttributeFilter(SyncAccount $syncAccount, string $categoryId): ?string
    {
        // This would depend on how the marketplace structures category-attribute relationships
        // For now, return null - can be extended per marketplace
        return null;
    }

    /**
     * 🗂️ Map taxonomy data type to form field type
     */
    protected function mapFormFieldType(string $taxonomyType): string
    {
        return match ($taxonomyType) {
            'text' => 'text',
            'integer', 'decimal' => 'number',
            'boolean' => 'checkbox',
            'list' => 'select',
            'dimension' => 'dimension',
            'color' => 'color',
            default => 'text',
        };
    }

    /**
     * 📋 Format choices for form options
     */
    protected function formatChoicesForForm(array $choices): array
    {
        $options = [];

        foreach ($choices as $choice) {
            if (is_string($choice)) {
                $options[] = [
                    'value' => $choice,
                    'label' => $choice,
                ];
            } elseif (is_array($choice)) {
                $options[] = [
                    'value' => $choice['value'] ?? $choice['id'],
                    'label' => $choice['label'] ?? $choice['name'],
                    'description' => $choice['description'] ?? null,
                ];
            }
        }

        return $options;
    }

    /**
     * 🔍 Validate data type
     */
    protected function validateDataType($value, string $expectedType): bool
    {
        if (empty($value)) {
            return true; // Empty values are handled by required validation
        }

        return match ($expectedType) {
            'text' => is_string($value),
            'integer' => is_numeric($value) && is_int($value + 0),
            'decimal' => is_numeric($value),
            'boolean' => is_bool($value) || in_array($value, ['true', 'false', '1', '0', 1, 0]),
            'list' => is_array($value),
            'dimension' => is_array($value) && isset($value['width'], $value['height']),
            default => true,
        };
    }

    /**
     * ✅ Validate against custom rules
     */
    protected function validateCustomRules($value, array $rules): array
    {
        $errors = [];

        foreach ($rules as $rule => $constraint) {
            match ($rule) {
                'min_length' => strlen($value) < $constraint && $errors[] = "Minimum length: {$constraint}",
                'max_length' => strlen($value) > $constraint && $errors[] = "Maximum length: {$constraint}",
                'min_value' => is_numeric($value) && $value < $constraint && $errors[] = "Minimum value: {$constraint}",
                'max_value' => is_numeric($value) && $value > $constraint && $errors[] = "Maximum value: {$constraint}",
                'pattern' => ! preg_match($constraint, $value) && $errors[] = 'Invalid format',
                default => null,
            };
        }

        return $errors;
    }

    /**
     * 📊 Format statistics row
     */
    protected function formatStatRow($stat): array
    {
        return [
            'total' => $stat->total ?? 0,
            'active' => $stat->active ?? 0,
            'required' => $stat->required_count ?? 0,
            'last_synced' => $stat->last_synced ?? null,
        ];
    }

    /**
     * 🎯 Get health status from score
     */
    protected function getHealthStatus(int $score): string
    {
        return match (true) {
            $score >= 90 => 'excellent',
            $score >= 70 => 'good',
            $score >= 50 => 'fair',
            $score >= 30 => 'poor',
            default => 'critical',
        };
    }
}
