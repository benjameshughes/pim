<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 🏢 SYNC ACCOUNT MODEL
 *
 * Represents different marketplace accounts/stores:
 * - main_shopify, backup_shopify
 * - ebay_uk, ebay_us, ebay_de
 * - amazon_uk, mirakl_main
 *
 * Enables the fluent API: Sync::shopify()->account('main')
 */
class SyncAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'channel',
        'display_name',
        'is_active',
        'credentials',
        'settings',
        'marketplace_type',
        'marketplace_subtype',
        'marketplace_template',
        'last_connection_test',
        'connection_test_result',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'marketplace_template' => 'array',
        'last_connection_test' => 'datetime',
        'connection_test_result' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ---- Lightweight helpers backed by JSON `settings` attribute ----

    /**
     * Get settings as array
     */
    protected function getSettings(): array
    {
        return $this->settings ?? [];
    }

    /**
     * Merge and persist settings
     */
    protected function mergeSettings(array $patch): bool
    {
        $current = $this->getSettings();
        $updated = array_replace_recursive($current, $patch);

        return $this->update(['settings' => $updated]);
    }

    /**
     * Build a credentials value object for adapters
     */
    public function toCredentialsVO(): \App\ValueObjects\MarketplaceCredentials
    {
        return new \App\ValueObjects\MarketplaceCredentials(
            type: $this->marketplace_type ?: $this->channel,
            credentials: $this->credentials ?? [],
            settings: $this->settings ?? [],
            operator: $this->marketplace_subtype
        );
    }

    /**
     * Current health status data stored in settings
     */
    public function getHealth(): array
    {
        $settings = $this->getSettings();

        return $settings['health']['current'] ?? [
            'status' => 'unknown',
            'tested_at' => null,
            'message' => null,
            'response_time_ms' => null,
        ];
    }

    /**
     * Health history (bounded list)
     *
     * @return array<int, array<string,mixed>>
     */
    public function getHealthHistory(int $limit = 20): array
    {
        $settings = $this->getSettings();
        $history = $settings['health']['history'] ?? [];

        return array_slice($history, -$limit);
    }

    /**
     * Record a connection test result into settings (and trim history)
     */
    public function recordHealthCheck(\App\ValueObjects\ConnectionTestResult $result, int $maxHistory = 20): bool
    {
        $entry = [
            'status' => $result->success ? 'healthy' : 'failing',
            'success' => $result->success,
            'message' => $result->message,
            'response_time_ms' => $result->responseTime,
            'endpoint' => $result->endpoint,
            'status_code' => $result->statusCode,
            'tested_at' => now()->toISOString(),
        ];

        $settings = $this->getSettings();
        $history = $settings['health']['history'] ?? [];
        $history[] = $entry;
        if (count($history) > $maxHistory) {
            $history = array_slice($history, -$maxHistory);
        }

        return $this->mergeSettings([
            'health' => [
                'current' => $entry,
                'history' => $history,
            ],
        ]);
    }

    /**
     * Return a compact badge descriptor for UI
     */
    public function getHealthBadge(): array
    {
        $current = $this->getHealth();
        $status = $current['status'] ?? 'unknown';

        return [
            'status' => $status,
            'color' => match ($status) {
                'healthy' => 'green',
                'failing' => 'red',
                default => 'gray',
            },
            'icon' => match ($status) {
                'healthy' => 'check-circle',
                'failing' => 'x-circle',
                default => 'question-mark-circle',
            },
            'tested_at' => $current['tested_at'] ?? null,
            'message' => $current['message'] ?? null,
        ];
    }

    /**
     * Determine if the last health check is older than provided hours
     */
    public function isHealthCheckStale(int $maxAgeHours = 24): bool
    {
        $testedAt = $this->getHealth()['tested_at'] ?? null;
        if (! $testedAt) {
            return true;
        }

        try {
            return now()->diffInHours(\Carbon\Carbon::parse($testedAt)) >= $maxAgeHours;
        } catch (\Exception) {
            return true;
        }
    }

    /**
     * Capabilities helpers backed by settings
     */
    public function capabilities(): array
    {
        $settings = $this->getSettings();

        return $settings['capabilities'] ?? [];
    }

    public function hasCapability(string $key): bool
    {
        return (bool) ($this->capabilities()[$key] ?? false);
    }

    /**
     * Rate limit snapshot helpers
     */
    public function rateLimit(): ?array
    {
        $settings = $this->getSettings();

        return $settings['rate_limit'] ?? null;
    }

    public function updateRateLimit(array $meta): bool
    {
        return $this->mergeSettings([
            'rate_limit' => array_merge($this->rateLimit() ?? [], $meta),
        ]);
    }

    /**
     * 🏷️ ACCOUNT NAME ACCESSOR
     *
     * Provides account_name as an alias for the name field
     */
    public function getAccountNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * 📊 SYNC STATUSES RELATIONSHIP
     *
     * All sync status records for this account
     */
    public function syncStatuses(): HasMany
    {
        return $this->hasMany(SyncStatus::class);
    }

    /**
     * 📝 SYNC LOGS RELATIONSHIP
     *
     * All sync operation logs for this account
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    /**
     * 🎯 SCOPE: Active accounts only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 🎯 SCOPE: Filter by channel
     */
    public function scopeChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * 🔍 FIND BY CHANNEL AND NAME
     *
     * Used by fluent API: Sync::shopify()->account('main')
     */
    public static function findByChannelAndName(string $channel, string $name): ?self
    {
        return static::where('channel', $channel)
            ->where('name', $name)
            ->where('is_active', true)
            ->first();
    }

    /**
     * 🔍 GET DEFAULT FOR CHANNEL
     *
     * Get the default account for a channel (first active one)
     */
    public static function getDefaultForChannel(string $channel): ?self
    {
        return static::where('channel', $channel)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * 🛍️ GET CHANNEL DISPLAY NAME
     *
     * Human-readable channel name
     */
    public function getChannelDisplayNameAttribute(): string
    {
        return match ($this->channel) {
            'shopify' => 'Shopify',
            'ebay' => 'eBay',
            'amazon' => 'Amazon',
            'mirakl' => 'Mirakl',
            default => ucfirst($this->channel)
        };
    }

    /**
     * 🎨 CHANNEL COLOR
     *
     * For UI badges and displays
     */
    public function getChannelColorAttribute(): string
    {
        return match ($this->channel) {
            'shopify' => '#95BF46',
            'ebay' => '#E53E3E',
            'amazon' => '#FF9500',
            'mirakl' => '#6B73FF',
            default => '#6B7280'
        };
    }

    /**
     * 🏷️ FULL DISPLAY NAME
     *
     * Combined channel and account name
     */
    public function getFullDisplayNameAttribute(): string
    {
        return "{$this->channel_display_name} - {$this->display_name}";
    }

    /**
     * ✅ IS CONFIGURED
     *
     * Check if account has required credentials
     */
    public function isConfigured(): bool
    {
        return ! empty($this->credentials) && $this->is_active;
    }

    /**
     * 📊 GET SYNC STATS
     *
     * Statistics for this account
     */
    public function getSyncStats(): array
    {
        return [
            'total_synced' => $this->syncStatuses()->where('sync_status', 'synced')->count(),
            'pending' => $this->syncStatuses()->where('sync_status', 'pending')->count(),
            'failed' => $this->syncStatuses()->where('sync_status', 'failed')->count(),
            'last_sync' => $this->syncLogs()->where('status', 'success')->latest()->first()?->created_at,
        ];
    }

    /**
     * 🔄 RECENT ACTIVITY
     *
     * Recent sync operations for this account
     */
    public function getRecentActivity($limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return $this->syncLogs()
            ->with('product:id,name')
            ->latest()
            ->limit($limit)
            ->get();
    }

    // ===== MARKETPLACE IDENTIFIER METHODS ===== //

    /**
     * 🏷️ GET MARKETPLACE IDENTIFIERS
     *
     * Get the marketplace identifiers configuration for this account
     */
    public function getMarketplaceIdentifiers(): array
    {
        $settings = $this->settings ?? [];

        return $settings['marketplace_identifiers'] ?? [];
    }

    /**
     * 🏪 GET MARKETPLACE DETAILS
     *
     * Get marketplace-specific details (shop name, domain, etc.)
     */
    public function getMarketplaceDetails(): array
    {
        $identifiers = $this->getMarketplaceIdentifiers();

        return $identifiers['shop_details'] ?? $identifiers['account_details'] ?? [];
    }

    /**
     * 📋 GET AVAILABLE IDENTIFIER TYPES
     *
     * Get the types of identifiers available for this marketplace
     */
    public function getAvailableIdentifierTypes(): array
    {
        $identifiers = $this->getMarketplaceIdentifiers();

        return $identifiers['identifier_types'] ?? [];
    }

    /**
     * ✅ IS IDENTIFIER SETUP COMPLETE
     *
     * Check if marketplace identifiers have been configured
     */
    public function isIdentifierSetupComplete(): bool
    {
        $settings = $this->settings ?? [];

        return ($settings['identifier_setup_completed'] ?? false) === true;
    }

    /**
     * 💾 UPDATE MARKETPLACE IDENTIFIERS
     *
     * Update the marketplace identifiers configuration
     */
    public function updateMarketplaceIdentifiers(array $identifierData): bool
    {
        $currentSettings = $this->settings ?? [];

        $updatedSettings = array_merge($currentSettings, [
            'marketplace_identifiers' => $identifierData,
            'identifier_setup_completed' => true,
            'identifier_setup_date' => now()->toISOString(),
        ]);

        return $this->update(['settings' => $updatedSettings]);
    }

    /**
     * 🔍 GET MARKETPLACE DISPLAY INFO
     *
     * Get display-friendly marketplace information
     */
    public function getMarketplaceDisplayInfo(): array
    {
        $details = $this->getMarketplaceDetails();

        return [
            'account_name' => $this->display_name,
            'channel' => $this->channel,
            'marketplace_name' => $details['shop_name'] ?? $details['name'] ?? 'Unknown',
            'marketplace_url' => $details['shop_domain'] ?? $details['domain'] ?? null,
            'setup_complete' => $this->isIdentifierSetupComplete(),
            'setup_date' => ($this->settings['identifier_setup_date'] ?? null),
        ];
    }

    /**
     * 🎯 SCOPE: Accounts with identifiers setup
     */
    public function scopeWithIdentifiers($query)
    {
        return $query->whereJsonContains('settings->identifier_setup_completed', true);
    }

    /**
     * 🎯 SCOPE: Accounts needing identifier setup
     */
    public function scopeNeedingIdentifierSetup($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('settings->identifier_setup_completed')
                ->orWhereJsonContains('settings->identifier_setup_completed', false);
        });
    }

    // ===== DYNAMIC MARKETPLACE INTEGRATION METHODS ===== //

    /**
     * 🎯 SCOPE: Filter by marketplace type
     */
    public function scopeByMarketplaceType($query, string $type)
    {
        return $query->where('marketplace_type', $type);
    }

    /**
     * 🎯 SCOPE: Accounts with operator types (Mirakl)
     */
    public function scopeWithOperatorType($query)
    {
        return $query->whereNotNull('marketplace_subtype');
    }

    /**
     * 🏷️ GET MARKETPLACE TEMPLATE
     */
    public function getMarketplaceTemplate(): ?array
    {
        return $this->marketplace_template;
    }

    /**
     * ✅ CHECK IF MARKETPLACE USES OPERATORS
     */
    public function isOperatorType(): bool
    {
        return $this->marketplace_subtype !== null;
    }

    /**
     * 🔧 GET MARKETPLACE DISPLAY NAME
     */
    public function getMarketplaceDisplayName(): string
    {
        if ($this->isOperatorType()) {
            $template = $this->getMarketplaceTemplate();
            $operators = $template['supported_operators'] ?? [];

            return $operators[$this->marketplace_subtype]['display_name'] ??
                   ucfirst($this->marketplace_subtype).' ('.ucfirst($this->marketplace_type).')';
        }

        return ucfirst($this->marketplace_type ?? $this->channel);
    }

    /**
     * 🧪 GET LAST CONNECTION TEST RESULT
     */
    public function getLastConnectionTestResult(): ?array
    {
        return $this->connection_test_result;
    }

    /**
     * ✅ CHECK IF CONNECTION TEST WAS SUCCESSFUL
     */
    public function hasSuccessfulConnectionTest(): bool
    {
        $result = $this->getLastConnectionTestResult();

        return $result && ($result['success'] ?? false);
    }

    /**
     * 🕒 GET CONNECTION TEST STATUS COLOR
     */
    public function getConnectionTestStatusColor(): string
    {
        if (! $this->connection_test_result) {
            return 'gray'; // Never tested
        }

        return $this->hasSuccessfulConnectionTest() ? 'green' : 'red';
    }

    /**
     * 📊 GET CONNECTION TEST SUMMARY
     */
    public function getConnectionTestSummary(): array
    {
        $result = $this->getLastConnectionTestResult();

        if (! $result) {
            return [
                'status' => 'never_tested',
                'message' => 'Connection not tested',
                'color' => 'gray',
                'icon' => 'question-mark-circle',
                'last_test' => null,
            ];
        }

        return [
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['message'] ?? 'Unknown status',
            'color' => $result['success'] ? 'green' : 'red',
            'icon' => $result['success'] ? 'check-circle' : 'x-circle',
            'last_test' => $this->last_connection_test?->diffForHumans(),
            'response_time' => $result['response_time'] ?? null,
        ];
    }

    /**
     * 💾 UPDATE CONNECTION TEST RESULT
     */
    public function updateConnectionTestResult(array $result): bool
    {
        return $this->update([
            'connection_test_result' => $result,
            'last_connection_test' => $result['success'] ? now() : $this->last_connection_test,
        ]);
    }

    /**
     * 🏪 GET MARKETPLACE CREDENTIALS AS VALUE OBJECT
     */
    public function getMarketplaceCredentials(): ?\App\ValueObjects\MarketplaceCredentials
    {
        if (! $this->marketplace_type || ! $this->credentials) {
            return null;
        }

        return new \App\ValueObjects\MarketplaceCredentials(
            type: $this->marketplace_type,
            credentials: $this->credentials,
            settings: $this->settings ?? [],
            operator: $this->marketplace_subtype
        );
    }

    /**
     * 🔧 GET REQUIRED FIELDS FOR THIS MARKETPLACE
     */
    public function getRequiredFields(): array
    {
        $registry = app(\App\Services\Marketplace\MarketplaceRegistry::class);

        return $registry->getRequiredFields(
            $this->marketplace_type ?? $this->channel,
            $this->marketplace_subtype
        );
    }

    /**
     * 📋 GET VALIDATION RULES FOR THIS MARKETPLACE
     */
    public function getValidationRules(): array
    {
        $registry = app(\App\Services\Marketplace\MarketplaceRegistry::class);

        return $registry->getValidationRules(
            $this->marketplace_type ?? $this->channel,
            $this->marketplace_subtype
        );
    }

    /**
     * 🎨 GET MARKETPLACE LOGO URL
     */
    public function getMarketplaceLogoUrl(): ?string
    {
        $template = $this->getMarketplaceTemplate();

        return $template['logo_url'] ?? null;
    }

    /**
     * 📄 GET MARKETPLACE DOCUMENTATION URL
     */
    public function getMarketplaceDocumentationUrl(): ?string
    {
        $template = $this->getMarketplaceTemplate();

        return $template['documentation_url'] ?? null;
    }

    // ===== SALES CHANNEL INTEGRATION METHODS ===== //

    /**
     * 🏷️ GET CHANNEL CODE - For pricing integration
     *
     * Format: {channel}_{account_name} → ebay_blindsoutlet, shopify_main
     * This code is used for channel-specific pricing attributes
     */
    public function getChannelCode(): string
    {
        $channel = \Illuminate\Support\Str::slug($this->channel);
        $name = \Illuminate\Support\Str::slug($this->name);

        return "{$channel}_{$name}";
    }

    /**
     * 🔗 GET CORRESPONDING SALES CHANNEL
     *
     * Get the auto-created SalesChannel for this SyncAccount
     */
    public function getSalesChannel(): ?\App\Models\SalesChannel
    {
        return \App\Models\SalesChannel::where('code', $this->getChannelCode())->first();
    }

    /**
     * ✅ HAS SALES CHANNEL - Check if corresponding channel exists
     */
    public function hasSalesChannel(): bool
    {
        return $this->getSalesChannel() !== null;
    }

    /**
     * 🎯 GET PRICING ATTRIBUTE KEY - For channel-specific pricing
     *
     * Returns: ebay_blindsoutlet_price, shopify_main_price, etc.
     */
    public function getPricingAttributeKey(): string
    {
        return $this->getChannelCode().'_price';
    }
}
