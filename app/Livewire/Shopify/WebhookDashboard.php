<?php

namespace App\Livewire\Shopify;

use App\Models\ShopifyWebhookLog;
use App\Http\Middleware\ShopifyWebhookMiddleware;
use App\Services\Shopify\API\ShopifyWebhookService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 🎭 LEGENDARY SHOPIFY WEBHOOK DASHBOARD 🎭
 * 
 * The most FABULOUS webhook monitoring dashboard ever created!
 * Because webhook monitoring should be as stunning as a drag performance! 💅
 */
#[Layout('components.layouts.app')]
class WebhookDashboard extends Component
{
    use WithPagination;

    public string $filterStatus = 'all';
    public string $filterTopic = 'all';
    public string $timeRange = '24h';
    public string $search = '';
    
    public array $dashboardStats = [];
    public array $securityStats = [];
    public array $topicBreakdown = [];

    public function mount(): void
    {
        $this->loadDashboardData();
    }

    /**
     * 🔄 Refresh all dashboard data
     */
    public function refresh(): void
    {
        $this->loadDashboardData();
        $this->dispatch('dashboard-refreshed');
    }

    /**
     * 📊 Load LEGENDARY dashboard statistics
     */
    public function loadDashboardData(): void
    {
        $this->dashboardStats = $this->calculateDashboardStats();
        $this->securityStats = ShopifyWebhookMiddleware::getMiddlewareStats();
        $this->topicBreakdown = $this->calculateTopicBreakdown();
    }

    /**
     * 📈 Calculate comprehensive dashboard statistics
     */
    private function calculateDashboardStats(): array
    {
        $timeRange = $this->getTimeRangeCarbon();
        
        $stats = ShopifyWebhookLog::where('created_at', '>=', $timeRange)
            ->selectRaw('
                COUNT(*) as total_webhooks,
                COUNT(CASE WHEN status = "completed" THEN 1 END) as successful_webhooks,
                COUNT(CASE WHEN status = "failed" OR status = "permanent_failure" THEN 1 END) as failed_webhooks,
                COUNT(CASE WHEN status = "processing" THEN 1 END) as processing_webhooks,
                COUNT(CASE WHEN status = "queued" THEN 1 END) as queued_webhooks,
                AVG(CASE 
                    WHEN JSON_EXTRACT(metadata, "$.processing_time_ms") IS NOT NULL 
                    THEN CAST(JSON_EXTRACT(metadata, "$.processing_time_ms") as DECIMAL(10,2))
                END) as avg_processing_time,
                MAX(CASE 
                    WHEN JSON_EXTRACT(metadata, "$.processing_time_ms") IS NOT NULL 
                    THEN CAST(JSON_EXTRACT(metadata, "$.processing_time_ms") as DECIMAL(10,2))
                END) as max_processing_time
            ')
            ->first();

        // Calculate success rate
        $successRate = $stats->total_webhooks > 0 
            ? round(($stats->successful_webhooks / $stats->total_webhooks) * 100, 1)
            : 0;

        // Get hourly distribution
        $hourlyStats = $this->getHourlyWebhookStats($timeRange);

        return [
            'total_webhooks' => $stats->total_webhooks ?? 0,
            'successful_webhooks' => $stats->successful_webhooks ?? 0,
            'failed_webhooks' => $stats->failed_webhooks ?? 0,
            'processing_webhooks' => $stats->processing_webhooks ?? 0,
            'queued_webhooks' => $stats->queued_webhooks ?? 0,
            'success_rate' => $successRate,
            'avg_processing_time' => round($stats->avg_processing_time ?? 0, 2),
            'max_processing_time' => round($stats->max_processing_time ?? 0, 2),
            'hourly_distribution' => $hourlyStats,
            'health_status' => $this->calculateSystemHealth($successRate),
            'time_range' => $this->timeRange
        ];
    }

    /**
     * ⏰ Get hourly webhook statistics
     */
    private function getHourlyWebhookStats(Carbon $since): array
    {
        $hours = [];
        $now = now();
        
        for ($i = 23; $i >= 0; $i--) {
            $hour = $now->copy()->subHours($i);
            $nextHour = $hour->copy()->addHour();
            
            $count = ShopifyWebhookLog::whereBetween('created_at', [$hour, $nextHour])->count();
            
            $hours[] = [
                'hour' => $hour->format('H:00'),
                'count' => $count,
                'timestamp' => $hour->toISOString()
            ];
        }
        
        return $hours;
    }

    /**
     * 📊 Calculate topic breakdown statistics
     */
    private function calculateTopicBreakdown(): array
    {
        $timeRange = $this->getTimeRangeCarbon();
        
        return ShopifyWebhookLog::where('created_at', '>=', $timeRange)
            ->selectRaw('
                topic,
                COUNT(*) as total,
                COUNT(CASE WHEN status = "completed" THEN 1 END) as successful,
                COUNT(CASE WHEN status = "failed" OR status = "permanent_failure" THEN 1 END) as failed,
                AVG(CASE 
                    WHEN JSON_EXTRACT(metadata, "$.processing_time_ms") IS NOT NULL 
                    THEN CAST(JSON_EXTRACT(metadata, "$.processing_time_ms") as DECIMAL(10,2))
                END) as avg_time
            ')
            ->groupBy('topic')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) {
                $successRate = $item->total > 0 ? round(($item->successful / $item->total) * 100, 1) : 0;
                
                return [
                    'topic' => $item->topic,
                    'total' => $item->total,
                    'successful' => $item->successful,
                    'failed' => $item->failed,
                    'success_rate' => $successRate,
                    'avg_processing_time' => round($item->avg_time ?? 0, 2),
                    'status_color' => $successRate >= 95 ? 'emerald' : ($successRate >= 80 ? 'yellow' : 'red')
                ];
            })
            ->toArray();
    }

    /**
     * 🏥 Calculate system health status
     */
    private function calculateSystemHealth(float $successRate): array
    {
        if ($successRate >= 95) {
            return ['status' => 'LEGENDARY', 'color' => 'emerald', 'icon' => 'star'];
        } elseif ($successRate >= 85) {
            return ['status' => 'FABULOUS', 'color' => 'blue', 'icon' => 'check-circle'];
        } elseif ($successRate >= 70) {
            return ['status' => 'NEEDS WORK', 'color' => 'yellow', 'icon' => 'exclamation-triangle'];
        } else {
            return ['status' => 'CRITICAL', 'color' => 'red', 'icon' => 'x-circle'];
        }
    }

    /**
     * ⏰ Get Carbon instance for time range
     */
    private function getTimeRangeCarbon(): Carbon
    {
        return match($this->timeRange) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay()
        };
    }

    /**
     * 📋 Get filtered webhook logs
     */
    #[Computed]
    public function webhookLogs()
    {
        $query = ShopifyWebhookLog::query()
            ->with(['product'])
            ->orderByDesc('created_at');

        // Apply filters
        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterTopic !== 'all') {
            $query->where('topic', $this->filterTopic);
        }

        if ($this->search) {
            $query->where(function($q) {
                $q->where('topic', 'like', "%{$this->search}%")
                  ->orWhere('shopify_product_id', 'like', "%{$this->search}%")
                  ->orWhereJsonContains('metadata->shop_domain', $this->search);
            });
        }

        // Apply time range
        $query->where('created_at', '>=', $this->getTimeRangeCarbon());

        return $query->paginate(20);
    }

    /**
     * 🗂️ Get unique topics for filter dropdown
     */
    #[Computed]
    public function availableTopics()
    {
        return ShopifyWebhookLog::select('topic')
            ->distinct()
            ->orderBy('topic')
            ->pluck('topic')
            ->toArray();
    }

    /**
     * 🔄 Update filters and refresh data
     */
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->loadDashboardData();
    }

    public function updatedFilterTopic(): void
    {
        $this->resetPage();
        $this->loadDashboardData();
    }

    public function updatedTimeRange(): void
    {
        $this->resetPage();
        $this->loadDashboardData();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * 🗑️ Clear old webhook logs (admin action)
     */
    public function clearOldLogs(): void
    {
        $cutoff = now()->subDays(30);
        $deleted = ShopifyWebhookLog::where('created_at', '<', $cutoff)->delete();
        
        $this->dispatch('log-cleanup-completed', count: $deleted);
        $this->loadDashboardData();
    }

    /**
     * 🔁 Retry failed webhooks
     */
    public function retryFailedWebhooks(): void
    {
        $failedWebhooks = ShopifyWebhookLog::whereIn('status', ['failed', 'permanent_failure'])
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        $retried = 0;
        foreach ($failedWebhooks as $webhook) {
            // Re-queue the webhook for processing
            \App\Jobs\ProcessShopifyWebhookJob::dispatch(
                $webhook->id,
                $webhook->topic,
                $webhook->payload,
                $webhook->metadata ?? []
            );
            
            $webhook->update(['status' => 'queued']);
            $retried++;
        }

        $this->dispatch('webhooks-retried', count: $retried);
        $this->loadDashboardData();
    }

    /**
     * 🎭 Render the LEGENDARY webhook dashboard
     */
    public function render()
    {
        return view('livewire.shopify.webhook-dashboard');
    }
}