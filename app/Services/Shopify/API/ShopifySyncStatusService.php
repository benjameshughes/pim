<?php

namespace App\Services\Shopify\API;

use App\Models\Product;
use App\Models\ShopifyProductSync;
use App\Services\ShopifyConnectService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 💅 SHOPIFY SYNC STATUS SERVICE 💅
 *
 * Monitors sync status between our app and Shopify like they're SOULMATES!
 * Provides real-time sync monitoring, health tracking, and status reporting.
 *
 * Part of the organized Shopify API services architecture 🎭
 */
class ShopifySyncStatusService
{
    private ShopifyConnectService $shopifyService;

    public function __construct(ShopifyConnectService $shopifyService)
    {
        $this->shopifyService = $shopifyService;
    }

    /**
     * Get comprehensive sync status for a single product
     */
    public function getProductSyncStatus(Product $product): array
    {
        Log::info("🔍 Getting sync status for product: {$product->name}");

        $syncRecord = $this->getSyncRecord($product);

        if (! $syncRecord || ! $syncRecord->shopify_product_id) {
            return $this->buildNotSyncedStatus($product);
        }

        // Get current Shopify data for comparison
        $shopifyData = $this->fetchShopifyProductData($syncRecord->shopify_product_id);

        if (! $shopifyData['success']) {
            return $this->buildErrorStatus($product, $syncRecord, $shopifyData['error']);
        }

        // Build comprehensive status with all the juicy details
        return $this->buildSyncedStatus($product, $syncRecord, $shopifyData['data']);
    }

    /**
     * Get sync status for multiple products (bulk operation)
     */
    public function getBulkSyncStatus(Collection $products): array
    {
        Log::info('📊 Getting bulk sync status for '.$products->count().' products');

        $results = [];
        $summary = $this->initializeSummary($products->count());

        foreach ($products as $product) {
            $status = $this->getProductSyncStatus($product);
            $results[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'status' => $status,
            ];

            $this->updateSummary($summary, $status['status']);
        }

        return [
            'summary' => $summary,
            'products' => $results,
            'health_percentage' => $this->calculateHealthPercentage($summary),
        ];
    }

    /**
     * Check if a product needs to be synced based on timestamps and changes
     */
    public function needsSync(Product $product): bool
    {
        $syncRecord = $this->getSyncRecord($product);

        if (! $syncRecord || ! $syncRecord->shopify_product_id) {
            return true; // Never synced
        }

        // Check various conditions that indicate sync is needed
        return $this->checkLocalChanges($product, $syncRecord) ||
               $this->checkVariantChanges($product, $syncRecord) ||
               $this->checkPricingChanges($product, $syncRecord);
    }

    /**
     * Get real-time sync health dashboard data
     */
    public function getSyncHealthDashboard(): array
    {
        $syncRecords = ShopifyProductSync::with('product')->get();
        $totalProducts = Product::count();

        return [
            'overview' => $this->buildOverview($syncRecords, $totalProducts),
            'recent_activity' => $this->getRecentSyncActivity(),
            'health_score' => $this->calculateOverallHealthScore($syncRecords, $totalProducts),
            'sync_recommendations' => $this->getSyncRecommendations($syncRecords, $totalProducts),
            'status_breakdown' => $this->getStatusBreakdown($syncRecords),
        ];
    }

    /**
     * Get detailed sync information for the sync tab UI
     */
    public function getSyncTabData(Product $product): array
    {
        $status = $this->getProductSyncStatus($product);

        return [
            'sync_status' => $status,
            'sync_actions' => $this->getAvailableSyncActions($status),
            'sync_history' => $this->getSyncHistory($product),
            'shopify_link' => $status['shopify_url'] ?? null,
            'last_sync_details' => $this->getLastSyncDetails($product),
        ];
    }

    // ===== PRIVATE HELPER METHODS ===== //

    private function getSyncRecord(Product $product): ?ShopifyProductSync
    {
        return ShopifyProductSync::where('product_id', $product->id)->first();
    }

    private function fetchShopifyProductData(string $shopifyProductId): array
    {
        $numericId = $this->extractNumericId($shopifyProductId);

        if (! $numericId) {
            return ['success' => false, 'error' => 'Invalid Shopify product ID format'];
        }

        return $this->shopifyService->getProduct($numericId);
    }

    private function extractNumericId(string $gid): ?int
    {
        // Extract numeric ID from GID like "gid://shopify/Product/123456789"
        if (preg_match('/\/Product\/(\d+)$/', $gid, $matches)) {
            return (int) $matches[1];
        }

        // If it's already numeric
        if (is_numeric($gid)) {
            return (int) $gid;
        }

        return null;
    }

    private function buildNotSyncedStatus(Product $product): array
    {
        return [
            'status' => 'not_synced',
            'shopify_product_id' => null,
            'shopify_url' => null,
            'last_synced_at' => null,
            'sync_health' => 'unknown',
            'needs_sync' => true,
            'sync_summary' => 'Product has never been synced to Shopify',
            'recommendations' => [
                'Push this product to Shopify to enable sync tracking',
                'Ensure product has all required data (name, pricing, variants)',
            ],
        ];
    }

    private function buildErrorStatus(Product $product, ShopifyProductSync $syncRecord, string $error): array
    {
        return [
            'status' => 'error',
            'shopify_product_id' => $syncRecord->shopify_product_id,
            'shopify_url' => $this->buildShopifyProductUrl($syncRecord->shopify_product_id),
            'last_synced_at' => $syncRecord->last_synced_at,
            'sync_health' => 'critical',
            'error' => $error,
            'needs_sync' => true,
            'sync_summary' => 'Unable to fetch current Shopify data: '.$error,
            'recommendations' => [
                'Check if product still exists in Shopify',
                'Verify Shopify API credentials and permissions',
                'Consider re-syncing the product',
            ],
        ];
    }

    private function buildSyncedStatus(Product $product, ShopifyProductSync $syncRecord, array $shopifyData): array
    {
        $localUpdated = Carbon::parse($product->updated_at);
        $shopifyUpdated = Carbon::parse($shopifyData['updatedAt']);
        $lastSynced = Carbon::parse($syncRecord->last_synced_at);

        $needsSync = $this->needsSync($product);
        $syncHealth = $this->determineSyncHealth($localUpdated, $shopifyUpdated, $lastSynced, $needsSync);

        return [
            'status' => $needsSync ? 'out_of_sync' : 'synced',
            'shopify_product_id' => $syncRecord->shopify_product_id,
            'shopify_url' => $this->buildShopifyProductUrl($syncRecord->shopify_product_id),
            'last_synced_at' => $syncRecord->last_synced_at,
            'sync_health' => $syncHealth,
            'needs_sync' => $needsSync,
            'sync_summary' => $this->generateSyncSummary($needsSync, $localUpdated, $shopifyUpdated),
            'shopify_updated_at' => $shopifyData['updatedAt'],
            'local_updated_at' => $product->updated_at,
            'variants_status' => $this->compareVariantCounts($product, $shopifyData),
            'data_freshness' => $this->calculateDataFreshness($lastSynced),
            'recommendations' => $this->generateRecommendations($needsSync, $syncHealth),
        ];
    }

    private function buildShopifyProductUrl(string $shopifyProductId): string
    {
        $numericId = $this->extractNumericId($shopifyProductId);
        $storeUrl = config('services.shopify.store_url');

        return "https://{$storeUrl}/admin/products/{$numericId}";
    }

    private function determineSyncHealth(Carbon $localUpdated, Carbon $shopifyUpdated, Carbon $lastSynced, bool $needsSync): string
    {
        if ($needsSync) {
            $hoursSinceLastSync = $lastSynced->diffInHours(now());

            if ($hoursSinceLastSync > 24) {
                return 'poor';
            } elseif ($hoursSinceLastSync > 6) {
                return 'fair';
            } else {
                return 'good';
            }
        }

        return 'excellent';
    }

    private function generateSyncSummary(bool $needsSync, Carbon $localUpdated, Carbon $shopifyUpdated): string
    {
        if (! $needsSync) {
            return 'Product is perfectly synchronized with Shopify';
        }

        if ($localUpdated->gt($shopifyUpdated)) {
            return 'Local changes need to be pushed to Shopify';
        } elseif ($shopifyUpdated->gt($localUpdated)) {
            return 'Shopify has newer data - consider pulling updates';
        }

        return 'Sync status needs verification';
    }

    private function compareVariantCounts(Product $product, array $shopifyData): array
    {
        $localCount = $product->variants->count();
        $shopifyCount = count($shopifyData['variants'] ?? []);

        return [
            'local_count' => $localCount,
            'shopify_count' => $shopifyCount,
            'count_match' => $localCount === $shopifyCount,
            'status' => $localCount === $shopifyCount ? 'synced' : 'mismatch',
        ];
    }

    private function calculateDataFreshness(Carbon $lastSynced): string
    {
        $hoursAgo = $lastSynced->diffInHours(now());

        if ($hoursAgo < 1) {
            return 'very_fresh';
        } elseif ($hoursAgo < 6) {
            return 'fresh';
        } elseif ($hoursAgo < 24) {
            return 'moderate';
        } else {
            return 'stale';
        }
    }

    private function generateRecommendations(bool $needsSync, string $syncHealth): array
    {
        $recommendations = [];

        if ($needsSync) {
            $recommendations[] = 'Sync this product to update Shopify with latest changes';
        }

        if ($syncHealth === 'poor') {
            $recommendations[] = 'Consider setting up automatic syncing for this product';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Product sync is healthy - no action required';
        }

        return $recommendations;
    }

    // Additional helper methods for bulk operations, summaries, etc...
    private function initializeSummary(int $totalCount): array
    {
        return [
            'total' => $totalCount,
            'synced' => 0,
            'out_of_sync' => 0,
            'not_synced' => 0,
            'errors' => 0,
            'needs_attention' => 0,
        ];
    }

    private function updateSummary(array &$summary, string $status): void
    {
        $summary[$status] = ($summary[$status] ?? 0) + 1;

        if (in_array($status, ['out_of_sync', 'not_synced', 'error'])) {
            $summary['needs_attention']++;
        }
    }

    private function calculateHealthPercentage(array $summary): float
    {
        return $summary['total'] > 0
            ? round(($summary['synced'] / $summary['total']) * 100, 1)
            : 0;
    }

    private function checkLocalChanges(Product $product, ShopifyProductSync $syncRecord): bool
    {
        return $product->updated_at > $syncRecord->last_synced_at;
    }

    private function checkVariantChanges(Product $product, ShopifyProductSync $syncRecord): bool
    {
        return $product->variants()
            ->where('updated_at', '>', $syncRecord->last_synced_at)
            ->exists();
    }

    private function checkPricingChanges(Product $product, ShopifyProductSync $syncRecord): bool
    {
        return $product->variants()
            ->whereHas('pricing', function ($query) use ($syncRecord) {
                $query->where('updated_at', '>', $syncRecord->last_synced_at);
            })
            ->exists();
    }

    // Placeholder methods for dashboard functionality
    private function buildOverview(Collection $syncRecords, int $totalProducts): array
    {
        return [
            'total_products' => $totalProducts,
            'synced_products' => $syncRecords->where('sync_status', 'synced')->count(),
            'pending_sync' => $syncRecords->where('sync_status', 'pending')->count(),
            'sync_errors' => $syncRecords->where('sync_status', 'failed')->count(),
            'never_synced' => $totalProducts - $syncRecords->count(),
        ];
    }

    private function getRecentSyncActivity(): array
    {
        return ShopifyProductSync::with('product')
            ->where('last_synced_at', '>=', Carbon::now()->subHours(24))
            ->orderBy('last_synced_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($sync) => [
                'product_name' => $sync->product?->name,
                'action' => 'synced',
                'timestamp' => $sync->last_synced_at,
                'status' => $sync->sync_status,
            ])
            ->toArray();
    }

    private function calculateOverallHealthScore(Collection $syncRecords, int $totalProducts): int
    {
        if ($totalProducts === 0) {
            return 100;
        }

        $syncedCount = $syncRecords->where('sync_status', 'synced')->count();

        return round(($syncedCount / $totalProducts) * 100);
    }

    private function getSyncRecommendations(Collection $syncRecords, int $totalProducts): array
    {
        $recommendations = [];

        $failedCount = $syncRecords->where('sync_status', 'failed')->count();
        if ($failedCount > 0) {
            $recommendations[] = [
                'type' => 'error',
                'title' => 'Fix Failed Syncs',
                'description' => "{$failedCount} products have failed sync attempts",
                'action' => 'review_errors',
            ];
        }

        $neverSynced = $totalProducts - $syncRecords->count();
        if ($neverSynced > 0) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Sync New Products',
                'description' => "{$neverSynced} products never synced to Shopify",
                'action' => 'bulk_sync',
            ];
        }

        return $recommendations;
    }

    private function getStatusBreakdown(Collection $syncRecords): array
    {
        return [
            'synced' => $syncRecords->where('sync_status', 'synced')->count(),
            'pending' => $syncRecords->where('sync_status', 'pending')->count(),
            'failed' => $syncRecords->where('sync_status', 'failed')->count(),
            'out_of_sync' => $syncRecords->where('sync_status', 'out_of_sync')->count(),
        ];
    }

    private function getAvailableSyncActions(array $status): array
    {
        $actions = [];

        if ($status['needs_sync']) {
            $actions[] = [
                'action' => 'sync_now',
                'label' => 'Sync to Shopify',
                'type' => 'primary',
            ];
        }

        if ($status['status'] === 'error') {
            $actions[] = [
                'action' => 'retry_sync',
                'label' => 'Retry Sync',
                'type' => 'warning',
            ];
        }

        $actions[] = [
            'action' => 'view_in_shopify',
            'label' => 'View in Shopify',
            'type' => 'secondary',
        ];

        return $actions;
    }

    private function getSyncHistory(Product $product): array
    {
        // Placeholder for sync history - would come from sync logs
        return [];
    }

    private function getLastSyncDetails(Product $product): ?array
    {
        $syncRecord = $this->getSyncRecord($product);

        if (! $syncRecord) {
            return null;
        }

        return [
            'last_synced_at' => $syncRecord->last_synced_at,
            'sync_method' => $syncRecord->sync_method ?? 'manual',
            'variants_synced' => $syncRecord->variants_synced ?? 0,
            'sync_duration' => $syncRecord->sync_duration ?? null,
        ];
    }
}
