<?php

namespace App\Livewire\Images;

use App\Actions\Images\GetImageActivityAction;
use App\Models\Image;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 📜 IMAGE HISTORY COMPONENT
 *
 * Displays activity logs for an image family (original + variants)
 * Shows creation, editing, processing, attachment, and deletion activities
 */
class ImageHistory extends Component
{
    use WithPagination;

    public Image $image;

    public $activities = [];

    public $groupedActivities = [];

    public $originalImage = null;

    public $variants = [];

    public $totalActivities = 0;

    public $activityTypes = [];

    // Filter properties
    public string $selectedType = 'all';

    public string $dateRange = 'all';

    public int $perPage = 20;

    public function mount(Image $image): void
    {
        $this->image = $image;
        $this->loadActivityHistory();
    }

    public function updatedSelectedType(): void
    {
        $this->resetPage();
        $this->applyFilters();
    }

    public function updatedDateRange(): void
    {
        $this->resetPage();
        $this->applyFilters();
    }

    public function loadActivityHistory(): void
    {
        $action = new GetImageActivityAction;
        $result = $action->execute($this->image);

        if ($result['success']) {
            $this->activities = $result['data']['activities']->toArray();
            $this->groupedActivities = $result['data']['grouped_activities'];
            $this->originalImage = $result['data']['original_image'];
            $this->variants = $result['data']['variants']->toArray();
            $this->totalActivities = $result['data']['total_activities'];
            $this->activityTypes = $result['data']['activity_types'];
        }

        $this->applyFilters();
    }

    protected function applyFilters(): void
    {
        $filtered = collect($this->activities);

        // Filter by activity type
        if ($this->selectedType !== 'all') {
            $filtered = $filtered->filter(function ($activity) {
                return str_replace('image.', '', $activity['event']) === $this->selectedType;
            });
        }

        // Filter by date range
        if ($this->dateRange !== 'all') {
            $cutoffDate = match ($this->dateRange) {
                'today' => now()->startOfDay(),
                'week' => now()->subWeek(),
                'month' => now()->subMonth(),
                default => null
            };

            if ($cutoffDate) {
                $filtered = $filtered->filter(function ($activity) use ($cutoffDate) {
                    return \Carbon\Carbon::parse($activity['occurred_at'])->gte($cutoffDate);
                });
            }
        }

        $this->activities = $filtered->values()->toArray();
    }

    public function getActivityIcon(string $eventType): string
    {
        return match ($eventType) {
            'created' => 'plus-circle',
            'updated' => 'pencil-square',
            'deleted' => 'trash',
            'processed' => 'arrow-path',
            'variants_generated' => 'sparkles',
            'attached' => 'link',
            'detached' => 'unlink-2',
            default => 'document-text'
        };
    }

    public function getActivityColor(string $eventType): string
    {
        return match ($eventType) {
            'created' => 'green',
            'updated' => 'blue',
            'deleted' => 'red',
            'processed' => 'purple',
            'variants_generated' => 'yellow',
            'attached' => 'indigo',
            'detached' => 'orange',
            default => 'gray'
        };
    }

    public function render(): View
    {
        return view('livewire.images.image-history');
    }
}
