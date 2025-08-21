<?php

namespace App\Actions\Shopify\Sync;

use App\Actions\Base\BaseAction;
use App\Models\Product;
use App\Services\Shopify\API\ShopifyDataComparatorService;
use App\Services\Shopify\API\ShopifySyncStatusService;
use Illuminate\Support\Facades\Log;

/**
 * 🔍 CHECK SYNC STATUS ACTION 🔍
 *
 * Performs comprehensive sync status checking for products like a SYNC DETECTIVE!
 * Uses our fabulous sync status services with performance monitoring! 💅
 */
class CheckSyncStatusAction extends BaseAction
{
    private ShopifySyncStatusService $syncService;

    private ShopifyDataComparatorService $comparatorService;

    public function __construct(
        ShopifySyncStatusService $syncService,
        ShopifyDataComparatorService $comparatorService
    ) {
        parent::__construct();
        $this->syncService = $syncService;
        $this->comparatorService = $comparatorService;
    }

    /**
     * Execute comprehensive sync status check
     */
    protected function performAction(...$params): array
    {
        $product = $params[0] ?? null;
        $options = $params[1] ?? [];

        if (! $product instanceof Product) {
            throw new \InvalidArgumentException('First parameter must be a Product instance');
        }

        Log::info('🔍 Starting comprehensive sync status check', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'options' => $options,
        ]);

        $startTime = microtime(true);

        try {
            // Step 1: Get basic sync status
            $syncStatus = $this->syncService->getProductSyncStatus($product);

            // Step 2: If product is synced, perform data comparison
            $dataComparison = null;
            if ($syncStatus['status'] === 'synced' || $syncStatus['status'] === 'out_of_sync') {
                $dataComparison = $this->performDataComparison($product, $syncStatus);
            }

            // Step 3: Compile comprehensive status report
            $report = $this->compileSyncReport($product, $syncStatus, $dataComparison);

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            Log::info('✅ Sync status check completed', [
                'product_id' => $product->id,
                'duration_ms' => $duration,
                'status' => $report['overall_status'],
            ]);

            return $this->success('Sync status check completed', $report);

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            Log::error('❌ Sync status check failed', [
                'product_id' => $product->id,
                'duration_ms' => $duration,
                'error' => $e->getMessage(),
            ]);

            return $this->failure('Sync status check failed: '.$e->getMessage(), [
                'product_id' => $product->id,
                'error_details' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Perform detailed data comparison if product is synced
     */
    private function performDataComparison(Product $product, array $syncStatus): ?array
    {
        // Only compare if we have Shopify data available
        if (! isset($syncStatus['shopify_data']) || empty($syncStatus['shopify_data'])) {
            return null;
        }

        try {
            return $this->comparatorService->compareProductData($product, $syncStatus['shopify_data']);
        } catch (\Exception $e) {
            Log::warning('⚠️ Data comparison failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Compile comprehensive sync status report
     */
    private function compileSyncReport(Product $product, array $syncStatus, ?array $dataComparison): array
    {
        $report = [
            'product_info' => [
                'id' => $product->id,
                'name' => $product->name,
                'variants_count' => $product->variants->count(),
                'last_updated' => $product->updated_at,
            ],
            'sync_status' => $syncStatus,
            'data_comparison' => $dataComparison,
            'overall_status' => $this->determineOverallStatus($syncStatus, $dataComparison),
            'health_score' => $this->calculateHealthScore($syncStatus, $dataComparison),
            'recommendations' => $this->generateRecommendations($syncStatus, $dataComparison),
            'action_items' => $this->generateActionItems($syncStatus, $dataComparison),
            'checked_at' => now()->toISOString(),
        ];

        return $report;
    }

    /**
     * Determine overall sync status based on all factors
     */
    private function determineOverallStatus(array $syncStatus, ?array $dataComparison): string
    {
        // If basic sync failed, overall status is critical
        if ($syncStatus['status'] === 'error') {
            return 'critical';
        }

        if ($syncStatus['status'] === 'not_synced') {
            return 'not_synced';
        }

        // If synced but has data drift, determine severity
        if ($dataComparison && $dataComparison['needs_sync']) {
            return match ($dataComparison['drift_severity']) {
                'critical' => 'critical',
                'high' => 'needs_sync',
                'medium' => 'minor_drift',
                'low' => 'healthy',
                default => 'healthy'
            };
        }

        return 'healthy';
    }

    /**
     * Calculate overall health score (0-100)
     */
    private function calculateHealthScore(array $syncStatus, ?array $dataComparison): int
    {
        $score = 100;

        // Deduct for sync status issues
        if ($syncStatus['status'] === 'error') {
            $score -= 50;
        } elseif ($syncStatus['status'] === 'not_synced') {
            $score -= 40;
        } elseif ($syncStatus['status'] === 'out_of_sync') {
            $score -= 20;
        }

        // Deduct for data drift
        if ($dataComparison && isset($dataComparison['drift_score'])) {
            $driftScore = $dataComparison['drift_score'];
            if ($driftScore >= 8) {
                $score -= 30;
            } elseif ($driftScore >= 5) {
                $score -= 20;
            } elseif ($driftScore >= 2) {
                $score -= 10;
            }
        }

        // Deduct for sync health
        if (isset($syncStatus['sync_health'])) {
            $healthDeduction = match ($syncStatus['sync_health']) {
                'poor' => 25,
                'fair' => 15,
                'good' => 5,
                default => 0
            };
            $score -= $healthDeduction;
        }

        return max(0, $score);
    }

    /**
     * Generate actionable recommendations
     */
    private function generateRecommendations(array $syncStatus, ?array $dataComparison): array
    {
        $recommendations = [];

        // Basic sync recommendations
        if (isset($syncStatus['recommendations']) && is_array($syncStatus['recommendations'])) {
            $recommendations = array_merge($recommendations, $syncStatus['recommendations']);
        }

        // Data comparison recommendations
        if ($dataComparison && isset($dataComparison['recommendation'])) {
            $recommendations[] = $dataComparison['recommendation'];
        }

        // Ensure we always have at least one recommendation
        if (empty($recommendations)) {
            $recommendations[] = 'Product sync status is healthy - continue monitoring';
        }

        return array_unique($recommendations);
    }

    /**
     * Generate specific action items for the user
     */
    private function generateActionItems(array $syncStatus, ?array $dataComparison): array
    {
        $actions = [];

        if ($syncStatus['status'] === 'not_synced') {
            $actions[] = [
                'action' => 'initial_sync',
                'priority' => 'high',
                'description' => 'Sync this product to Shopify for the first time',
            ];
        }

        if ($syncStatus['status'] === 'error') {
            $actions[] = [
                'action' => 'resolve_errors',
                'priority' => 'urgent',
                'description' => 'Resolve sync errors and retry synchronization',
            ];
        }

        if ($dataComparison && $dataComparison['needs_sync']) {
            $priority = match ($dataComparison['drift_severity']) {
                'critical' => 'urgent',
                'high' => 'high',
                'medium' => 'normal',
                default => 'low'
            };

            $actions[] = [
                'action' => 'sync_updates',
                'priority' => $priority,
                'description' => 'Update Shopify with latest product changes',
            ];
        }

        if (empty($actions)) {
            $actions[] = [
                'action' => 'monitor',
                'priority' => 'low',
                'description' => 'Continue monitoring sync status',
            ];
        }

        return $actions;
    }

    /**
     * Check sync status for multiple products (bulk operation)
     */
    public function checkBulkSyncStatus(array $productIds, array $options = []): array
    {
        $results = [];
        $summary = [
            'total_checked' => 0,
            'healthy' => 0,
            'needs_sync' => 0,
            'critical' => 0,
            'not_synced' => 0,
        ];

        foreach ($productIds as $productId) {
            $product = Product::find($productId);
            if (! $product) {
                continue;
            }

            $result = $this->execute($product, $options);
            if ($result['success']) {
                $status = $result['data']['overall_status'];
                $results[] = [
                    'product_id' => $productId,
                    'product_name' => $product->name,
                    'status' => $status,
                    'health_score' => $result['data']['health_score'],
                    'report' => $result['data'],
                ];

                // Update summary
                $summary['total_checked']++;
                $summary[$status] = ($summary[$status] ?? 0) + 1;
            }
        }

        return [
            'summary' => $summary,
            'results' => $results,
            'checked_at' => now()->toISOString(),
        ];
    }
}
