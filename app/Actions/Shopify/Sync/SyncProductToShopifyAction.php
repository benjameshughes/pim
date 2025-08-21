<?php

namespace App\Actions\Shopify\Sync;

use App\Actions\Base\BaseAction;
use App\Models\Product;
use App\Models\ShopifyProductSync;
use App\Services\Shopify\API\ShopifyDataComparatorService;
use App\Services\ShopifyConnectService;
use App\Services\Shopify\Services\ShopifyExportService;
use Illuminate\Support\Facades\Log;

/**
 * 🚀 SYNC PRODUCT TO SHOPIFY ACTION 🚀
 *
 * Performs comprehensive product synchronization to Shopify like a SYNC SUPERHERO!
 * Handles creation, updates, data comparison, and error recovery with STYLE! 💅
 */
class SyncProductToShopifyAction extends BaseAction
{
    private ShopifyConnectService $shopifyService;

    private ShopifyExportService $exportService;

    private ShopifyDataComparatorService $comparatorService;

    public function __construct(
        ShopifyConnectService $shopifyService,
        ShopifyExportService $exportService,
        ShopifyDataComparatorService $comparatorService
    ) {
        parent::__construct();
        $this->shopifyService = $shopifyService;
        $this->exportService = $exportService;
        $this->comparatorService = $comparatorService;
    }

    /**
     * Execute comprehensive product sync to Shopify
     */
    protected function performAction(...$params): array
    {
        $product = $params[0] ?? null;
        $options = $params[1] ?? [];

        if (! $product instanceof Product) {
            throw new \InvalidArgumentException('First parameter must be a Product instance');
        }

        $syncMethod = $options['method'] ?? 'manual';
        $forceUpdate = $options['force'] ?? false;

        Log::info('🚀 Starting product sync to Shopify', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sync_method' => $syncMethod,
            'force_update' => $forceUpdate,
        ]);

        $startTime = microtime(true);

        try {
            // Step 1: Check if product needs syncing (unless forced)
            if (! $forceUpdate && ! $this->needsSync($product)) {
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                Log::info('⏭️ Sync skipped - product already up to date', [
                    'product_id' => $product->id,
                    'duration_ms' => $duration,
                ]);

                return $this->success('Product is already synchronized', [
                    'action' => 'skipped',
                    'reason' => 'already_synced',
                    'duration_ms' => $duration,
                ]);
            }

            // Step 2: Determine sync strategy
            $existingSync = ShopifyProductSync::getMainSyncRecord($product->id);
            $isNewProduct = ! $existingSync || ! $existingSync->shopify_product_id;

            // Step 3: Perform the sync
            if ($isNewProduct) {
                $result = $this->performInitialSync($product, $syncMethod);
            } else {
                $result = $this->performUpdateSync($product, $existingSync, $syncMethod);
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Step 4: Update sync record with comprehensive data
            $this->updateSyncRecord($product, $result, $syncMethod, $duration);

            Log::info('✅ Product sync completed successfully', [
                'product_id' => $product->id,
                'sync_type' => $isNewProduct ? 'create' : 'update',
                'duration_ms' => $duration,
                'shopify_id' => $result['shopify_product_id'] ?? null,
            ]);

            return $this->success('Product synchronized successfully', [
                'action' => $isNewProduct ? 'created' : 'updated',
                'shopify_product_id' => $result['shopify_product_id'] ?? null,
                'shopify_url' => $result['shopify_url'] ?? null,
                'variants_synced' => $result['variants_synced'] ?? 0,
                'duration_ms' => $duration,
                'sync_details' => $result,
            ]);

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Record the failure
            $this->recordSyncFailure($product, $e, $syncMethod, $duration);

            Log::error('❌ Product sync failed', [
                'product_id' => $product->id,
                'duration_ms' => $duration,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->failure('Product sync failed: '.$e->getMessage(), [
                'error_type' => get_class($e),
                'error_details' => $e->getMessage(),
                'duration_ms' => $duration,
                'recovery_suggestions' => $this->getRecoverySuggestions($e),
            ]);
        }
    }

    /**
     * Perform initial sync for new products
     */
    private function performInitialSync(Product $product, string $syncMethod): array
    {
        Log::info('📦 Creating new product in Shopify', [
            'product_id' => $product->id,
            'product_name' => $product->name,
        ]);

        // Use existing export service for initial creation
        $exportResult = $this->exportService->exportProductsToShopify([$product]);

        if (! $exportResult['success'] || empty($exportResult['results'])) {
            throw new \Exception('Failed to create product in Shopify: '.($exportResult['message'] ?? 'Unknown error'));
        }

        $productResult = $exportResult['results'][0] ?? null;
        if (! $productResult || ! $productResult['success']) {
            throw new \Exception('Product creation failed: '.($productResult['error'] ?? 'Unknown error'));
        }

        return [
            'action' => 'created',
            'shopify_product_id' => $productResult['shopify_product_id'],
            'shopify_url' => $this->buildShopifyUrl($productResult['shopify_product_id']),
            'variants_synced' => count($productResult['variants'] ?? []),
            'export_result' => $productResult,
        ];
    }

    /**
     * Perform update sync for existing products
     */
    private function performUpdateSync(Product $product, ShopifyProductSync $existingSync, string $syncMethod): array
    {
        Log::info('🔄 Updating existing product in Shopify', [
            'product_id' => $product->id,
            'shopify_product_id' => $existingSync->shopify_product_id,
        ]);

        // Get current Shopify data for comparison
        $shopifyId = $this->extractNumericId($existingSync->shopify_product_id);
        $currentData = $this->shopifyService->getProduct($shopifyId);

        if (! $currentData['success']) {
            throw new \Exception('Failed to fetch current Shopify data: '.$currentData['error']);
        }

        // Perform data comparison to see what needs updating
        $comparison = $this->comparatorService->compareProductData($product, $currentData['data']);

        if (! $comparison['needs_sync']) {
            return [
                'action' => 'no_changes',
                'shopify_product_id' => $existingSync->shopify_product_id,
                'shopify_url' => $existingSync->getShopifyAdminUrl(),
                'variants_synced' => $existingSync->variants_synced ?? 0,
                'comparison' => $comparison,
            ];
        }

        // Perform targeted update based on what changed
        $updateResult = $this->performTargetedUpdate($product, $existingSync, $comparison);

        return [
            'action' => 'updated',
            'shopify_product_id' => $existingSync->shopify_product_id,
            'shopify_url' => $existingSync->getShopifyAdminUrl(),
            'variants_synced' => $updateResult['variants_synced'] ?? 0,
            'changes_made' => $updateResult['changes'] ?? [],
            'comparison' => $comparison,
        ];
    }

    /**
     * Perform targeted updates based on data comparison
     */
    private function performTargetedUpdate(Product $product, ShopifyProductSync $sync, array $comparison): array
    {
        $changes = [];
        $variantsSynced = 0;

        // For now, use the export service for updates (future enhancement: targeted GraphQL updates)
        $exportResult = $this->exportService->exportProductsToShopify([$product]);

        if (! $exportResult['success'] || empty($exportResult['results'])) {
            throw new \Exception('Failed to update product in Shopify: '.($exportResult['message'] ?? 'Unknown error'));
        }

        $productResult = $exportResult['results'][0] ?? null;
        if (! $productResult || ! $productResult['success']) {
            throw new \Exception('Product update failed: '.($productResult['error'] ?? 'Unknown error'));
        }

        // Track what was changed based on comparison
        if (! empty($comparison['differences'])) {
            $changes = array_keys($comparison['differences']);
        }

        return [
            'changes' => $changes,
            'variants_synced' => count($productResult['variants'] ?? []),
            'update_result' => $productResult,
        ];
    }

    /**
     * Check if product needs syncing
     */
    private function needsSync(Product $product): bool
    {
        $sync = ShopifyProductSync::getMainSyncRecord($product->id);

        if (! $sync || ! $sync->shopify_product_id) {
            return true; // Never synced
        }

        // Check if local data has changed since last sync
        if ($product->updated_at > $sync->last_synced_at) {
            return true;
        }

        // Check variant changes
        $hasVariantChanges = $product->variants()
            ->where('updated_at', '>', $sync->last_synced_at)
            ->exists();

        if ($hasVariantChanges) {
            return true;
        }

        // Check pricing changes
        $hasPricingChanges = $product->variants()
            ->whereHas('pricing', function ($query) use ($sync) {
                $query->where('updated_at', '>', $sync->last_synced_at);
            })
            ->exists();

        return $hasPricingChanges;
    }

    /**
     * Update sync record with comprehensive data
     */
    private function updateSyncRecord(Product $product, array $result, string $syncMethod, float $duration): void
    {
        // Find the primary color for the sync record (legacy compatibility)
        $primaryColor = $product->variants->first()?->color ?? 'default';

        ShopifyProductSync::updateComprehensiveSync(
            $product->id,
            $primaryColor,
            $result['shopify_product_id'],
            $result,
            [
                'status' => 'synced',
                'method' => $syncMethod,
                'variants_synced' => $result['variants_synced'] ?? 0,
                'duration' => (int) $duration,
                'health_score' => 100, // Successful sync gets perfect health
                'drift_score' => 0.0,   // Fresh sync has no drift
            ]
        );
    }

    /**
     * Record sync failure with detailed error information
     */
    private function recordSyncFailure(Product $product, \Exception $e, string $syncMethod, float $duration): void
    {
        $primaryColor = $product->variants->first()?->color ?? 'default';
        $existingSync = ShopifyProductSync::getMainSyncRecord($product->id);

        ShopifyProductSync::updateComprehensiveSync(
            $product->id,
            $primaryColor,
            $existingSync?->shopify_product_id ?? '',
            ['error' => $e->getMessage()],
            [
                'status' => 'failed',
                'method' => $syncMethod,
                'duration' => (int) $duration,
                'health_score' => 25, // Failed sync gets low health
                'errors' => [
                    'type' => get_class($e),
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'failed_at' => now()->toISOString(),
                ],
            ]
        );
    }

    /**
     * Get recovery suggestions based on error type
     */
    private function getRecoverySuggestions(\Exception $e): array
    {
        $suggestions = [];

        if (str_contains($e->getMessage(), 'rate limit')) {
            $suggestions[] = 'Wait a few minutes and retry - Shopify API rate limit reached';
            $suggestions[] = 'Enable automatic retry with exponential backoff';
        }

        if (str_contains($e->getMessage(), 'authentication')) {
            $suggestions[] = 'Check Shopify API credentials and permissions';
            $suggestions[] = 'Verify Shopify app is still installed and authorized';
        }

        if (str_contains($e->getMessage(), 'product') && str_contains($e->getMessage(), 'not found')) {
            $suggestions[] = 'Product may have been deleted in Shopify - consider re-creating';
            $suggestions[] = 'Reset sync record and perform fresh sync';
        }

        if (empty($suggestions)) {
            $suggestions[] = 'Review error details and check Shopify API documentation';
            $suggestions[] = 'Contact support if the issue persists';
        }

        return $suggestions;
    }

    /**
     * Build Shopify admin URL from product ID
     */
    private function buildShopifyUrl(string $shopifyProductId): ?string
    {
        $numericId = $this->extractNumericId($shopifyProductId);
        $storeUrl = config('services.shopify.store_url');

        return $numericId ? "https://{$storeUrl}/admin/products/{$numericId}" : null;
    }

    /**
     * Extract numeric ID from Shopify GID
     */
    private function extractNumericId(string $gid): ?int
    {
        if (preg_match('/\/Product\/(\d+)$/', $gid, $matches)) {
            return (int) $matches[1];
        }

        if (is_numeric($gid)) {
            return (int) $gid;
        }

        return null;
    }

    /**
     * Sync multiple products to Shopify (bulk operation)
     */
    public function syncBulkProducts(array $productIds, array $options = []): array
    {
        $results = [];
        $summary = [
            'total_requested' => count($productIds),
            'successful_syncs' => 0,
            'failed_syncs' => 0,
            'skipped_syncs' => 0,
        ];

        foreach ($productIds as $productId) {
            $product = Product::find($productId);
            if (! $product) {
                continue;
            }

            $result = $this->execute($product, $options);
            $results[] = [
                'product_id' => $productId,
                'product_name' => $product->name,
                'result' => $result,
            ];

            // Update summary
            if ($result['success']) {
                if ($result['data']['action'] === 'skipped') {
                    $summary['skipped_syncs']++;
                } else {
                    $summary['successful_syncs']++;
                }
            } else {
                $summary['failed_syncs']++;
            }
        }

        return [
            'summary' => $summary,
            'results' => $results,
            'last_synced_at' => now()->toISOString(),
        ];
    }
}
