<?php

namespace App\Livewire\Examples;

use App\Models\Product;
use App\Contracts\HasStackedList;
use App\Concerns\HasStackedListBehavior;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AutoProductList extends Component implements HasStackedList
{
    use HasStackedListBehavior;

    protected $listeners = [
        'refreshList' => '$refresh'
    ];

    public function mount()
    {
        $this->initializeStackedList(Product::class, $this->getStackedListConfig());
    }

    public function getStackedListConfig(): array
    {
        return [
            // Header Configuration
            'title' => 'Example Product Gallery',
            'subtitle' => 'Example product listing with FilamentPHP-style config',
            
            // Search & Filter Configuration
            'search_placeholder' => 'Search products by name, SKU, or description...',
            'searchable' => ['name', 'parent_sku'],
            'sortable_columns' => ['name', 'parent_sku', 'status', 'variants_count'],
            
            // Data Configuration
            'withCount' => ['variants'],
            'per_page_options' => [5, 10, 25, 50],
            'export' => false,
            
            // Table Columns (excluding description, features, slug, metadata as requested)
            'columns' => [
                [
                    'key' => 'name',
                    'label' => 'Product Name',
                    'type' => 'text',
                    'font' => 'font-medium',
                    'sortable' => true
                ],
                [
                    'key' => 'parent_sku',
                    'label' => 'SKU',
                    'type' => 'text',
                    'font' => 'font-mono text-sm',
                    'sortable' => true
                ],
                [
                    'key' => 'variants_count',
                    'label' => 'Variants',
                    'type' => 'text',
                    'sortable' => true
                ],
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'type' => 'badge',
                    'sortable' => true,
                    'badges' => [
                        'active' => [
                            'label' => 'Active',
                            'class' => 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800',
                            'icon' => 'check-circle'
                        ],
                        'inactive' => [
                            'label' => 'Inactive',
                            'class' => 'bg-zinc-100 text-zinc-800 border-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:border-zinc-600',
                            'icon' => 'pause-circle'
                        ],
                        'discontinued' => [
                            'label' => 'Discontinued',
                            'class' => 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800',
                            'icon' => 'x-circle'
                        ]
                    ]
                ],
                [
                    'key' => 'actions',
                    'label' => 'Actions',
                    'type' => 'actions',
                    'actions' => [
                        [
                            'label' => 'Activate',
                            'method' => 'activateProduct',
                            'icon' => 'check-circle',
                            'navigate' => false
                        ],
                        [
                            'label' => 'Deactivate',
                            'method' => 'deactivateProduct',
                            'icon' => 'x-circle',
                            'navigate' => false
                        ]
                    ]
                ]
            ],
            
            // Empty State Configuration
            'empty_title' => 'No products found',
            'empty_description' => 'This is an example component showing the FilamentPHP-style config system.',
        ];
    }

    public function handleBulkAction(string $action, array $selectedIds): void
    {
        // No bulk actions for this example
    }

    // Custom action methods
    public function activateProduct($productId)
    {
        Product::find($productId)->update(['status' => 'active']);
        session()->flash('success', 'Product activated successfully!');
    }

    public function deactivateProduct($productId)
    {
        Product::find($productId)->update(['status' => 'inactive']);
        session()->flash('success', 'Product deactivated successfully!');
    }

    public function render()
    {
        return view('livewire.examples.auto-product-list');
    }
}