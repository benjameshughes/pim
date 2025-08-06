<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Contracts\HasStackedList;
use App\Concerns\HasStackedListBehavior;
use Livewire\Component;

class ProductVariantsList extends Component implements HasStackedList
{
    use HasStackedListBehavior;

    public Product $product;

    protected $listeners = [
        'refreshList' => '$refresh'
    ];

    public function mount(Product $product)
    {
        $this->product = $product;
        $this->initializeStackedList(ProductVariant::class, $this->getStackedListConfig());
    }

    public function getStackedListConfig(): array
    {
        return [
            'title' => 'Product Variants',
            'baseFilters' => [
                'product_id' => $this->product->id
            ],
            'subtitle' => 'All variants for this product',
            'search_placeholder' => 'Search variants by SKU, color, size...',
            'export' => true,
            'per_page_options' => [5, 10, 25, 50],
            'searchable' => ['sku', 'color', 'size'],
            'sortable_columns' => ['sku', 'color', 'size', 'status'],
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
                    'key' => 'sku',
                    'label' => 'SKU',
                    'type' => 'text',
                    'font' => 'font-mono text-sm',
                    'sortable' => true
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
            'default_sort' => [
                'column' => 'sku',
                'direction' => 'asc'
            ],
            'header_actions' => [
                [
                    'label' => 'Add Variant',
                    'href' => route('products.variants.create') . '?product=' . $this->product->id,
                    'icon' => 'plus'
                ]
            ],
            'empty_title' => 'No variants yet',
            'empty_description' => 'Create your first product variant to get started.',
            'empty_action' => [
                'label' => 'Add First Variant',
                'href' => route('products.variants.create') . '?product=' . $this->product->id,
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

    public function render()
    {
        return view('livewire.products.product-variants-list');
    }
}