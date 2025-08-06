<?php

namespace App\Livewire\Pim\Products\Management;

use App\Models\Product;
use App\Contracts\HasStackedList;
use App\Concerns\HasStackedListBehavior;
use App\StackedList\Concerns\HasStackedListActions;
use App\StackedList\Actions\BulkAction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ProductIndex extends Component implements HasStackedList
{
    use HasStackedListBehavior;
    use HasStackedListActions;

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
            'title' => 'Product Catalog',
            'subtitle' => 'Manage your product catalog with advanced filtering and bulk operations',
            
            // Search & Filter Configuration
            'search_placeholder' => 'Search products by name, SKU, or description...',
            'searchable' => ['name', 'description', 'parent_sku'],
            'sortable_columns' => ['name', 'parent_sku', 'status', 'variants_count'],
            
            // Data Configuration
            'withCount' => ['variants'],
            'per_page_options' => [5, 10, 25, 50, 100],
            'export' => true,
            
            // Header Actions
            'header_actions' => [
                [
                    'label' => 'Create Product',
                    'href' => route('products.create'),
                    'icon' => 'plus',
                    'variant' => 'primary'
                ]
            ],
            
            // Filters
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
            
            // Table Columns
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
                    'label' => '# Variants',
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
                            'icon' => 'eye',
                            'navigate' => true
                        ],
                        [
                            'label' => 'Edit',
                            'route' => 'products.product.edit',
                            'icon' => 'pencil',
                            'navigate' => true
                        ]
                    ]
                ]
            ],
            
            // Bulk Actions
            'bulk_actions' => collect([
                BulkAction::make('update_pricing')
                    ->label('Update Pricing')
                    ->icon('dollar-sign')
                    ->outline(),
                BulkAction::export(),
                BulkAction::make('toggle_status')
                    ->label('Toggle Status')
                    ->icon('refresh-cw')
                    ->outline(),
                BulkAction::delete()
            ])->map(fn($action) => $action->toArray())->toArray(),
            
            // Empty State Configuration
            'empty_title' => 'No products found',
            'empty_description' => 'Create your first product to get started with your catalog.',
            'empty_action' => [
                'label' => 'Create Product',
                'href' => route('products.create'),
                'icon' => 'plus',
                'variant' => 'primary'
            ]
        ];
    }

    private function toggleProductStatus(array $selectedIds): bool
    {
        foreach ($selectedIds as $id) {
            $product = Product::find($id);
            if ($product) {
                $product->status = $product->status === 'active' ? 'inactive' : 'active';
                $product->save();
            }
        }
        
        // No flash message - user can see the status change immediately
        
        return true;
    }

    public function handleBulkAction(string $action, array $selectedIds): void
    {
        // Handle all actions without flash messages
        match($action) {
            'update_pricing' => $this->handleUpdatePricing($selectedIds),
            'toggle_status' => $this->toggleProductStatus($selectedIds),
            'export' => $this->handleExport($selectedIds),
            'delete' => $this->handleDelete($selectedIds),
            default => null
        };
    }

    private function handleUpdatePricing(array $selectedIds): bool
    {
        // TODO: Implement pricing update functionality
        return true;
    }

    private function handleExport(array $selectedIds): bool
    {
        // TODO: Implement export functionality
        return true;
    }

    private function handleDelete(array $selectedIds): bool
    {
        // TODO: Implement delete functionality
        return true;
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