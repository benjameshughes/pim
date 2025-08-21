<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 🎯 CHANNEL VALUE LIST MODEL
 *
 * Stores valid values for LIST-type marketplace fields:
 * - Mirakl operators: values from /api/values_lists endpoint
 * - Shopify: product types, tags, collections, etc.
 * - eBay: item specifics valid values
 * - Amazon: browse node attribute values
 *
 * Supports monthly caching and sync with marketplace APIs
 */
class ChannelValueList extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_type',
        'channel_subtype',
        'list_code',
        'list_name',
        'list_description',
        'allowed_values',
        'value_metadata',
        'values_count',
        'discovered_at',
        'last_synced_at',
        'api_version',
        'is_active',
        'sync_status',
        'sync_error',
        'sync_metadata',
    ];

    protected $casts = [
        'allowed_values' => 'array',
        'value_metadata' => 'array',
        'sync_metadata' => 'array',
        'is_active' => 'boolean',
        'discovered_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 🔗 FIELD DEFINITIONS RELATIONSHIP
     *
     * Get fields that use this value list
     */
    public function fieldDefinitions(): HasMany
    {
        return $this->hasMany(ChannelFieldDefinition::class, 'value_list_code', 'list_code')
            ->where('channel_type', $this->channel_type)
            ->where('channel_subtype', $this->channel_subtype);
    }

    /**
     * 🎯 SCOPE: Active lists only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 🎯 SCOPE: Successfully synced
     */
    public function scopeSynced($query)
    {
        return $query->where('sync_status', 'synced');
    }

    /**
     * 🎯 SCOPE: Needs sync
     */
    public function scopeNeedsSync($query)
    {
        return $query->where(function ($q) {
            $q->where('sync_status', 'pending')
                ->orWhere('sync_status', 'failed')
                ->orWhere('last_synced_at', '<', now()->subMonth());
        });
    }

    /**
     * 🎯 SCOPE: For channel
     */
    public function scopeForChannel($query, string $channelType, ?string $channelSubtype = null)
    {
        $query->where('channel_type', $channelType);

        if ($channelSubtype) {
            $query->where('channel_subtype', $channelSubtype);
        }

        return $query;
    }

    /**
     * 🎯 SCOPE: For list code
     */
    public function scopeForListCode($query, string $listCode)
    {
        return $query->where('list_code', $listCode);
    }

    /**
     * 📋 GET VALUE LIST
     *
     * Get value list for specific field
     */
    public static function getValueList(
        string $channelType,
        string $listCode,
        ?string $channelSubtype = null
    ): ?self {
        return static::active()
            ->forChannel($channelType, $channelSubtype)
            ->forListCode($listCode)
            ->first();
    }

    /**
     * 📋 GET ALL VALUES
     *
     * Get all allowed values as simple array
     */
    public function getAllValues(): array
    {
        return $this->allowed_values ?? [];
    }

    /**
     * 📋 GET VALUES WITH METADATA
     *
     * Get values with additional metadata
     */
    public function getValuesWithMetadata(): array
    {
        $values = $this->getAllValues();
        $metadata = $this->value_metadata ?? [];

        $result = [];
        foreach ($values as $value) {
            $result[] = [
                'value' => $value,
                'metadata' => $metadata[$value] ?? [],
            ];
        }

        return $result;
    }

    /**
     * ✅ IS VALUE VALID
     *
     * Check if a value is in this list
     */
    public function isValueValid(string $value): bool
    {
        return in_array($value, $this->getAllValues());
    }

    /**
     * 🔍 SEARCH VALUES
     *
     * Search for values containing query
     */
    public function searchValues(string $query): array
    {
        $values = $this->getAllValues();
        $query = strtolower($query);

        return array_filter($values, function ($value) use ($query) {
            return stripos($value, $query) !== false;
        });
    }

    /**
     * 📊 UPDATE VALUES
     *
     * Update the values list from API response
     */
    public function updateValues(array $newValues, ?array $metadata = null): void
    {
        $this->allowed_values = $newValues;
        $this->values_count = count($newValues);
        $this->value_metadata = $metadata;
        $this->last_synced_at = now();
        $this->sync_status = 'synced';
        $this->sync_error = null;
        $this->save();
    }

    /**
     * ❌ MARK SYNC FAILED
     *
     * Mark sync as failed with error
     */
    public function markSyncFailed(string $error, ?array $metadata = null): void
    {
        $this->sync_status = 'failed';
        $this->sync_error = $error;
        $this->sync_metadata = array_merge($this->sync_metadata ?? [], [
            'last_error' => $error,
            'failed_at' => now()->toISOString(),
            'metadata' => $metadata,
        ]);
        $this->save();
    }

    /**
     * 🔄 SYNC FROM API
     *
     * Sync this value list from marketplace API
     */
    public function syncFromApi(): array
    {
        $startTime = microtime(true);

        try {
            // TODO: Implement actual API sync based on channel type
            $result = $this->performApiSync();

            if ($result['success']) {
                $this->updateValues($result['values'], $result['metadata'] ?? null);

                return [
                    'success' => true,
                    'values_count' => count($result['values']),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'synced_at' => now()->toISOString(),
                ];
            } else {
                $this->markSyncFailed($result['error'], $result);

                return [
                    'success' => false,
                    'error' => $result['error'],
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ];
            }
        } catch (\Exception $e) {
            $this->markSyncFailed($e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }
    }

    /**
     * 🔧 PERFORM API SYNC
     *
     * Actual API sync implementation (to be implemented per channel type)
     */
    protected function performApiSync(): array
    {
        return match ($this->channel_type) {
            'mirakl' => $this->syncFromMiraklApi(),
            'shopify' => $this->syncFromShopifyApi(),
            'ebay' => $this->syncFromEbayApi(),
            'amazon' => $this->syncFromAmazonApi(),
            default => ['success' => false, 'error' => "Unsupported channel type: {$this->channel_type}"],
        };
    }

    /**
     * 🔧 SYNC FROM MIRAKL API
     */
    protected function syncFromMiraklApi(): array
    {
        // TODO: Use MarketplaceClient to fetch /api/values_lists/{list_code}
        return ['success' => false, 'error' => 'Mirakl sync not implemented yet'];
    }

    /**
     * 🔧 SYNC FROM SHOPIFY API
     */
    protected function syncFromShopifyApi(): array
    {
        // TODO: Use ShopifyConnectService to fetch relevant values
        return ['success' => false, 'error' => 'Shopify sync not implemented yet'];
    }

    /**
     * 🔧 SYNC FROM EBAY API
     */
    protected function syncFromEbayApi(): array
    {
        // TODO: Use EbayConnectService to fetch item specifics
        return ['success' => false, 'error' => 'eBay sync not implemented yet'];
    }

    /**
     * 🔧 SYNC FROM AMAZON API
     */
    protected function syncFromAmazonApi(): array
    {
        // TODO: Implement Amazon API sync
        return ['success' => false, 'error' => 'Amazon sync not implemented yet'];
    }

    /**
     * 📊 GET STATISTICS
     *
     * Get statistics for value lists
     */
    public static function getStatistics(): array
    {
        $all = static::all();

        return [
            'total_lists' => $all->count(),
            'active_lists' => $all->where('is_active', true)->count(),
            'synced_lists' => $all->where('sync_status', 'synced')->count(),
            'failed_lists' => $all->where('sync_status', 'failed')->count(),
            'pending_lists' => $all->where('sync_status', 'pending')->count(),
            'by_channel' => $all->groupBy('channel_type')->map->count(),
            'total_values' => $all->sum('values_count'),
            'last_sync' => $all->max('last_synced_at'),
            'needs_sync' => $all->filter(function ($list) {
                return $list->sync_status === 'pending' ||
                       $list->sync_status === 'failed' ||
                       $list->last_synced_at < now()->subMonth();
            })->count(),
        ];
    }

    /**
     * 🔄 SYNC ALL OUTDATED
     *
     * Sync all value lists that need updating
     */
    public static function syncAllOutdated(): array
    {
        $lists = static::needsSync()->get();
        $results = [];

        foreach ($lists as $list) {
            $results[] = [
                'list_code' => $list->list_code,
                'channel' => "{$list->channel_type}:{$list->channel_subtype}",
                'result' => $list->syncFromApi(),
            ];
        }

        return [
            'processed' => count($results),
            'results' => $results,
            'summary' => [
                'successful' => collect($results)->where('result.success', true)->count(),
                'failed' => collect($results)->where('result.success', false)->count(),
            ],
        ];
    }

    /**
     * 🏷️ GET DISPLAY NAME
     *
     * Human-readable list identifier
     */
    public function getDisplayNameAttribute(): string
    {
        $channel = $this->channel_subtype ? "{$this->channel_type}:{$this->channel_subtype}" : $this->channel_type;
        $name = $this->list_name ?: $this->list_code;

        return "{$channel} - {$name}";
    }

    /**
     * 🎨 GET SYNC STATUS COLOR
     *
     * UI color for sync status badges
     */
    public function getSyncStatusColorAttribute(): string
    {
        return match ($this->sync_status) {
            'synced' => 'green',
            'pending' => 'yellow',
            'failed' => 'red',
            default => 'gray',
        };
    }

    /**
     * 📝 GET SYNC STATUS DESCRIPTION
     *
     * Human-readable sync status
     */
    public function getSyncStatusDescriptionAttribute(): string
    {
        return match ($this->sync_status) {
            'synced' => "Last synced: {$this->last_synced_at?->diffForHumans()}",
            'pending' => 'Pending sync',
            'failed' => "Failed: {$this->sync_error}",
            default => 'Unknown status',
        };
    }

    /**
     * ✅ IS OUTDATED
     *
     * Check if this list needs syncing
     */
    public function isOutdated(): bool
    {
        return $this->sync_status === 'pending' ||
               $this->sync_status === 'failed' ||
               $this->last_synced_at < now()->subMonth();
    }

    /**
     * 📈 GET VALUES GROWTH
     *
     * Compare current values count with previous sync
     */
    public function getValuesGrowth(): array
    {
        $previousCount = $this->sync_metadata['previous_count'] ?? $this->values_count;
        $currentCount = $this->values_count;
        $growth = $currentCount - $previousCount;

        return [
            'previous_count' => $previousCount,
            'current_count' => $currentCount,
            'growth' => $growth,
            'growth_percentage' => $previousCount > 0 ? round(($growth / $previousCount) * 100, 1) : 0,
        ];
    }
}
