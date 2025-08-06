<?php

namespace App\Livewire\PIM\Products\Management;

use App\Models\Product;
use App\Contracts\HasStackedList;
use App\Concerns\HasStackedListBehavior;
use App\StackedList\Concerns\HasStackedListActions;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ProductIndex extends Component implements HasStackedList
{
    use HasStackedListBehavior;
    use HasStackedListActions;

    // FilamentPHP-style: No property conflicts!
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
            'title' => 'Products',
            'subtitle' => 'Manage your product catalog',
            'search_placeholder' => 'Search products by name, SKU, or description...',
            'export' => true,
            'per_page_options' => [5, 10, 25, 50, 100],
            'searchable' => ['name', 'description', 'parent_sku'],
            'sortable_columns' => ['name', 'parent_sku', 'status', 'variants_count'],
            'filters' => [
                'status' => [
                    'type' => 'select',
                    'label' => 'Status',
                    'column' => 'status',
                    'options' => [
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'discontinued' => 'Discontinued'
                    ]
                ]
            ],
            'columns' => [
                [
                    'key' => 'name',
                    'label' => 'Name',
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
                            'label' => 'View',
                            'route' => 'products.view',
                            'icon' => 'eye'
                        ],
                        [
                            'label' => 'Edit',
                            'route' => 'products.product.edit',
                            'icon' => 'pencil'
                        ]
                    ]
                ]
            ],
            'bulk_actions' => [
                [
                    'key' => 'delete',
                    'label' => 'Delete Selected',
                    'variant' => 'danger',
                    'icon' => 'trash-2'
                ],
                [
                    'key' => 'activate',
                    'label' => 'Activate',
                    'variant' => 'outline',
                    'icon' => 'check-circle'
                ]
            ],
            'withCount' => ['variants'],
            'header_actions' => [
                [
                    'label' => 'Create Product',
                    'href' => route('products.create'),
                    'icon' => 'plus'
                ]
            ],
            'empty_title' => 'No products found',
            'empty_description' => 'Create your first product to get started.',
            'empty_action' => [
                'label' => 'Create Product',
                'href' => route('products.create'),
                'icon' => 'plus'
            ]
        ];
    }

    public function handleBulkAction(string $action, array $selectedIds): void
    {
        // Use the trait's common implementations
        if (!$this->handleCommonBulkActions($action, $selectedIds, Product::class)) {
            // Handle any custom actions here
            match($action) {
                default => session()->flash('error', "Unknown action: {$action}")
            };
        }
    }

    public function viewProduct($productId)
    {
        return $this->redirect(route('products.view', $productId));
    }

    public function render()
    {
        return view('livewire.pim.products.management.product-index');
    }
}