<?php

namespace App\Services\Sync\Builders;

use App\Models\Product;
use App\Models\SyncStatus;
use Illuminate\Support\Collection;

/**
 * 📊 SYNC STATUS BUILDER
 *
 * Beautiful fluent API for sync status queries:
 *
 * Sync::status()->product($product)->needsSync()->get()
 * Sync::status()->shopify()->failed()->count()
 * Sync::status()->colorSeparated()->byColor('red')->synced()->get()
 * Sync::status()->products($products)->health()
 */
class SyncStatusBuilder
{
    protected \Illuminate\Database\Eloquent\Builder $query;

    public function __construct()
    {
        $this->query = SyncStatus::query()->with(['product:id,name', 'syncAccount', 'productVariant']);
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
     * 🛍️ Shopify statuses only
     */
    public function shopify(): self
    {
        return $this->channel('shopify');
    }

    /**
     * 🏪 eBay statuses only
     */
    public function ebay(): self
    {
        return $this->channel('ebay');
    }

    /**
     * 📦 Amazon statuses only
     */
    public function amazon(): self
    {
        return $this->channel('amazon');
    }

    /**
     * 🌐 Mirakl statuses only
     */
    public function mirakl(): self
    {
        return $this->channel('mirakl');
    }

    /**
     * 🏢 Filter by account
     */
    public function account(string $channel, string $accountName): self
    {
        $this->query->forAccount($channel, $accountName);

        return $this;
    }

    /**
     * ✅ Synced items only
     */
    public function synced(): self
    {
        $this->query->synced();

        return $this;
    }

    /**
     * ⏳ Pending items only
     */
    public function pending(): self
    {
        $this->query->pending();

        return $this;
    }

    /**
     * ❌ Failed items only
     */
    public function failed(): self
    {
        $this->query->failed();

        return $this;
    }

    /**
     * 🔄 Out of sync items only
     */
    public function outOfSync(): self
    {
        $this->query->outOfSync();

        return $this;
    }

    /**
     * 🎯 Items that need sync
     */
    public function needsSync(): self
    {
        $this->query->needsSync();

        return $this;
    }

    /**
     * 🎨 Color-separated products only
     */
    public function colorSeparated(): self
    {
        $this->query->colorSeparated();

        return $this;
    }

    /**
     * 🎨 Filter by specific color
     */
    public function byColor(string $color): self
    {
        $this->query->byColor($color);

        return $this;
    }

    /**
     * 🎨 Filter by multiple colors
     */
    public function byColors(array $colors): self
    {
        $this->query->whereIn('color', $colors);

        return $this;
    }

    /**
     * 📅 Recently synced (last 24 hours)
     */
    public function recentlySynced(): self
    {
        $this->query->where('last_synced_at', '>=', now()->subDay());

        return $this;
    }

    /**
     * 📅 Stale sync (over 7 days)
     */
    public function stale(): self
    {
        $this->query->where('last_synced_at', '<=', now()->subDays(7));

        return $this;
    }

    /**
     * 🔗 Has external ID
     */
    public function hasExternalId(): self
    {
        $this->query->whereNotNull('external_product_id');

        return $this;
    }

    /**
     * 🚫 Missing external ID
     */
    public function missingExternalId(): self
    {
        $this->query->whereNull('external_product_id');

        return $this;
    }

    /**
     * 📊 Get sync health overview
     */
    public function health(): array
    {
        $statuses = $this->query->get();

        if ($statuses->isEmpty()) {
            return [
                'total_items' => 0,
                'synced' => 0,
                'pending' => 0,
                'failed' => 0,
                'health_score' => 0,
                'grade' => 'N/A',
                'breakdown' => [],
                'recommendations' => [],
            ];
        }

        $breakdown = [
            'synced' => $statuses->where('sync_status', 'synced')->count(),
            'pending' => $statuses->where('sync_status', 'pending')->count(),
            'failed' => $statuses->where('sync_status', 'failed')->count(),
            'out_of_sync' => $statuses->where('sync_status', 'out_of_sync')->count(),
        ];

        $healthScore = ($breakdown['synced'] / $statuses->count()) * 100;
        $grade = $this->calculateHealthGrade($healthScore);

        return [
            'total_items' => $statuses->count(),
            'synced' => $breakdown['synced'],
            'pending' => $breakdown['pending'],
            'failed' => $breakdown['failed'],
            'health_score' => round($healthScore, 1),
            'grade' => $grade,
            'breakdown' => $breakdown,
            'by_channel' => $this->getChannelBreakdown($statuses),
            'recent_activity' => $this->getRecentActivitySummary($statuses),
            'recommendations' => $this->generateHealthRecommendations($breakdown, $healthScore),
        ];
    }

    /**
     * 📈 Get sync trends
     */
    public function trends(): array
    {
        $statuses = $this->query->get();

        $trendData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $dayStatuses = $statuses->where('last_synced_at', '>=', $date.' 00:00:00')
                ->where('last_synced_at', '<=', $date.' 23:59:59');

            $trendData[$date] = [
                'date' => $date,
                'synced' => $dayStatuses->where('sync_status', 'synced')->count(),
                'failed' => $dayStatuses->where('sync_status', 'failed')->count(),
                'total' => $dayStatuses->count(),
            ];
        }

        return [
            'daily_trends' => array_values($trendData),
            'trend_direction' => $this->calculateTrendDirection($trendData),
            'improvement_rate' => $this->calculateImprovementRate($trendData),
        ];
    }

    /**
     * 🎨 Get color breakdown (for Shopify)
     */
    public function colorBreakdown(): array
    {
        $colorStatuses = $this->query->colorSeparated()->get();

        if ($colorStatuses->isEmpty()) {
            return [
                'total_colors' => 0,
                'colors' => [],
            ];
        }

        $colorGroups = $colorStatuses->groupBy('color');

        $colorData = $colorGroups->map(function ($statuses, $color) {
            return [
                'color' => $color,
                'total' => $statuses->count(),
                'synced' => $statuses->where('sync_status', 'synced')->count(),
                'failed' => $statuses->where('sync_status', 'failed')->count(),
                'pending' => $statuses->where('sync_status', 'pending')->count(),
                'success_rate' => $statuses->count() > 0
                    ? round(($statuses->where('sync_status', 'synced')->count() / $statuses->count()) * 100, 1)
                    : 0,
            ];
        })->sortByDesc('success_rate');

        return [
            'total_colors' => $colorData->count(),
            'colors' => $colorData->values()->toArray(),
            'best_performing_color' => $colorData->first()['color'] ?? null,
            'worst_performing_color' => $colorData->last()['color'] ?? null,
        ];
    }

    /**
     * ⚡ Quick status check
     */
    public function isSync(): bool
    {
        return $this->query->synced()->exists();
    }

    /**
     * ⚡ Quick needs sync check
     */
    public function isNeedsSync(): bool
    {
        return $this->query->needsSync()->exists();
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
    public function first(): ?SyncStatus
    {
        return $this->query->first();
    }

    /**
     * 📊 Private helper methods
     */
    private function calculateHealthGrade(float $healthScore): string
    {
        if ($healthScore >= 95) {
            return 'A+';
        }
        if ($healthScore >= 90) {
            return 'A';
        }
        if ($healthScore >= 85) {
            return 'B+';
        }
        if ($healthScore >= 80) {
            return 'B';
        }
        if ($healthScore >= 75) {
            return 'C+';
        }
        if ($healthScore >= 70) {
            return 'C';
        }
        if ($healthScore >= 65) {
            return 'D+';
        }
        if ($healthScore >= 60) {
            return 'D';
        }

        return 'F';
    }

    private function getChannelBreakdown(Collection $statuses): array
    {
        return $statuses->groupBy('syncAccount.channel')->map(function ($channelStatuses, $channel) {
            $synced = $channelStatuses->where('sync_status', 'synced');

            return [
                'channel' => $channel,
                'total' => $channelStatuses->count(),
                'synced' => $synced->count(),
                'health_score' => $channelStatuses->count() > 0
                    ? round(($synced->count() / $channelStatuses->count()) * 100, 1)
                    : 0,
            ];
        })->values()->toArray();
    }

    private function getRecentActivitySummary(Collection $statuses): array
    {
        $recentlySynced = $statuses->where('last_synced_at', '>=', now()->subHours(24));

        return [
            'last_24h_syncs' => $recentlySynced->count(),
            'avg_daily_syncs' => round($recentlySynced->count() / 1, 0), // For 1 day
            'most_active_channel' => $recentlySynced->groupBy('syncAccount.channel')
                ->sortByDesc(fn ($items) => $items->count())
                ->keys()
                ->first(),
        ];
    }

    private function generateHealthRecommendations(array $breakdown, float $healthScore): array
    {
        $recommendations = [];

        if ($breakdown['failed'] > 0) {
            $recommendations[] = [
                'type' => 'error',
                'message' => "Fix {$breakdown['failed']} failed sync(s)",
                'priority' => 'high',
            ];
        }

        if ($breakdown['pending'] > 5) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => "Process {$breakdown['pending']} pending sync(s)",
                'priority' => 'medium',
            ];
        }

        if ($healthScore < 80) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Consider enabling automatic sync for better health',
                'priority' => 'low',
            ];
        }

        return $recommendations;
    }

    private function calculateTrendDirection(array $trendData): string
    {
        $recent = collect($trendData)->takeLast(3);
        $older = collect($trendData)->take(3);

        $recentAvg = $recent->avg('synced');
        $olderAvg = $older->avg('synced');

        if ($recentAvg > $olderAvg * 1.1) {
            return 'improving';
        }
        if ($recentAvg < $olderAvg * 0.9) {
            return 'declining';
        }

        return 'stable';
    }

    private function calculateImprovementRate(array $trendData): float
    {
        $first = collect($trendData)->first();
        $last = collect($trendData)->last();

        if ($first['synced'] == 0) {
            return 0;
        }

        return round((($last['synced'] - $first['synced']) / $first['synced']) * 100, 1);
    }
}
