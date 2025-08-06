<?php

namespace App\Livewire\PIM\Barcodes;

use App\Models\Barcode;
use App\Models\ProductVariant;
use App\Contracts\HasStackedList;
use App\Concerns\HasStackedListBehavior;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class BarcodeIndex extends Component implements HasStackedList
{
    use HasStackedListBehavior;

    public $parentProductsOnly = false;

    protected $listeners = [
        'refreshList' => '$refresh'
    ];

    public function mount()
    {
        $this->initializeStackedList(Barcode::class, $this->getStackedListConfig());
    }

    public function getStackedListConfig(): array
    {
        return [
            'title' => 'Barcodes',
            'subtitle' => 'Manage product barcodes and identifiers',
            'search_placeholder' => 'Search barcodes...',
            'export' => true,
            'per_page_options' => [25, 50, 100, 150, 200],
            'searchable' => ['barcode'],
            'sortable_columns' => ['barcode', 'barcode_type', 'is_primary', 'created_at'],
            'filters' => [
                'product_id' => [
                    'type' => 'select',
                    'label' => 'Product',
                    'relation' => 'productVariant',
                    'column' => 'product_id',
                    'options' => \App\Models\Product::orderBy('name')->limit(100)->pluck('name', 'id')->toArray()
                ],
                'barcode_type' => [
                    'type' => 'select',
                    'label' => 'Type',
                    'column' => 'barcode_type',
                    'options' => Barcode::BARCODE_TYPES
                ]
            ],
            'columns' => [
                [
                    'key' => 'barcode',
                    'label' => 'Barcode',
                    'type' => 'text',
                    'font' => 'font-mono text-sm',
                    'sortable' => true
                ],
                [
                    'key' => 'productVariant.product.name',
                    'label' => 'Product',
                    'type' => 'text',
                    'font' => 'font-medium',
                    'sortable' => false
                ],
                [
                    'key' => 'productVariant.sku',
                    'label' => 'Variant SKU',
                    'type' => 'text',
                    'font' => 'font-mono text-xs',
                    'sortable' => false
                ],
                [
                    'key' => 'barcode_type',
                    'label' => 'Type',
                    'type' => 'badge',
                    'sortable' => true,
                    'badges' => [
                        'CODE128' => [
                            'label' => 'Code 128',
                            'class' => 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/20 dark:text-blue-300 dark:border-blue-800',
                            'icon' => 'barcode'
                        ],
                        'EAN13' => [
                            'label' => 'EAN-13',
                            'class' => 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800',
                            'icon' => 'barcode'
                        ],
                        'UPC' => [
                            'label' => 'UPC',
                            'class' => 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-900/20 dark:text-purple-300 dark:border-purple-800',
                            'icon' => 'barcode'
                        ],
                        'default' => [
                            'label' => 'Other',
                            'class' => 'bg-zinc-100 text-zinc-800 border-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:border-zinc-600',
                            'icon' => 'barcode'
                        ]
                    ]
                ],
                [
                    'key' => 'is_primary',
                    'label' => 'Primary',
                    'type' => 'badge',
                    'sortable' => true,
                    'badges' => [
                        '1' => [
                            'label' => 'Primary',
                            'class' => 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800',
                            'icon' => 'star'
                        ],
                        '0' => [
                            'label' => 'Secondary',
                            'class' => 'bg-zinc-100 text-zinc-800 border-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:border-zinc-600',
                            'icon' => 'minus'
                        ]
                    ]
                ],
                [
                    'key' => 'actions',
                    'label' => 'Actions',
                    'type' => 'actions',
                    'actions' => [
                        [
                            'label' => 'Primary',
                            'icon' => 'star',
                            'variant' => 'ghost',
                            'method' => 'setPrimary',
                            'title' => 'Set as primary barcode'
                        ],
                        [
                            'label' => 'Delete',
                            'icon' => 'trash-2',
                            'variant' => 'ghost',
                            'method' => 'deleteBarcode',
                            'title' => 'Delete barcode'
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
                ]
            ],
            'with' => ['productVariant.product'],
            'default_sort' => [
                'column' => 'created_at',
                'direction' => 'desc'
            ],
            'header_actions' => [
                [
                    'label' => 'Generate Barcodes',
                    'href' => '#',
                    'icon' => 'plus'
                ]
            ],
            'empty_title' => 'No barcodes found',
            'empty_description' => 'Generate your first barcode to get started.',
            'empty_action' => [
                'label' => 'Generate Barcode',
                'href' => '#',
                'icon' => 'plus'
            ]
        ];
    }

    public function updatingParentProductsOnly()
    {
        $this->resetPage();
    }

    public function generateBarcode($variantId, $type = 'CODE128')
    {
        $variant = ProductVariant::findOrFail($variantId);
        
        // Check if variant already has a barcode of this type
        $existingBarcode = $variant->barcodes()->where('barcode_type', $type)->first();
        
        if ($existingBarcode) {
            session()->flash('error', 'This variant already has a ' . Barcode::BARCODE_TYPES[$type] . ' barcode.');
            return;
        }

        // Generate new barcode
        $barcodeValue = Barcode::generateRandomBarcode($type);
        
        // Create barcode record
        Barcode::create([
            'product_variant_id' => $variantId,
            'barcode' => $barcodeValue,
            'barcode_type' => $type,
            'is_primary' => $variant->barcodes()->count() === 0, // First barcode is primary
        ]);

        session()->flash('success', 'Barcode generated successfully!');
    }

    public function setPrimary($barcodeId)
    {
        $barcode = Barcode::findOrFail($barcodeId);
        
        // Set all other barcodes for this variant to non-primary
        $barcode->productVariant->barcodes()->update(['is_primary' => false]);
        
        // Set this barcode as primary
        $barcode->update(['is_primary' => true]);
        
        session()->flash('success', 'Primary barcode updated!');
    }

    public function deleteBarcode($barcodeId)
    {
        $barcode = Barcode::findOrFail($barcodeId);
        $variant = $barcode->productVariant;
        
        $barcode->delete();
        
        // If this was the primary barcode, make the first remaining barcode primary
        if ($barcode->is_primary && $variant->barcodes()->count() > 0) {
            $variant->barcodes()->first()->update(['is_primary' => true]);
        }
        
        session()->flash('success', 'Barcode deleted successfully!');
    }

    public function bulkDeleteBarcodes($barcodeIds)
    {
        Barcode::whereIn('id', $barcodeIds)->delete();
        session()->flash('message', 'Selected barcodes have been deleted.');
    }

    public function handleBulkAction(string $action, array $selectedIds): void
    {
        match($action) {
            'delete' => $this->bulkDeleteBarcodes($selectedIds),
            default => null
        };
    }


    public function render()
    {
        // Load variants for generation section when needed
        $variants = collect();
        if (!$this->stackedListSearch && empty($this->stackedListFilters)) {
            $variants = ProductVariant::with('product')
                ->limit(100)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return view('livewire.pim.barcodes.barcode-index', [
            'variants' => $variants,
            'barcodeTypes' => Barcode::BARCODE_TYPES,
        ]);
    }
}
