<?php

namespace App\Livewire\LogDashboard\Tabs;

use App\Facades\Activity;
use App\Services\LogParserService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

class Overview extends Component
{
    protected LogParserService $logParser;

    public function boot(LogParserService $logParser): void
    {
        $this->logParser = $logParser;
    }

    public function getSystemSummaryProperty(): array
    {
        $performanceMetrics = $this->logParser->getPerformanceMetrics();
        $recentActivity = Activity::recent(24);
        $recentErrors = $this->logParser->getRecentErrors(50);

        return [
            'total_requests' => $performanceMetrics['total_requests'],
            'avg_response_time' => $performanceMetrics['avg_response_time'],
            'error_rate' => $performanceMetrics['error_rate'],
            'total_activities' => $recentActivity->count(),
            'unique_users' => $recentActivity->pluck('user_id')->filter()->unique()->count(),
            'recent_errors' => $recentErrors->count(),
        ];
    }

    public function getRecentActivitySummaryProperty(): Collection
    {
        return Activity::recent(24)
            ->groupBy(function ($log) {
                return Str::before($log->event, '.');
            })
            ->map(fn ($group) => [
                'type' => ucwords(str_replace('_', ' ', $group->first()->event)),
                'count' => $group->count(),
                'latest' => $group->first()->occurred_at,
            ])
            ->sortByDesc('count')
            ->take(5);
    }

    public function getSystemHealthProperty(): array
    {
        $logSizes = $this->logParser->getLogFileSizes();
        $performanceMetrics = $this->logParser->getPerformanceMetrics();
        $recentErrors = $this->logParser->getRecentErrors(10);

        // Simple health scoring
        $healthScore = 100;

        // Deduct points for high error rate
        if ($performanceMetrics['error_rate'] > 5) {
            $healthScore -= 20;
        } elseif ($performanceMetrics['error_rate'] > 1) {
            $healthScore -= 10;
        }

        // Deduct points for slow responses
        if ($performanceMetrics['avg_response_time'] > 1000) {
            $healthScore -= 15;
        } elseif ($performanceMetrics['avg_response_time'] > 500) {
            $healthScore -= 5;
        }

        // Deduct points for recent errors
        if ($recentErrors->count() > 10) {
            $healthScore -= 15;
        } elseif ($recentErrors->count() > 5) {
            $healthScore -= 5;
        }

        $status = match (true) {
            $healthScore >= 90 => 'excellent',
            $healthScore >= 75 => 'good',
            $healthScore >= 60 => 'warning',
            default => 'critical'
        };

        return [
            'score' => max(0, $healthScore),
            'status' => $status,
            'color' => match ($status) {
                'excellent' => 'green',
                'good' => 'blue',
                'warning' => 'yellow',
                'critical' => 'red',
            },
        ];
    }

    public function render()
    {
        return view('livewire.log-dashboard.tabs.overview', [
            'systemSummary' => $this->systemSummary,
            'recentActivitySummary' => $this->recentActivitySummary,
            'systemHealth' => $this->systemHealth,
        ]);
    }
}
