<?php

namespace App\Services\ChannelMapping;

use App\Models\ChannelFieldDefinition;
use App\Models\ChannelValueList;
use App\Models\SyncAccount;
use App\Services\EbayConnectService;
use App\Services\Marketplace\API\MarketplaceClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 🔍 CHANNEL FIELD DISCOVERY SERVICE
 *
 * Discovers and syncs field requirements from all marketplace APIs:
 * - Mirakl: /api/products/attributes & /api/values_lists
 * - Shopify: Product/variant fields & metafields
 * - eBay: Item specifics and aspects
 * - Amazon: Browse node attributes
 *
 * Implements monthly caching strategy for field requirements
 */
class ChannelFieldDiscoveryService
{
    protected ?EbayConnectService $ebayService = null;

    public function __construct(?EbayConnectService $ebayService = null)
    {
        $this->ebayService = $ebayService;
    }

    /**
     * 🔍 DISCOVER ALL CHANNELS
     *
     * Discover field requirements for all sync accounts
     */
    public function discoverAllChannels(): array
    {
        $results = [];
        $syncAccounts = SyncAccount::where('is_active', true)->get();

        foreach ($syncAccounts as $syncAccount) {
            $results[] = [
                'sync_account_id' => $syncAccount->id,
                'channel' => "{$syncAccount->marketplace_type}:{$syncAccount->account_name}",
                'result' => $this->discoverChannelFields($syncAccount),
            ];
        }

        return [
            'processed_accounts' => count($results),
            'results' => $results,
            'summary' => $this->summarizeDiscoveryResults($results),
        ];
    }

    /**
     * 🔍 DISCOVER CHANNEL FIELDS
     *
     * Discover field requirements for specific sync account
     */
    public function discoverChannelFields(SyncAccount $syncAccount): array
    {
        $startTime = microtime(true);

        try {
            Log::info("Starting field discovery for {$syncAccount->marketplace_type}:{$syncAccount->account_name}");

            $result = match ($syncAccount->marketplace_type) {
                'mirakl' => $this->discoverMiraklFields($syncAccount),
                'shopify' => $this->shopifyService ? $this->discoverShopifyFields($syncAccount) : [
                    'success' => false,
                    'error' => 'Shopify service not configured',
                ],
                'ebay' => $this->ebayService ? $this->discoverEbayFields($syncAccount) : [
                    'success' => false,
                    'error' => 'eBay service not configured',
                ],
                'amazon' => $this->discoverAmazonFields($syncAccount),
                default => [
                    'success' => false,
                    'error' => "Unsupported marketplace type: {$syncAccount->marketplace_type}",
                ],
            };

            $result['execution_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            $result['discovered_at'] = now()->toISOString();

            return $result;

        } catch (\Exception $e) {
            Log::error("Field discovery failed for {$syncAccount->marketplace_type}:{$syncAccount->account_name}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'discovered_at' => now()->toISOString(),
            ];
        }
    }

    /**
     * 🔍 DISCOVER MIRAKL FIELDS
     *
     * Use /api/products/attributes and /api/values_lists
     */
    protected function discoverMiraklFields(SyncAccount $syncAccount): array
    {
        // Create a new adapter specifically for this SyncAccount
        $config = [
            'operator' => $syncAccount->credentials['operator'] ?? 'unknown',
            'base_url' => $syncAccount->credentials['api_url'] ?? null,
            'api_key' => $syncAccount->credentials['api_key'] ?? null,
            'timeout' => 30,
        ];

        if (empty($config['base_url']) || empty($config['api_key'])) {
            return [
                'success' => false,
                'error' => 'Missing API credentials in SyncAccount',
                'debug' => [
                    'base_url' => $config['base_url'] ? 'SET' : 'MISSING',
                    'api_key' => $config['api_key'] ? 'SET' : 'MISSING',
                ],
            ];
        }

        // Use the new MarketplaceClient with sync account
        $client = MarketplaceClient::for('mirakl')
            ->withAccount($syncAccount)
            ->withConfig(['operator' => $config['operator']])
            ->build();

        // Get product attributes using new client
        $attributesResult = $client->getProductAttributes()->execute();
        if (! $attributesResult['success']) {
            return [
                'success' => false,
                'error' => "Failed to get Mirakl attributes: {$attributesResult['error']}",
            ];
        }

        // Get value lists using new client
        $valueListsResult = $client->getValueLists()->execute();
        if (! $valueListsResult['success']) {
            return [
                'success' => false,
                'error' => "Failed to get Mirakl value lists: {$valueListsResult['error']}",
            ];
        }

        $fields = $attributesResult['attributes'] ?? [];
        $valueLists = $valueListsResult['value_lists'] ?? [];

        $fieldCount = 0;
        $valueListCount = 0;

        // Process field definitions
        foreach ($fields as $field) {
            $this->storeFieldDefinition(
                channelType: 'mirakl',
                channelSubtype: $syncAccount->account_name,
                fieldData: $field,
                syncAccount: $syncAccount
            );
            $fieldCount++;
        }

        // Process value lists
        foreach ($valueLists as $valueList) {
            $this->storeValueList(
                channelType: 'mirakl',
                channelSubtype: $syncAccount->account_name,
                valueListData: $valueList,
                syncAccount: $syncAccount
            );
            $valueListCount++;
        }

        return [
            'success' => true,
            'fields_discovered' => $fieldCount,
            'value_lists_discovered' => $valueListCount,
            'required_fields' => collect($fields)->where('required', true)->count(),
            'optional_fields' => collect($fields)->where('required', false)->count(),
        ];
    }

    /**
     * 🔍 DISCOVER SHOPIFY FIELDS
     *
     * Discover Shopify product/variant fields and metafields
     */
    protected function discoverShopifyFields(SyncAccount $syncAccount): array
    {
        // Shopify has predefined product/variant structure
        $shopifyFields = [
            // Product fields
            ['code' => 'title', 'label' => 'Product Title', 'type' => 'TEXT', 'required' => true],
            ['code' => 'body_html', 'label' => 'Description', 'type' => 'LONG_TEXT', 'required' => false],
            ['code' => 'vendor', 'label' => 'Vendor', 'type' => 'TEXT', 'required' => false],
            ['code' => 'product_type', 'label' => 'Product Type', 'type' => 'TEXT', 'required' => false],
            ['code' => 'tags', 'label' => 'Tags', 'type' => 'LIST_MULTIPLE_VALUES', 'required' => false],
            ['code' => 'handle', 'label' => 'URL Handle', 'type' => 'TEXT', 'required' => false],
            ['code' => 'published', 'label' => 'Published', 'type' => 'BOOLEAN', 'required' => false],

            // Variant fields
            ['code' => 'sku', 'label' => 'SKU', 'type' => 'TEXT', 'required' => true],
            ['code' => 'price', 'label' => 'Price', 'type' => 'DECIMAL', 'required' => true],
            ['code' => 'compare_at_price', 'label' => 'Compare At Price', 'type' => 'DECIMAL', 'required' => false],
            ['code' => 'inventory_quantity', 'label' => 'Inventory Quantity', 'type' => 'INTEGER', 'required' => false],
            ['code' => 'weight', 'label' => 'Weight', 'type' => 'DECIMAL', 'required' => false],
            ['code' => 'barcode', 'label' => 'Barcode', 'type' => 'TEXT', 'required' => false],
            ['code' => 'option1', 'label' => 'Option 1 (Color)', 'type' => 'TEXT', 'required' => false],
            ['code' => 'option2', 'label' => 'Option 2 (Size)', 'type' => 'TEXT', 'required' => false],
            ['code' => 'option3', 'label' => 'Option 3 (Material)', 'type' => 'TEXT', 'required' => false],
        ];

        $fieldCount = 0;
        foreach ($shopifyFields as $field) {
            $this->storeFieldDefinition(
                channelType: 'shopify',
                channelSubtype: $syncAccount->account_name,
                fieldData: $field,
                syncAccount: $syncAccount
            );
            $fieldCount++;
        }

        // TODO: Discover actual metafields from Shopify API
        // TODO: Get product types, vendors, collections as value lists

        return [
            'success' => true,
            'fields_discovered' => $fieldCount,
            'value_lists_discovered' => 0,
            'required_fields' => collect($shopifyFields)->where('required', true)->count(),
            'optional_fields' => collect($shopifyFields)->where('required', false)->count(),
        ];
    }

    /**
     * 🔍 DISCOVER EBAY FIELDS
     *
     * Discover eBay item specifics and aspects
     */
    protected function discoverEbayFields(SyncAccount $syncAccount): array
    {
        // eBay fields depend on category, but we have some common ones
        $ebayFields = [
            ['code' => 'title', 'label' => 'Title', 'type' => 'TEXT', 'required' => true],
            ['code' => 'description', 'label' => 'Description', 'type' => 'LONG_TEXT', 'required' => true],
            ['code' => 'price', 'label' => 'Price', 'type' => 'DECIMAL', 'required' => true],
            ['code' => 'quantity', 'label' => 'Quantity', 'type' => 'INTEGER', 'required' => true],
            ['code' => 'condition', 'label' => 'Condition', 'type' => 'LIST', 'required' => true],
            ['code' => 'brand', 'label' => 'Brand', 'type' => 'TEXT', 'required' => false],
            ['code' => 'mpn', 'label' => 'MPN', 'type' => 'TEXT', 'required' => false],
            ['code' => 'upc', 'label' => 'UPC', 'type' => 'TEXT', 'required' => false],
            ['code' => 'ean', 'label' => 'EAN', 'type' => 'TEXT', 'required' => false],
        ];

        $fieldCount = 0;
        foreach ($ebayFields as $field) {
            $this->storeFieldDefinition(
                channelType: 'ebay',
                channelSubtype: $syncAccount->account_name,
                fieldData: $field,
                syncAccount: $syncAccount
            );
            $fieldCount++;
        }

        // TODO: Use eBay API to get category-specific item specifics
        // TODO: Get condition values and other enum lists

        return [
            'success' => true,
            'fields_discovered' => $fieldCount,
            'value_lists_discovered' => 0,
            'required_fields' => collect($ebayFields)->where('required', true)->count(),
            'optional_fields' => collect($ebayFields)->where('required', false)->count(),
        ];
    }

    /**
     * 🔍 DISCOVER AMAZON FIELDS
     *
     * Discover Amazon browse node attributes
     */
    protected function discoverAmazonFields(SyncAccount $syncAccount): array
    {
        // Amazon fields are complex and category-dependent
        $amazonFields = [
            ['code' => 'title', 'label' => 'Product Title', 'type' => 'TEXT', 'required' => true],
            ['code' => 'description', 'label' => 'Product Description', 'type' => 'LONG_TEXT', 'required' => true],
            ['code' => 'price', 'label' => 'Price', 'type' => 'DECIMAL', 'required' => true],
            ['code' => 'quantity', 'label' => 'Quantity', 'type' => 'INTEGER', 'required' => true],
            ['code' => 'brand', 'label' => 'Brand', 'type' => 'TEXT', 'required' => true],
            ['code' => 'manufacturer', 'label' => 'Manufacturer', 'type' => 'TEXT', 'required' => false],
            ['code' => 'asin', 'label' => 'ASIN', 'type' => 'TEXT', 'required' => false],
            ['code' => 'upc', 'label' => 'UPC', 'type' => 'TEXT', 'required' => false],
            ['code' => 'ean', 'label' => 'EAN', 'type' => 'TEXT', 'required' => false],
        ];

        $fieldCount = 0;
        foreach ($amazonFields as $field) {
            $this->storeFieldDefinition(
                channelType: 'amazon',
                channelSubtype: $syncAccount->account_name,
                fieldData: $field,
                syncAccount: $syncAccount
            );
            $fieldCount++;
        }

        // TODO: Use Amazon SP-API to get browse node specific attributes
        // TODO: Implement category-specific field discovery

        return [
            'success' => true,
            'fields_discovered' => $fieldCount,
            'value_lists_discovered' => 0,
            'required_fields' => collect($amazonFields)->where('required', true)->count(),
            'optional_fields' => collect($amazonFields)->where('required', false)->count(),
        ];
    }

    /**
     * 💾 STORE FIELD DEFINITION
     *
     * Store or update field definition in database
     */
    protected function storeFieldDefinition(
        string $channelType,
        ?string $channelSubtype,
        array $fieldData,
        SyncAccount $syncAccount,
        ?string $category = null
    ): ChannelFieldDefinition {
        $definition = ChannelFieldDefinition::updateOrCreate(
            [
                'channel_type' => $channelType,
                'channel_subtype' => $channelSubtype,
                'category' => $category,
                'field_code' => $fieldData['code'],
            ],
            [
                'field_label' => $fieldData['label'] ?? $fieldData['code'],
                'field_type' => $fieldData['type'] ?? 'TEXT',
                'is_required' => $fieldData['required'] ?? false,
                'description' => $fieldData['description'] ?? null,
                'field_metadata' => $fieldData['metadata'] ?? null,
                'validation_rules' => $fieldData['validation'] ?? null,
                'allowed_values' => $fieldData['allowed_values'] ?? null,
                'value_list_code' => $fieldData['value_list_code'] ?? null,
                'discovered_at' => now(),
                'last_verified_at' => now(),
                'api_version' => $fieldData['api_version'] ?? null,
                'is_active' => true,
            ]
        );

        Log::debug("Stored field definition: {$channelType}:{$channelSubtype} - {$fieldData['code']}");

        return $definition;
    }

    /**
     * 💾 STORE VALUE LIST
     *
     * Store or update value list in database
     */
    protected function storeValueList(
        string $channelType,
        ?string $channelSubtype,
        array $valueListData,
        SyncAccount $syncAccount
    ): ChannelValueList {
        $allowedValues = $valueListData['values'] ?? [];

        $valueList = ChannelValueList::updateOrCreate(
            [
                'channel_type' => $channelType,
                'channel_subtype' => $channelSubtype,
                'list_code' => $valueListData['code'],
            ],
            [
                'list_name' => $valueListData['name'] ?? $valueListData['code'],
                'list_description' => $valueListData['description'] ?? null,
                'allowed_values' => $allowedValues,
                'value_metadata' => $valueListData['metadata'] ?? null,
                'values_count' => count($allowedValues),
                'discovered_at' => now(),
                'last_synced_at' => now(),
                'api_version' => $valueListData['api_version'] ?? null,
                'is_active' => true,
                'sync_status' => 'synced',
                'sync_error' => null,
            ]
        );

        Log::debug("Stored value list: {$channelType}:{$channelSubtype} - {$valueListData['code']} ({$valueList->values_count} values)");

        return $valueList;
    }

    /**
     * 📊 SUMMARIZE DISCOVERY RESULTS
     *
     * Create summary of discovery operation
     */
    protected function summarizeDiscoveryResults(array $results): array
    {
        $successful = collect($results)->where('result.success', true);
        $failed = collect($results)->where('result.success', false);

        return [
            'total_accounts' => count($results),
            'successful' => $successful->count(),
            'failed' => $failed->count(),
            'total_fields' => $successful->sum('result.fields_discovered'),
            'total_value_lists' => $successful->sum('result.value_lists_discovered'),
            'total_required_fields' => $successful->sum('result.required_fields'),
            'total_optional_fields' => $successful->sum('result.optional_fields'),
            'errors' => $failed->pluck('result.error')->toArray(),
        ];
    }

    /**
     * 🔄 SYNC OUTDATED FIELDS
     *
     * Sync field definitions that haven't been updated in a month
     */
    public function syncOutdatedFields(): array
    {
        $outdatedDate = now()->subMonth();
        $syncAccounts = SyncAccount::where('is_active', true)
            ->whereHas('channelFieldDefinitions', function ($query) use ($outdatedDate) {
                $query->where('last_verified_at', '<', $outdatedDate);
            })
            ->get();

        $results = [];
        foreach ($syncAccounts as $syncAccount) {
            $results[] = [
                'sync_account_id' => $syncAccount->id,
                'channel' => "{$syncAccount->marketplace_type}:{$syncAccount->account_name}",
                'result' => $this->discoverChannelFields($syncAccount),
            ];
        }

        return [
            'processed_accounts' => count($results),
            'results' => $results,
            'summary' => $this->summarizeDiscoveryResults($results),
        ];
    }

    /**
     * 🔄 SYNC OUTDATED VALUE LISTS
     *
     * Sync value lists that need updating
     */
    public function syncOutdatedValueLists(): array
    {
        return ChannelValueList::syncAllOutdated();
    }

    /**
     * 📊 GET DISCOVERY STATISTICS
     *
     * Get statistics about discovered fields and value lists
     */
    public function getDiscoveryStatistics(): array
    {
        return [
            'field_definitions' => ChannelFieldDefinition::getStatistics(),
            'value_lists' => ChannelValueList::getStatistics(),
            'sync_accounts' => SyncAccount::where('is_active', true)->count(),
            'last_discovery' => ChannelFieldDefinition::max('discovered_at'),
            'last_sync' => $this->getLastSyncTime(),
            'discovery_health' => $this->getDiscoveryHealth(),
        ];
    }

    /**
     * 📅 GET LAST SYNC TIME
     */
    protected function getLastSyncTime(): ?\Carbon\Carbon
    {
        // Get the most recent discovery time across all field definitions
        $lastFieldSync = ChannelFieldDefinition::max('discovered_at');
        $lastValueListSync = ChannelValueList::max('last_synced_at');

        if (! $lastFieldSync && ! $lastValueListSync) {
            return null;
        }

        // Convert strings to Carbon instances if needed
        if ($lastFieldSync && ! $lastFieldSync instanceof \Carbon\Carbon) {
            $lastFieldSync = \Carbon\Carbon::parse($lastFieldSync);
        }
        if ($lastValueListSync && ! $lastValueListSync instanceof \Carbon\Carbon) {
            $lastValueListSync = \Carbon\Carbon::parse($lastValueListSync);
        }

        // Return the most recent of the two
        if (! $lastFieldSync) {
            return $lastValueListSync;
        }
        if (! $lastValueListSync) {
            return $lastFieldSync;
        }

        return $lastFieldSync->gt($lastValueListSync) ? $lastFieldSync : $lastValueListSync;
    }

    /**
     * ❤️ GET DISCOVERY HEALTH
     *
     * Health check for field discovery system
     */
    protected function getDiscoveryHealth(): array
    {
        $oneWeekAgo = now()->subWeek();
        $oneMonthAgo = now()->subMonth();

        $totalFields = ChannelFieldDefinition::count();
        $recentlyVerified = ChannelFieldDefinition::where('last_verified_at', '>', $oneWeekAgo)->count();
        $needsVerification = ChannelFieldDefinition::where('last_verified_at', '<', $oneMonthAgo)->count();

        $totalValueLists = ChannelValueList::count();
        $syncedValueLists = ChannelValueList::where('sync_status', 'synced')->count();
        $failedValueLists = ChannelValueList::where('sync_status', 'failed')->count();

        return [
            'field_health' => [
                'total' => $totalFields,
                'recently_verified' => $recentlyVerified,
                'needs_verification' => $needsVerification,
                'health_score' => $totalFields > 0 ? round(($recentlyVerified / $totalFields) * 100, 1) : 0,
            ],
            'value_list_health' => [
                'total' => $totalValueLists,
                'synced' => $syncedValueLists,
                'failed' => $failedValueLists,
                'health_score' => $totalValueLists > 0 ? round(($syncedValueLists / $totalValueLists) * 100, 1) : 0,
            ],
            'overall_health' => $this->calculateOverallHealth($totalFields, $recentlyVerified, $totalValueLists, $syncedValueLists),
        ];
    }

    /**
     * 🧮 CALCULATE OVERALL HEALTH
     *
     * Calculate overall system health score
     */
    protected function calculateOverallHealth(int $totalFields, int $verifiedFields, int $totalValueLists, int $syncedValueLists): array
    {
        $fieldScore = $totalFields > 0 ? ($verifiedFields / $totalFields) * 100 : 0;
        $valueListScore = $totalValueLists > 0 ? ($syncedValueLists / $totalValueLists) * 100 : 0;

        $overallScore = ($fieldScore + $valueListScore) / 2;

        $status = match (true) {
            $overallScore >= 90 => 'excellent',
            $overallScore >= 70 => 'good',
            $overallScore >= 50 => 'fair',
            default => 'poor',
        };

        return [
            'score' => round($overallScore, 1),
            'status' => $status,
            'recommendations' => $this->getHealthRecommendations($overallScore, $fieldScore, $valueListScore),
        ];
    }

    /**
     * 💡 GET HEALTH RECOMMENDATIONS
     *
     * Provide recommendations for improving system health
     */
    protected function getHealthRecommendations(float $overallScore, float $fieldScore, float $valueListScore): array
    {
        $recommendations = [];

        if ($fieldScore < 70) {
            $recommendations[] = 'Run field discovery to update outdated field definitions';
        }

        if ($valueListScore < 70) {
            $recommendations[] = 'Sync failed or outdated value lists';
        }

        if ($overallScore < 50) {
            $recommendations[] = 'Consider implementing automated monthly sync job';
            $recommendations[] = 'Check marketplace API credentials and connectivity';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'System health is good - continue regular monitoring';
        }

        return $recommendations;
    }
}
