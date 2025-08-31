<?php

namespace App\Livewire\LogDashboard\Tabs;

use App\Services\LogParserService;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class Errors extends Component
{
    use WithPagination;

    public string $search = '';
    public string $levelFilter = 'all'; // error, warning, all
    public string $timeFilter = '24'; // hours
    public int $perPage = 20;
    public bool $showContext = false;

    protected LogParserService $logParser;

    public function boot(LogParserService $logParser): void
    {
        $this->logParser = $logParser;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLevelFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTimeFilter(): void
    {
        $this->resetPage();
    }

    public function setLevelFilter(string $filter): void
    {
        $this->levelFilter = $filter;
        $this->resetPage();
    }

    public function setTimeFilter(string $hours): void
    {
        $this->timeFilter = $hours;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->levelFilter = 'all';
        $this->timeFilter = '24';
        $this->resetPage();
    }

    public function toggleContext(): void
    {
        $this->showContext = !$this->showContext;
    }

    public function getErrorStatsProperty(): array
    {
        $errors = $this->logParser->getRecentErrors(500);
        
        $stats = [
            'total_errors' => $errors->count(),
            'critical_errors' => $errors->where('level', 'ERROR')->count(),
            'warnings' => $errors->where('level', 'WARNING')->count(),
            'unique_messages' => $errors->pluck('message')->unique()->count(),
        ];

        // Calculate error rate trend (last 24h vs previous 24h)
        $last24h = $errors->where('timestamp', '>=', now()->subHours(24)->toISOString())->count();
        $previous24h = $errors->whereBetween('timestamp', [
            now()->subHours(48)->toISOString(),
            now()->subHours(24)->toISOString()
        ])->count();

        $stats['trend'] = $previous24h > 0 ? 
            round((($last24h - $previous24h) / $previous24h) * 100, 1) : 
            ($last24h > 0 ? 100 : 0);

        return $stats;
    }

    public function getTopErrorsProperty(): Collection
    {
        $errors = $this->logParser->getRecentErrors(200);
        
        return $errors
            ->groupBy('message')
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'message' => $first['message'],
                    'count' => $group->count(),
                    'level' => $first['level'],
                    'latest' => $group->first()['timestamp'],
                    'paths' => $group->pluck('path')->filter()->unique()->take(3)->toArray(),
                ];
            })
            ->sortByDesc('count')
            ->take(10)
            ->values();
    }

    public function getErrorsByHourProperty(): array
    {
        $errors = $this->logParser->getRecentErrors(200);
        
        return $errors
            ->groupBy(function ($error) {
                return $error['timestamp'] ? 
                    \Carbon\Carbon::parse($error['timestamp'])->format('H:00') : 
                    now()->format('H:00');
            })
            ->map(fn($group) => $group->count())
            ->sortKeys()
            ->toArray();
    }

    public function getFilteredErrorsProperty()
    {
        $errors = collect($this->logParser->getRecentErrors(1000));

        // Apply time filter
        if ($this->timeFilter !== 'all') {
            $hours = (int) $this->timeFilter;
            $cutoff = now()->subHours($hours)->toISOString();
            $errors = $errors->where('timestamp', '>=', $cutoff);
        }

        // Apply level filter
        if ($this->levelFilter !== 'all') {
            $errors = $errors->where('level', strtoupper($this->levelFilter));
        }

        // Apply search filter
        if (!empty($this->search)) {
            $errors = $errors->filter(function ($error) {
                return str_contains(strtolower($error['message'] ?? ''), strtolower($this->search)) ||
                       str_contains(strtolower($error['path'] ?? ''), strtolower($this->search));
            });
        }

        // Paginate manually
        $page = $this->getPage();
        $total = $errors->count();
        $items = $errors->forPage($page, $this->perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $this->perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    public function getErrorPatternsProperty(): Collection
    {
        $errors = $this->logParser->getRecentErrors(200);
        
        return $errors
            ->groupBy(function ($error) {
                // Extract error type/pattern from message
                $message = $error['message'] ?? '';
                
                if (str_contains($message, 'undefined')) {
                    return 'Undefined Variables/Methods';
                } elseif (str_contains($message, 'Class') && str_contains($message, 'not found')) {
                    return 'Class Not Found';
                } elseif (str_contains($message, 'Call to undefined')) {
                    return 'Undefined Function Calls';
                } elseif (str_contains($message, 'syntax error')) {
                    return 'Syntax Errors';
                } elseif (str_contains($message, 'database') || str_contains($message, 'SQL')) {
                    return 'Database Errors';
                } elseif (str_contains($message, 'permission') || str_contains($message, 'access')) {
                    return 'Permission/Access Errors';
                } else {
                    return 'Other Errors';
                }
            })
            ->map(fn($group) => [
                'pattern' => $group->first() ? $this->extractErrorPattern($group) : 'Unknown',
                'count' => $group->count(),
                'latest' => $group->first()['timestamp'] ?? null,
            ])
            ->sortByDesc('count')
            ->take(8)
            ->values();
    }

    protected function extractErrorPattern(Collection $errors): string
    {
        $messages = $errors->pluck('message')->take(3);
        return $messages->first() ?? 'Unknown Error Pattern';
    }

    public function getAvailableLevelsProperty(): array
    {
        return [
            'all' => 'All Levels',
            'error' => 'Errors Only',
            'warning' => 'Warnings Only',
        ];
    }

    public function getTimeFiltersProperty(): array
    {
        return [
            '1' => 'Last Hour',
            '24' => 'Last 24 Hours',
            '168' => 'Last Week',
            '720' => 'Last Month',
            'all' => 'All Time',
        ];
    }

    public function render()
    {
        return view('livewire.log-dashboard.tabs.errors', [
            'errors' => $this->filteredErrors,
            'errorStats' => $this->errorStats,
            'topErrors' => $this->topErrors,
            'errorsByHour' => $this->errorsByHour,
            'errorPatterns' => $this->errorPatterns,
            'availableLevels' => $this->availableLevels,
            'timeFilters' => $this->timeFilters,
        ]);
    }
}