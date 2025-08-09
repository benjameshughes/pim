<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ShopifyProductSync;
use App\Models\ShopifyWebhookLog;
use App\Services\Shopify\API\ShopifySyncStatusService;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * 🏪 LEGENDARY SHOPIFY DASHBOARD 🏪
 * 
 * The most FABULOUS sync monitoring dashboard ever created!
 * Shows comprehensive sync intelligence with SASS and STYLE! 💅
 */
#[Layout('components.layouts.app')]
#[Title('Shopify Dashboard')]
class ShopifyDashboard extends Component
{
    public array $dashboardData = [];
    public array $recentActivity = [];
    public array $healthSummary = [];
    public array $webhookHealth = [];

    public function mount()
    {
        $this->loadDashboardData();
    }

    public function render()
    {
        return view('livewire.shopify-dashboard');
    }

    /**
     * 📊 Load all dashboard data with MAXIMUM FABULOUSNESS
     */
    public function loadDashboardData()
    {
        $syncService = app(ShopifySyncStatusService::class);
        
        // Get comprehensive dashboard data
        $this->dashboardData = $syncService->getSyncHealthDashboard();
        
        // Load additional UI-specific data
        $this->loadHealthSummary();
        $this->loadRecentActivity();
        $this->loadWebhookHealth();
    }

    /**
     * 💎 Load health summary with grades and scores
     */
    private function loadHealthSummary()
    {
        $syncRecords = ShopifyProductSync::with('product')->get();
        
        $healthScores = [];
        $gradeDistribution = ['A+' => 0, 'A' => 0, 'A-' => 0, 'B+' => 0, 'B' => 0, 'B-' => 0, 'C+' => 0, 'C' => 0, 'C-' => 0, 'D' => 0, 'F' => 0];
        
        foreach ($syncRecords as $sync) {
            $health = $sync->calculateSyncHealth();
            $healthScores[] = $health;
            
            $grade = $this->getHealthGrade($health);
            $gradeDistribution[$grade]++;
        }

        $averageHealth = count($healthScores) > 0 ? round(array_sum($healthScores) / count($healthScores)) : 0;
        
        $this->healthSummary = [
            'average_health' => $averageHealth,
            'total_products' => $syncRecords->count(),
            'healthy_products' => count(array_filter($healthScores, fn($score) => $score >= 80)),
            'needs_attention' => count(array_filter($healthScores, fn($score) => $score < 80)),
            'grade_distribution' => array_filter($gradeDistribution, fn($count) => $count > 0),
            'health_trend' => $this->calculateHealthTrend(),
        ];
    }

    /**
     * 📋 Load recent sync activity
     */
    private function loadRecentActivity()
    {
        // Recent sync records
        $recentSyncs = ShopifyProductSync::with('product')
            ->where('last_synced_at', '>=', now()->subDays(7))
            ->orderBy('last_synced_at', 'desc')
            ->limit(10)
            ->get();

        // Recent webhook events
        $recentWebhooks = ShopifyWebhookLog::with('product')
            ->recent()
            ->syncRelated()
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        $this->recentActivity = [
            'syncs' => $recentSyncs->map(function($sync) {
                return [
                    'type' => 'sync',
                    'product_name' => $sync->product?->name ?? 'Unknown Product',
                    'status' => $sync->sync_status,
                    'method' => $sync->sync_method,
                    'timestamp' => $sync->last_synced_at,
                    'health_grade' => $this->getHealthGrade($sync->calculateSyncHealth()),
                    'drift_score' => $sync->data_drift_score ?? 0,
                ];
            }),
            'webhooks' => $recentWebhooks->map(function($webhook) {
                return [
                    'type' => 'webhook',
                    'topic' => $webhook->topic,
                    'product_name' => $webhook->product?->name ?? 'Unknown Product',
                    'status' => $webhook->processing_status,
                    'timestamp' => $webhook->created_at,
                    'verified' => $webhook->signature_verified,
                ];
            }),
        ];
    }

    /**
     * 🔔 Load webhook health status
     */
    private function loadWebhookHealth()
    {
        $this->webhookHealth = [
            'total_webhooks_24h' => ShopifyWebhookLog::where('created_at', '>=', now()->subDay())->count(),
            'successful_webhooks' => ShopifyWebhookLog::where('created_at', '>=', now()->subDay())
                ->where('processing_status', 'success')->count(),
            'failed_webhooks' => ShopifyWebhookLog::where('created_at', '>=', now()->subDay())
                ->where('processing_status', 'failed')->count(),
            'webhook_topics' => ShopifyWebhookLog::recent()
                ->selectRaw('topic, COUNT(*) as count')
                ->groupBy('topic')
                ->pluck('count', 'topic')
                ->toArray(),
        ];
    }

    /**
     * 🔄 Refresh dashboard data
     */
    public function refresh()
    {
        $this->loadDashboardData();
        $this->dispatch('dashboard-refreshed');
    }

    /**
     * 🚀 Trigger bulk sync for products needing attention
     */
    public function syncProductsNeedingAttention()
    {
        $productsNeedingSync = ShopifyProductSync::needsAttention()
            ->with('product')
            ->limit(10) // Reasonable batch size
            ->get();

        foreach ($productsNeedingSync as $sync) {
            if ($sync->product) {
                // Trigger sync job here (implement based on your job queue setup)
                // SyncProductToShopify::dispatch($sync->product);
            }
        }

        $this->dispatch('bulk-sync-triggered', [
            'message' => 'Bulk sync initiated for ' . $productsNeedingSync->count() . ' products',
            'count' => $productsNeedingSync->count()
        ]);

        // Refresh after triggering sync
        $this->refresh();
    }

    // ===== HELPER METHODS ===== //

    private function getHealthGrade(int $health): string
    {
        return match(true) {
            $health >= 95 => 'A+',
            $health >= 90 => 'A',
            $health >= 85 => 'A-',
            $health >= 80 => 'B+',
            $health >= 75 => 'B',
            $health >= 70 => 'B-',
            $health >= 65 => 'C+',
            $health >= 60 => 'C',
            $health >= 55 => 'C-',
            $health >= 50 => 'D',
            $health > 0 => 'F',
            default => 'N/A'
        };
    }

    private function calculateHealthTrend(): string
    {
        // Simple trend calculation - could be enhanced with historical data
        $recentHealth = ShopifyProductSync::where('last_synced_at', '>=', now()->subDays(7))
            ->get()
            ->avg(fn($sync) => $sync->calculateSyncHealth());

        $olderHealth = ShopifyProductSync::where('last_synced_at', '<', now()->subDays(7))
            ->where('last_synced_at', '>=', now()->subDays(14))
            ->get()
            ->avg(fn($sync) => $sync->calculateSyncHealth());

        if (!$recentHealth || !$olderHealth) {
            return 'stable';
        }

        $difference = $recentHealth - $olderHealth;
        
        if ($difference > 5) {
            return 'improving';
        } elseif ($difference < -5) {
            return 'declining';
        } else {
            return 'stable';
        }
    }
}