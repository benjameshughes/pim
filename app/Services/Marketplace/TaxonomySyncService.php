<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceTaxonomy;
use App\Models\SyncAccount;
use App\Services\Shopify\API\AttributesApi;
use App\Services\Shopify\API\CategoryApi;
use App\Services\Shopify\API\ValueListsApi;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🔄 TAXONOMY SYNC SERVICE
 *
 * Core business logic for syncing marketplace taxonomy data.
 * Handles categories, attributes, and values for all supported marketplaces.
 *
 * Provides clean interface for jobs, commands, and manual operations.
 */
class TaxonomySyncService
{
    protected array $syncStats = [
        'categories_synced' => 0,
        'attributes_synced' => 0,
        'values_synced' => 0,
        'items_updated' => 0,
        'items_created' => 0,
        'items_deactivated' => 0,
        'errors' => [],
    ];

    /**
     * 🔄 Sync taxonomy for all active sync accounts
     */
    public function syncAllMarketplaces(): array
    {
        $this->resetStats();
        $startTime = now();

        Log::info('🔄 Starting taxonomy sync for all marketplaces');

        $syncAccounts = SyncAccount::where('is_active', true)->get();
        $processedAccounts = 0;

        foreach ($syncAccounts as $syncAccount) {
            try {
                $this->syncMarketplace($syncAccount);
                $processedAccounts++;
            } catch (Exception $e) {
                $this->recordError($syncAccount, $e);
                Log::error("❌ Failed to sync {$syncAccount->name}", [
                    'sync_account_id' => $syncAccount->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $duration = $startTime->diffInMinutes(now());

        Log::info('✅ Completed taxonomy sync for all marketplaces', [
            'duration_minutes' => $duration,
            'accounts_processed' => $processedAccounts,
            'total_accounts' => $syncAccounts->count(),
            'stats' => $this->syncStats,
        ]);

        return [
            'success' => true,
            'duration_minutes' => $duration,
            'accounts_processed' => $processedAccounts,
            'total_accounts' => $syncAccounts->count(),
            'stats' => $this->syncStats,
        ];
    }

    /**
     * 🔄 Sync taxonomy for a single marketplace
     */
    public function syncMarketplace(SyncAccount $syncAccount): array
    {
        $startTime = now();
        $initialStats = $this->syncStats;

        Log::info("🔄 Starting taxonomy sync for {$syncAccount->name}", [
            'sync_account_id' => $syncAccount->id,
            'channel' => $syncAccount->channel,
        ]);

        try {
            // Route to appropriate marketplace sync method
            $result = match ($syncAccount->channel) {
                'shopify' => $this->syncShopifyTaxonomy($syncAccount),
                'ebay' => $this->syncEbayTaxonomy($syncAccount),
                'mirakl' => $this->syncMiraklTaxonomy($syncAccount),
                default => throw new Exception("Unsupported marketplace channel: {$syncAccount->channel}")
            };

            // Cleanup old/stale data
            $this->cleanupStaleData($syncAccount);

            $duration = $startTime->diffInMinutes(now());
            $accountStats = $this->getStatsDelta($initialStats);

            Log::info("✅ Completed taxonomy sync for {$syncAccount->name}", [
                'sync_account_id' => $syncAccount->id,
                'duration_minutes' => $duration,
                'stats' => $accountStats,
            ]);

            return [
                'success' => true,
                'duration_minutes' => $duration,
                'stats' => $accountStats,
            ];
        } catch (Exception $e) {
            $this->recordError($syncAccount, $e);
            throw $e;
        }
    }

    /**
     * 🛍️ Sync Shopify taxonomy data
     */
    public function syncShopifyTaxonomy(SyncAccount $syncAccount): array
    {
        $categoryApi = new CategoryApi($syncAccount);
        $attributesApi = new AttributesApi($syncAccount);
        $valueListsApi = new ValueListsApi($syncAccount);

        $results = [];

        // Sync in order: Categories → Attributes → Values
        $results['categories'] = $this->syncShopifyCategories($syncAccount, $categoryApi);
        $results['attributes'] = $this->syncShopifyAttributes($syncAccount, $attributesApi);
        $results['values'] = $this->syncShopifyValues($syncAccount, $valueListsApi);

        return $results;
    }

    /**
     * 📁 Sync Shopify categories
     */
    public function syncShopifyCategories(SyncAccount $syncAccount, CategoryApi $categoryApi): array
    {
        $categories = $categoryApi->getCategories([
            'limit' => 250,
            'include_attributes' => true,
        ]);

        $synced = 0;
        $errors = [];

        foreach ($categories as $category) {
            try {
                $this->upsertTaxonomyItem($syncAccount, [
                    'taxonomy_type' => 'category',
                    'external_id' => $category['id'],
                    'external_parent_id' => $category['parentId'] ?? null,
                    'name' => $category['name'],
                    'key' => $category['handle'] ?? null,
                    'description' => $category['description'] ?? null,
                    'level' => $category['level'] ?? 1,
                    'is_leaf' => $category['isLeaf'] ?? false,
                    'is_required' => false,
                    'data_type' => null,
                    'validation_rules' => null,
                    'metadata' => $category,
                    'properties' => [
                        'full_name' => $category['fullName'] ?? $category['name'],
                        'is_root' => $category['isRoot'] ?? false,
                        'is_archived' => $category['isArchived'] ?? false,
                        'ancestor_ids' => $category['ancestorIds'] ?? [],
                        'children_ids' => $category['childrenIds'] ?? [],
                    ],
                ]);

                $synced++;
                $this->syncStats['categories_synced']++;
            } catch (Exception $e) {
                $errors[] = [
                    'category_id' => $category['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return compact('synced', 'errors');
    }

    /**
     * 🏷️ Sync Shopify attributes
     */
    public function syncShopifyAttributes(SyncAccount $syncAccount, AttributesApi $attributesApi): array
    {
        $definitions = $attributesApi->getMetafieldDefinitions([
            'owner_type' => 'PRODUCT',
            'limit' => 250,
        ]);

        $synced = 0;
        $errors = [];

        foreach ($definitions as $definition) {
            try {
                $validationRules = $this->parseShopifyValidationRules($definition);

                $this->upsertTaxonomyItem($syncAccount, [
                    'taxonomy_type' => 'attribute',
                    'external_id' => $definition['id'],
                    'external_parent_id' => null,
                    'name' => $definition['name'],
                    'key' => $definition['key'],
                    'description' => $definition['description'] ?? null,
                    'level' => 1,
                    'is_leaf' => true,
                    'is_required' => $definition['required'] ?? false,
                    'data_type' => $this->mapShopifyDataType($definition['type']['name'] ?? 'text'),
                    'validation_rules' => $validationRules,
                    'metadata' => $definition,
                    'properties' => [
                        'namespace' => $definition['namespace'],
                        'owner_type' => $definition['ownerType'],
                        'type_category' => $definition['type']['category'] ?? null,
                    ],
                ]);

                $synced++;
                $this->syncStats['attributes_synced']++;
            } catch (Exception $e) {
                $errors[] = [
                    'definition_id' => $definition['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return compact('synced', 'errors');
    }

    /**
     * 🎯 Sync Shopify values
     */
    public function syncShopifyValues(SyncAccount $syncAccount, ValueListsApi $valueListsApi): array
    {
        $taxonomyValues = $valueListsApi->getTaxonomyValues([
            'limit' => 250,
        ]);

        $synced = 0;
        $errors = [];

        foreach ($taxonomyValues as $value) {
            try {
                $this->upsertTaxonomyItem($syncAccount, [
                    'taxonomy_type' => 'value',
                    'external_id' => $value['id'],
                    'external_parent_id' => $value['attributeId'] ?? null,
                    'name' => $value['name'],
                    'key' => $value['handle'] ?? null,
                    'description' => $value['description'] ?? null,
                    'level' => 2,
                    'is_leaf' => true,
                    'is_required' => false,
                    'data_type' => 'text',
                    'validation_rules' => null,
                    'metadata' => $value,
                    'properties' => [
                        'attribute_id' => $value['attributeId'] ?? null,
                        'popularity_score' => $value['popularityScore'] ?? null,
                    ],
                ]);

                $synced++;
                $this->syncStats['values_synced']++;
            } catch (Exception $e) {
                $errors[] = [
                    'value_id' => $value['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return compact('synced', 'errors');
    }

    /**
     * 🏪 Sync eBay taxonomy (placeholder)
     */
    public function syncEbayTaxonomy(SyncAccount $syncAccount): array
    {
        // TODO: Implement eBay taxonomy sync
        Log::info("📝 eBay taxonomy sync not implemented yet for {$syncAccount->name}");

        return [
            'categories' => ['synced' => 0, 'errors' => []],
            'attributes' => ['synced' => 0, 'errors' => []],
            'values' => ['synced' => 0, 'errors' => []],
        ];
    }

    /**
     * 🌐 Sync Mirakl taxonomy (placeholder)
     */
    public function syncMiraklTaxonomy(SyncAccount $syncAccount): array
    {
        // TODO: Implement Mirakl taxonomy sync using existing H11/PM11/VL11 APIs
        Log::info("📝 Mirakl taxonomy sync not implemented yet for {$syncAccount->name}");

        return [
            'categories' => ['synced' => 0, 'errors' => []],
            'attributes' => ['synced' => 0, 'errors' => []],
            'values' => ['synced' => 0, 'errors' => []],
        ];
    }

    /**
     * 💾 Upsert taxonomy item in database
     */
    protected function upsertTaxonomyItem(SyncAccount $syncAccount, array $taxonomyData): MarketplaceTaxonomy
    {
        $taxonomyData['sync_account_id'] = $syncAccount->id;
        $taxonomyData['last_synced_at'] = now();
        $taxonomyData['is_active'] = true;
        $taxonomyData['sync_version'] = '1.0';

        $existing = MarketplaceTaxonomy::where([
            'sync_account_id' => $syncAccount->id,
            'taxonomy_type' => $taxonomyData['taxonomy_type'],
            'external_id' => $taxonomyData['external_id'],
        ])->first();

        if ($existing) {
            $existing->update($taxonomyData);
            $this->syncStats['items_updated']++;

            return $existing;
        } else {
            $taxonomy = MarketplaceTaxonomy::create($taxonomyData);
            $this->syncStats['items_created']++;

            return $taxonomy;
        }
    }

    /**
     * 🧹 Cleanup stale taxonomy data
     */
    protected function cleanupStaleData(SyncAccount $syncAccount): int
    {
        $cutoffDate = now()->subDays(35); // Keep data that was synced within last 35 days

        $deactivated = MarketplaceTaxonomy::where('sync_account_id', $syncAccount->id)
            ->where('last_synced_at', '<', $cutoffDate)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        if ($deactivated > 0) {
            Log::info("🧹 Deactivated {$deactivated} stale taxonomy items for {$syncAccount->name}");
            $this->syncStats['items_deactivated'] += $deactivated;
        }

        return $deactivated;
    }

    /**
     * 🔧 Parse Shopify validation rules
     */
    protected function parseShopifyValidationRules(array $definition): ?array
    {
        $rules = [];
        $type = $definition['type'] ?? [];

        if (! empty($type['choices'])) {
            $rules['choices'] = $type['choices'];
        }

        if (! empty($definition['validations'])) {
            foreach ($definition['validations'] as $validation) {
                $rules[$validation['name']] = $validation['value'];
            }
        }

        return empty($rules) ? null : $rules;
    }

    /**
     * 🗂️ Map Shopify data types to internal types
     */
    protected function mapShopifyDataType(string $shopifyType): string
    {
        return match ($shopifyType) {
            'single_line_text_field', 'multi_line_text_field' => 'text',
            'number_integer' => 'integer',
            'number_decimal' => 'decimal',
            'boolean' => 'boolean',
            'list.single_line_text_field' => 'list',
            'dimension' => 'dimension',
            'color' => 'color',
            'weight' => 'weight',
            'volume' => 'volume',
            default => 'text',
        };
    }

    /**
     * 📊 Get sync statistics
     */
    public function getSyncStats(): array
    {
        return $this->syncStats;
    }

    /**
     * 🔄 Reset statistics
     */
    protected function resetStats(): void
    {
        $this->syncStats = [
            'categories_synced' => 0,
            'attributes_synced' => 0,
            'values_synced' => 0,
            'items_updated' => 0,
            'items_created' => 0,
            'items_deactivated' => 0,
            'errors' => [],
        ];
    }

    /**
     * ❌ Record error for reporting
     */
    protected function recordError(SyncAccount $syncAccount, Exception $e): void
    {
        $this->syncStats['errors'][] = [
            'sync_account_id' => $syncAccount->id,
            'sync_account_name' => $syncAccount->name,
            'channel' => $syncAccount->channel,
            'error' => $e->getMessage(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * 📈 Get stats delta from previous state
     */
    protected function getStatsDelta(array $initialStats): array
    {
        $delta = [];

        foreach ($this->syncStats as $key => $value) {
            if (is_numeric($value)) {
                $delta[$key] = $value - ($initialStats[$key] ?? 0);
            } else {
                $delta[$key] = $value;
            }
        }

        return $delta;
    }

    /**
     * 🔍 Get taxonomy cache status for a marketplace
     */
    public function getCacheStatus(SyncAccount $syncAccount): array
    {
        $stats = DB::table('marketplace_taxonomies')
            ->where('sync_account_id', $syncAccount->id)
            ->selectRaw('
                taxonomy_type,
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                MAX(last_synced_at) as last_synced_at
            ')
            ->groupBy('taxonomy_type')
            ->get()
            ->keyBy('taxonomy_type');

        return [
            'sync_account_id' => $syncAccount->id,
            'sync_account_name' => $syncAccount->name,
            'channel' => $syncAccount->channel,
            'categories' => $this->formatCacheTypeStats($stats->get('category')),
            'attributes' => $this->formatCacheTypeStats($stats->get('attribute')),
            'values' => $this->formatCacheTypeStats($stats->get('value')),
            'last_sync' => $stats->max('last_synced_at'),
        ];
    }

    /**
     * 📊 Format cache type statistics
     */
    protected function formatCacheTypeStats($stats): array
    {
        if (! $stats) {
            return ['total' => 0, 'active' => 0, 'last_synced_at' => null];
        }

        return [
            'total' => $stats->total,
            'active' => $stats->active,
            'last_synced_at' => $stats->last_synced_at,
        ];
    }
}
