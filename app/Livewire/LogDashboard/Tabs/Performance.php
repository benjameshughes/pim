<?php

namespace App\Livewire\LogDashboard\Tabs;

use App\Services\LogParserService;
use Illuminate\Support\Collection;
use Livewire\Component;

class Performance extends Component
{
    public int $requestLimit = 50;
    public int $endpointLimit = 15;
    public bool $autoRefresh = false;
    public int $refreshInterval = 30;

    protected LogParserService $logParser;

    public function boot(LogParserService $logParser): void
    {
        $this->logParser = $logParser;
    }

    public function toggleAutoRefresh(): void
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    public function refreshData(): void
    {
        $this->dispatch('$refresh');
    }

    public function setRequestLimit(int $limit): void
    {
        $this->requestLimit = min(200, max(10, $limit));
    }

    public function setEndpointLimit(int $limit): void
    {
        $this->endpointLimit = min(50, max(5, $limit));
    }

    public function getPerformanceMetricsProperty(): array
    {
        return $this->logParser->getPerformanceMetrics();
    }

    public function getRecentRequestsProperty(): Collection
    {
        return $this->logParser->getRecentRequests($this->requestLimit);
    }

    public function getSlowestEndpointsProperty(): Collection
    {
        return $this->logParser->getSlowestEndpoints($this->endpointLimit);
    }

    public function getLogFileSizesProperty(): array
    {
        return $this->logParser->getLogFileSizes();
    }

    public function getRequestsByHourProperty(): array
    {
        $requests = $this->logParser->getRecentRequests(200);
        
        return $requests
            ->groupBy(function ($request) {
                return $request['timestamp'] ? 
                    \Carbon\Carbon::parse($request['timestamp'])->format('H:00') : 
                    now()->format('H:00');
            })
            ->map(fn($group) => $group->count())
            ->sortKeys()
            ->toArray();
    }

    public function getResponseTimeDistributionProperty(): array
    {
        $requests = $this->logParser->getRecentRequests(200);
        
        $distribution = [
            '0-100ms' => 0,
            '100-500ms' => 0, 
            '500ms-1s' => 0,
            '1s-2s' => 0,
            '2s+' => 0
        ];

        foreach ($requests as $request) {
            $duration = $request['duration_ms'] ?? 0;
            
            if ($duration < 100) {
                $distribution['0-100ms']++;
            } elseif ($duration < 500) {
                $distribution['100-500ms']++;
            } elseif ($duration < 1000) {
                $distribution['500ms-1s']++;
            } elseif ($duration < 2000) {
                $distribution['1s-2s']++;
            } else {
                $distribution['2s+']++;
            }
        }

        return $distribution;
    }

    public function getStatusCodeDistributionProperty(): array
    {
        $requests = $this->logParser->getRecentRequests(200);
        
        return $requests
            ->groupBy(function ($request) {
                $status = $request['status'] ?? 200;
                
                if ($status >= 200 && $status < 300) return '2xx Success';
                if ($status >= 300 && $status < 400) return '3xx Redirect';
                if ($status >= 400 && $status < 500) return '4xx Client Error';
                if ($status >= 500) return '5xx Server Error';
                
                return 'Unknown';
            })
            ->map(fn($group) => $group->count())
            ->toArray();
    }

    public function getPerformanceInsightsProperty(): array
    {
        $metrics = $this->performanceMetrics;
        $requests = $this->recentRequests;
        $insights = [];

        // Slow response insight
        if ($metrics['avg_response_time'] > 1000) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Slow Average Response Time',
                'message' => "Average response time is {$metrics['avg_response_time']}ms. Consider optimizing slow endpoints.",
                'action' => 'Review slowest endpoints below'
            ];
        }

        // High error rate insight
        if ($metrics['error_rate'] > 5) {
            $insights[] = [
                'type' => 'error',
                'title' => 'High Error Rate',
                'message' => "Error rate is {$metrics['error_rate']}%. This indicates potential issues.",
                'action' => 'Check the Errors tab for details'
            ];
        }

        // High request volume insight
        if ($metrics['total_requests'] > 1000) {
            $insights[] = [
                'type' => 'info',
                'title' => 'High Traffic Volume',
                'message' => "{$metrics['total_requests']} requests processed recently. Monitor performance closely.",
                'action' => 'Consider scaling if needed'
            ];
        }

        // Good performance insight
        if ($metrics['avg_response_time'] < 200 && $metrics['error_rate'] < 1) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Excellent Performance',
                'message' => 'Low response times and minimal errors. System is performing well!',
                'action' => 'Keep monitoring to maintain this level'
            ];
        }

        return $insights;
    }

    public function render()
    {
        return view('livewire.log-dashboard.tabs.performance', [
            'performanceMetrics' => $this->performanceMetrics,
            'recentRequests' => $this->recentRequests,
            'slowestEndpoints' => $this->slowestEndpoints,
            'logFileSizes' => $this->logFileSizes,
            'requestsByHour' => $this->requestsByHour,
            'responseTimeDistribution' => $this->responseTimeDistribution,
            'statusCodeDistribution' => $this->statusCodeDistribution,
            'performanceInsights' => $this->performanceInsights,
        ]);
    }
}