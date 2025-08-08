<?php

namespace App\Livewire\Operations;

use App\Traits\HasRouteTabs;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class BulkOperationsIndex extends Component
{
    use HasRouteTabs;

    protected $baseRoute = 'operations.bulk';

    protected $tabConfig = [
        'tabs' => [
            [
                'key' => 'overview',
                'label' => 'Overview',
                'icon' => 'chart-bar',
            ],
            [
                'key' => 'templates',
                'label' => 'Title Templates',
                'icon' => 'layout-grid',
            ],
            [
                'key' => 'attributes',
                'label' => 'Bulk Attributes',
                'icon' => 'tag',
            ],
            [
                'key' => 'quality',
                'label' => 'Data Quality',
                'icon' => 'shield-check',
            ],
            [
                'key' => 'recommendations',
                'label' => 'Smart Recommendations',
                'icon' => 'lightbulb',
            ],
            [
                'key' => 'ai',
                'label' => 'AI Assistant',
                'icon' => 'zap',
            ],
        ],
    ];

    public function mount()
    {
        // Redirect to the overview tab by default
        $this->redirectToDefaultTabIfNeeded();
    }

    public function render()
    {
        return view('livewire.operations.bulk-operations-index');
    }
}
