<?php

namespace App\Livewire\Pim\Barcodes;

use App\Models\Barcode;
use App\Models\ProductVariant;
use App\Table\Table;
use App\Table\Column;
use App\Table\Concerns\InteractsWithTable;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class BarcodeIndex extends Component
{
    use InteractsWithTable;
    
    public $parentProductsOnly = false;

    protected $listeners = [
        'refreshList' => '$refresh'
    ];
    
    /**
     * Configure the table (FilamentPHP-style).
     */
    public function table(Table $table): Table
    {
        return $table
            ->model(Barcode::class)
            ->title('Barcode Manager')
            ->subtitle('Manage product barcodes and generate new ones')
            ->with(['productVariant.product'])
            ->columns([
                Column::make('barcode')
                    ->label('Barcode')
                    ->sortable()
                    ->font('font-mono'),

                Column::make('productVariant.product.name')
                    ->label('Product')
                    ->sortable(),

                Column::make('barcode_type')
                    ->label('Type')
                    ->badge([
                        'EAN13' => 'bg-blue-100 text-blue-800',
                        'CODE128' => 'bg-green-100 text-green-800',
                        'UPC' => 'bg-purple-100 text-purple-800',
                    ])
                    ->sortable(),
            ]);
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
