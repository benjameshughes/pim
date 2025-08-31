<?php

namespace App\Livewire\LogDashboard\Tabs;

use App\Facades\Activity;
use App\Models\ActivityLog;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class ActivityTab extends Component
{
    use WithPagination;

    public string $search = '';

    public string $eventFilter = 'all';

    public string $timeFilter = '24'; // hours

    public int $perPage = 25;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedEventFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTimeFilter(): void
    {
        $this->resetPage();
    }

    public function setEventFilter(string $filter): void
    {
        $this->eventFilter = $filter;
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
        $this->eventFilter = 'all';
        $this->timeFilter = '24';
        $this->resetPage();
    }

    public function getActivityStatsProperty(): array
    {
        $hours = (int) $this->timeFilter;
        $recent = Activity::recent($hours);

        return [
            'total' => $recent->count(),
            'product_activities' => $recent->filter(fn ($log) => str_starts_with($log->event, 'product'))->count(),
            'variant_activities' => $recent->filter(fn ($log) => str_starts_with($log->event, 'variant'))->count(),
            'pricing_activities' => $recent->filter(fn ($log) => str_starts_with($log->event, 'pricing'))->count(),
            'tracking_activities' => $recent->filter(fn ($log) => str_starts_with($log->event, 'ui.'))->count(),
            'sync_activities' => $recent->filter(fn ($log) => str_starts_with($log->event, 'sync'))->count(),
            'user_activities' => $recent->filter(fn ($log) => str_starts_with($log->event, 'user'))->count(),
            'import_activities' => $recent->filter(fn ($log) => str_starts_with($log->event, 'import'))->count(),
            'system_activities' => $recent->filter(fn ($log) => is_null($log->user_id))->count(),
            'unique_users' => $recent->pluck('user_id')->filter()->unique()->count(),
        ];
    }

    public function getTopUsersProperty(): Collection
    {
        $hours = (int) $this->timeFilter;

        return Activity::recent($hours)
            ->groupBy('user_name')
            ->map(fn ($activities) => [
                'name' => $activities->first()->user_name ?? 'System',
                'count' => $activities->count(),
                'events' => $activities->pluck('event')->unique()->take(3)->toArray(),
                'latest' => $activities->first()->occurred_at,
            ])
            ->sortByDesc('count')
            ->take(8)
            ->values();
    }

    public function getEventTypesProperty(): Collection
    {
        $hours = (int) $this->timeFilter;

        return Activity::recent($hours)
            ->groupBy(function ($log) {
                $eventType = \Illuminate\Support\Str::before($log->event, '.');

                // Beautify event type names
                return match ($eventType) {
                    'ui' => 'tracking',
                    default => $eventType
                };
            })
            ->map(fn ($group) => [
                'type' => ucfirst($group->first() ?
                    (\Illuminate\Support\Str::before($group->first()->event, '.') === 'ui' ? 'tracking' : \Illuminate\Support\Str::before($group->first()->event, '.'))
                    : 'unknown'),
                'count' => $group->count(),
                'latest' => $group->first()?->occurred_at,
            ])
            ->sortByDesc('count')
            ->take(8) // Show more event types now that we have more
            ->values();
    }

    public function getActivitiesProperty()
    {
        $query = ActivityLog::with('user')->latest('occurred_at');

        // Apply time filter
        if ($this->timeFilter !== 'all') {
            $hours = (int) $this->timeFilter;
            $query->recent($hours);
        }

        // Apply event filter
        if ($this->eventFilter !== 'all') {
            if ($this->eventFilter === 'system') {
                $query->whereNull('user_id');
            } elseif ($this->eventFilter === 'ui') {
                // Special handling for UI/tracking events
                $query->where('event', 'like', 'ui.%');
            } else {
                $query->where('event', 'like', $this->eventFilter.'%');
            }
        }

        // Apply search filter
        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('event', 'like', "%{$this->search}%")
                    ->orWhereJsonContains('data->description', $this->search)
                    ->orWhereJsonContains('data->subject->name', $this->search)
                    ->orWhereHas('user', function ($userQuery) {
                        $userQuery->where('name', 'like', "%{$this->search}%");
                    });
            });
        }

        return $query->paginate($this->perPage);
    }

    public function getAvailableEventTypesProperty(): array
    {
        return [
            'all' => 'All Events',
            'product' => 'Product Events',
            'variant' => 'Variant Events',
            'pricing' => 'Pricing Events',
            'ui' => 'Tracking Events', // Button clicks, form submissions, etc.
            'sync' => 'Sync Events',
            'user' => 'User Events',
            'import' => 'Import Events',
            'system' => 'System Events',
        ];
    }

    public function getTimeFiltersProperty(): array
    {
        return [
            '1' => 'Last Hour',
            '24' => 'Last 24 Hours',
            '168' => 'Last Week', // 24 * 7
            '720' => 'Last Month', // 24 * 30
            'all' => 'All Time',
        ];
    }

    public function render()
    {
        return view('livewire.log-dashboard.tabs.activity-tab', [
            'activities' => $this->activities,
            'activityStats' => $this->activityStats,
            'topUsers' => $this->topUsers,
            'eventTypes' => $this->eventTypes,
            'availableEventTypes' => $this->availableEventTypes,
            'timeFilters' => $this->timeFilters,
        ]);
    }
}
