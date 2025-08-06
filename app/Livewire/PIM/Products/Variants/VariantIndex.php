<?php

namespace App\Livewire\PIM\Products\Variants;

use App\Models\ProductVariant;
use App\Contracts\HasStackedList;
use App\Concerns\HasStackedListBehavior;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class VariantIndex extends Component implements HasStackedList
{
    use HasStackedListBehavior;

    public $showDeleteModal = false;
    public $variantToDelete = null;

    protected $listeners = [
        'refreshList' => '$refresh'
    ];

    public function mount()
    {
        $this->initializeStackedList(ProductVariant::class, $this->getStackedListConfig());
    }

    public function getStackedListConfig(): array
    {
        return [
            'title' => 'Product Variants',
            'subtitle' => 'Manage individual product variants',
            'search_placeholder' => 'Search variants by SKU, color, size...',
            'export' => true,
            'per_page_options' => [10, 25, 50, 100],
            'searchable' => ['sku', 'color', 'size', 'product.name'],
            'sortable_columns' => ['sku', 'color', 'size', 'status', 'barcodes_count'],
            'filters' => [
                'product_id' => [
                    'type' => 'select',
                    'label' => 'Product',
                    'column' => 'product_id',
                    'options' => \App\Models\Product::orderBy('name')->pluck('name', 'id')->toArray()
                ],
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
                    'key' => 'sku',
                    'label' => 'SKU',
                    'type' => 'text',
                    'font' => 'font-mono text-sm',
                    'sortable' => true
                ],
                [
                    'key' => 'product.name',
                    'label' => 'Product',
                    'type' => 'text',
                    'font' => 'font-medium',
                    'sortable' => false
                ],
                [
                    'key' => 'color',
                    'label' => 'Color',
                    'type' => 'text',
                    'sortable' => true
                ],
                [
                    'key' => 'size',
                    'label' => 'Size',
                    'type' => 'text',
                    'sortable' => true
                ],
                [
                    'key' => 'barcodes_count',
                    'label' => 'Barcodes',
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
                            'route' => 'products.variants.view',
                            'icon' => 'eye'
                        ],
                        [
                            'label' => 'Edit',
                            'route' => 'products.variants.edit',
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
            'with' => ['product'],
            'withCount' => ['barcodes'],
            'default_sort' => [
                'column' => 'created_at',
                'direction' => 'desc'
            ],
            'header_actions' => [
                [
                    'label' => 'Create Variant',
                    'href' => route('products.variants.create'),
                    'icon' => 'plus'
                ]
            ],
            'empty_title' => 'No variants found',
            'empty_description' => 'Create your first variant to get started.',
            'empty_action' => [
                'label' => 'Create Variant',
                'href' => route('products.variants.create'),
                'icon' => 'plus'
            ]
        ];
    }

    public function handleBulkAction(string $action, array $selectedIds): void
    {
        match($action) {
            'delete' => $this->deleteVariants($selectedIds),
            'activate' => $this->updateVariantStatus($selectedIds, 'active'),
            default => null
        };
    }

    private function deleteVariants(array $ids): void
    {
        ProductVariant::whereIn('id', $ids)->delete();
        session()->flash('message', count($ids) . ' variants deleted.');
    }

    private function updateVariantStatus(array $ids, string $status): void
    {
        ProductVariant::whereIn('id', $ids)->update(['status' => $status]);
        session()->flash('message', count($ids) . ' variants updated.');
    }

    public function confirmDelete($variantId)
    {
        $this->variantToDelete = $variantId;
        $this->showDeleteModal = true;
    }

    public function deleteVariant()
    {
        $variant = ProductVariant::find($this->variantToDelete);
        
        if ($variant) {
            $variant->delete();
            session()->flash('message', 'Variant deleted successfully.');
        }

        $this->showDeleteModal = false;
        $this->variantToDelete = null;
    }

    public function render()
    {
        return view('livewire.pim.products.variants.variant-index');
    }
}