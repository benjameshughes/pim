<?php

namespace App\Livewire\Products;

use App\Facades\Activity;
use App\Models\Product;
use Illuminate\Support\Collection;
use Livewire\Component;

class ProductHistory extends Component
{
    public Product $product;

    public string $activeTab = 'activity';

    public function mount(Product $product)
    {
        $this->authorize('view-product-history');

        // 🚀 NO RELATIONSHIP LOADING - ProductShow already loaded syncLogs with 50 limit
        $this->product = $product;
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function getActivityLogsProperty(): Collection
    {
        return Activity::forSubject($this->product)
            ->take(50);
    }

    public function getSyncLogsProperty(): Collection
    {
        return collect($this->product->syncLogs);
    }

    public function getCombinedHistoryProperty(): Collection
    {
        $activityLogs = $this->activityLogs->map(function ($log) {
            return (object) [
                'type' => 'activity',
                'timestamp' => $log->occurred_at,
                'user_name' => $log->user_name,
                'event' => $log->event,
                'description' => $log->description,
                'details' => $log->getContextData(),
                'changes' => $log->changes,
            ];
        });

        $syncLogs = $this->syncLogs->map(function ($log) {
            return (object) [
                'type' => 'sync',
                'timestamp' => $log->created_at,
                'user_name' => 'System',
                'event' => 'sync.'.$log->action,
                'description' => $log->message,
                'details' => collect($log->details ?? []),
                'channel' => $log->syncAccount?->channel,
                'status' => $log->status,
            ];
        });

        return $activityLogs->concat($syncLogs)
            ->sortByDesc('timestamp')
            ->take(100);
    }

    public function render()
    {
        return view('livewire.products.product-history', [
            'combinedHistory' => $this->combinedHistory,
            'activityLogs' => $this->activityLogs,
            'syncLogs' => $this->syncLogs,
        ]);
    }
}
