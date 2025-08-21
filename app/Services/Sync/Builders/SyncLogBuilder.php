<?php

namespace App\Services\Sync\Builders;

use App\Models\Product;
use App\Models\SyncLog;
use Illuminate\Support\Collection;

/**
 * 📝 SYNC LOG BUILDER
 *
 * Beautiful fluent API for sync log queries:
 *
 * Sync::log()->failures()->today()->get()
 * Sync::log()->product($product)->shopify()->lastHours(24)->get()
 * Sync::log()->channel('ebay')->successful()->paginate()
 * Sync::log()->batch($batchId)->performance()
 */
class SyncLogBuilder
{
    protected \Illuminate\Database\Eloquent\Builder $query;

    public function __construct()
    {
        $this->query = SyncLog::query()->with(['product:id,name', 'syncAccount']);
    }

    /**
     * 📦 Filter by product
     */
    public function product(Product $product): self
    {
        $this->query->where('product_id', $product->id);

        return $this;
    }

    /**
     * 📦 Filter by multiple products
     */
    public function products(Collection|array $products): self
    {
        $productIds = collect($products)->pluck('id')->toArray();
        $this->query->whereIn('product_id', $productIds);

        return $this;
    }

    /**
     * 🏪 Filter by channel
     */
    public function channel(string $channel): self
    {
        $this->query->forChannel($channel);

        return $this;
    }

    /**
     * 🛍️ Shopify logs only
     */
    public function shopify(): self
    {
        return $this->channel('shopify');
    }

    /**
     * 🏪 eBay logs only
     */
    public function ebay(): self
    {
        return $this->channel('ebay');
    }

    /**
     * 📦 Amazon logs only
     */
    public function amazon(): self
    {
        return $this->channel('amazon');
    }

    /**
     * 🌐 Mirakl logs only
     */
    public function mirakl(): self
    {
        return $this->channel('mirakl');
    }

    /**
     * ✅ Successful operations only
     */
    public function successful(): self
    {
        $this->query->successful();

        return $this;
    }

    /**
     * ❌ Failed operations only
     */
    public function failures(): self
    {
        $this->query->failed();

        return $this;
    }

    /**
     * ⚠️ Operations with warnings
     */
    public function warnings(): self
    {
        $this->query->where('status', 'warning');

        return $this;
    }

    /**
     * 🔄 In progress operations
     */
    public function inProgress(): self
    {
        $this->query->inProgress();

        return $this;
    }

    /**
     * 🎯 Filter by action
     */
    public function action(string $action): self
    {
        $this->query->action($action);

        return $this;
    }

    /**
     * 🚀 Push operations only
     */
    public function pushes(): self
    {
        return $this->action('push');
    }

    /**
     * 🔽 Pull operations only
     */
    public function pulls(): self
    {
        return $this->action('pull');
    }

    /**
     * 📅 Today's logs
     */
    public function today(): self
    {
        $this->query->whereDate('created_at', today());

        return $this;
    }

    /**
     * 📅 Yesterday's logs
     */
    public function yesterday(): self
    {
        $this->query->whereDate('created_at', yesterday());

        return $this;
    }

    /**
     * ⏰ Last N hours
     */
    public function lastHours(int $hours): self
    {
        $this->query->recentActivity($hours);

        return $this;
    }

    /**
     * 📅 Last N days
     */
    public function lastDays(int $days): self
    {
        $this->query->where('created_at', '>=', now()->subDays($days));

        return $this;
    }

    /**
     * 📋 Filter by batch ID
     */
    public function batch(string $batchId): self
    {
        $this->query->batch($batchId);

        return $this;
    }

    /**
     * 🔗 Eager load relationships
     */
    public function with(...$relations): self
    {
        $this->query->with($relations);

        return $this;
    }

    /**
     * 🔢 Limit results
     */
    public function take(int $limit): self
    {
        $this->query->limit($limit);

        return $this;
    }

    /**
     * 🔢 Limit results (alias for take)
     */
    public function limit(int $limit): self
    {
        return $this->take($limit);
    }

    /**
     * ⚡ Fast operations (< 1 second)
     */
    public function fast(): self
    {
        $this->query->where('duration_ms', '<', 1000);

        return $this;
    }

    /**
     * 🐌 Slow operations (> 10 seconds)
     */
    public function slow(): self
    {
        $this->query->where('duration_ms', '>', 10000);

        return $this;
    }

    /**
     * 📈 Order by duration (slowest first)
     */
    public function slowest(): self
    {
        $this->query->orderByDesc('duration_ms');

        return $this;
    }

    /**
     * 📈 Order by duration (fastest first)
     */
    public function fastest(): self
    {
        $this->query->orderBy('duration_ms');

        return $this;
    }

    /**
     * 🕒 Latest logs first
     */
    public function latest(): self
    {
        $this->query->latest();

        return $this;
    }

    /**
     * 🕒 Oldest logs first
     */
    public function oldest(): self
    {
        $this->query->oldest();

        return $this;
    }

    /**
     * 📊 Get performance statistics
     */
    public function performance(): array
    {
        $logs = $this->query->get();

        if ($logs->isEmpty()) {
            return [
                'total_operations' => 0,
                'avg_duration_ms' => null,
                'success_rate' => 0,
                'performance_grade' => 'N/A',
            ];
        }

        $successfulLogs = $logs->where('status', 'success');
        $avgDuration = $logs->whereNotNull('duration_ms')->avg('duration_ms');
        $successRate = ($successfulLogs->count() / $logs->count()) * 100;

        return [
            'total_operations' => $logs->count(),
            'successful' => $successfulLogs->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'warnings' => $logs->where('status', 'warning')->count(),
            'avg_duration_ms' => $avgDuration ? round($avgDuration, 2) : null,
            'avg_duration_formatted' => $this->formatDuration($avgDuration),
            'fastest_ms' => $logs->whereNotNull('duration_ms')->min('duration_ms'),
            'slowest_ms' => $logs->whereNotNull('duration_ms')->max('duration_ms'),
            'success_rate' => round($successRate, 1),
            'performance_grade' => $this->getPerformanceGrade($avgDuration, $successRate),
        ];
    }

    /**
     * 📈 Get error analysis
     */
    public function errorAnalysis(): array
    {
        $failedLogs = $this->query->where('status', 'failed')->get();

        if ($failedLogs->isEmpty()) {
            return [
                'total_errors' => 0,
                'error_types' => [],
                'most_common_error' => null,
            ];
        }

        $errorCounts = $failedLogs->groupBy('message')->map->count()->sortDesc();

        return [
            'total_errors' => $failedLogs->count(),
            'unique_errors' => $errorCounts->count(),
            'error_types' => $errorCounts->take(10)->toArray(),
            'most_common_error' => $errorCounts->keys()->first(),
            'error_rate_by_hour' => $this->getErrorRateByHour($failedLogs),
        ];
    }

    /**
     * 📊 Get channel comparison
     */
    public function channelComparison(): array
    {
        $logs = $this->query->get();

        return $logs->groupBy('syncAccount.channel')->map(function ($channelLogs, $channel) {
            $successful = $channelLogs->where('status', 'success');

            return [
                'channel' => $channel,
                'total' => $channelLogs->count(),
                'successful' => $successful->count(),
                'failed' => $channelLogs->where('status', 'failed')->count(),
                'success_rate' => $channelLogs->count() > 0
                    ? round(($successful->count() / $channelLogs->count()) * 100, 1)
                    : 0,
                'avg_duration_ms' => $channelLogs->whereNotNull('duration_ms')->avg('duration_ms'),
            ];
        })->sortByDesc('success_rate')->values()->toArray();
    }

    /**
     * 📋 Execute query and get results
     */
    public function get(): Collection
    {
        return $this->query->get();
    }

    /**
     * 📄 Execute query with pagination
     */
    public function paginate(int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->query->paginate($perPage);
    }

    /**
     * 🔢 Count results
     */
    public function count(): int
    {
        return $this->query->count();
    }

    /**
     * 🎯 Get first result
     */
    public function first(): ?SyncLog
    {
        return $this->query->first();
    }

    /**
     * ⏰ Format duration for display
     */
    private function formatDuration(?float $durationMs): ?string
    {
        if ($durationMs === null) {
            return null;
        }

        if ($durationMs < 1000) {
            return round($durationMs, 0).'ms';
        }

        return round($durationMs / 1000, 2).'s';
    }

    /**
     * 📊 Get performance grade
     */
    private function getPerformanceGrade(?float $avgDuration, float $successRate): string
    {
        if ($successRate < 80) {
            return 'F';
        }

        if ($avgDuration === null) {
            return 'N/A';
        }

        if ($avgDuration < 1000 && $successRate >= 95) {
            return 'A+';
        }

        if ($avgDuration < 2000 && $successRate >= 90) {
            return 'A';
        }

        if ($avgDuration < 5000 && $successRate >= 85) {
            return 'B';
        }

        return 'C';
    }

    /**
     * 📈 Get error rate by hour
     */
    private function getErrorRateByHour(Collection $failedLogs): array
    {
        return $failedLogs->groupBy(function ($log) {
            return $log->created_at->format('H:00');
        })->map->count()->toArray();
    }
}
