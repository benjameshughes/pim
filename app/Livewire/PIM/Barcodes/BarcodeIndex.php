<?php

namespace App\Livewire\Pim\Barcodes;

use App\Models\Barcode;
use App\Models\ProductVariant;
use App\Atom\Tables\Table;
use App\Atom\Tables\Column;
use App\Atom\Tables\Filter;
use App\Atom\Tables\SelectFilter;
use App\Atom\Tables\Action;
use App\Atom\Tables\HeaderAction;
use App\Atom\Tables\BulkAction;
use App\Atom\Tables\Concerns\InteractsWithTable;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class BarcodeIndex extends Component
{
    use InteractsWithTable, WithPagination;
    
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
            ->searchable(['barcode', 'productVariant.product.name', 'productVariant.sku'])
            ->with(['productVariant.product'])
            ->columns([
                Column::make('barcode')
                    ->label('Barcode')
                    ->sortable()
                    ->font('font-mono'),

                Column::make('productVariant.product.name')
                    ->label('Product')
                    ->sortable(),

                Column::make('productVariant.sku')
                    ->label('Variant SKU')
                    ->sortable()
                    ->font('font-mono'),

                Column::make('barcode_type')
                    ->label('Type')
                    ->badge([
                        'EAN13' => 'bg-blue-100 text-blue-800',
                        'CODE128' => 'bg-green-100 text-green-800',
                        'UPC' => 'bg-purple-100 text-purple-800',
                    ])
                    ->sortable(),

                Column::make('is_primary')
                    ->label('Primary')
                    ->badge([
                        true => 'bg-green-100 text-green-800',
                        false => 'bg-gray-100 text-gray-800',
                    ]),
                    
                Column::make('created_at')
                    ->label('Created')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('barcode_type')
                    ->label('Barcode Type')
                    ->options([
                        'EAN13' => 'EAN13',
                        'CODE128' => 'CODE128',
                        'UPC' => 'UPC',
                    ])
                    ->placeholder('All Types'),
                    
                Filter::make('is_primary')
                    ->label('Primary Only')
                    ->toggle()
                    ->query(function ($query, $value) {
                        if ($value) {
                            $query->where('is_primary', true);
                        }
                    }),
                    
                Filter::make('has_variant')
                    ->label('With Variants Only')
                    ->toggle()
                    ->query(function ($query, $value) {
                        if ($value) {
                            $query->whereNotNull('product_variant_id');
                        }
                    }),
            ])
            ->headerActions([
                HeaderAction::make('generate_bulk')
                    ->label('Generate Barcodes')
                    ->icon('plus')
                    ->color('primary')
                    ->action(function () {
                        // Open barcode generation modal
                        $this->dispatch('open-barcode-generator');
                    }),
                    
                HeaderAction::make('import')
                    ->label('Import Barcodes')
                    ->icon('upload')
                    ->color('secondary'),
            ])
            ->actions([
                Action::make('set_primary')
                    ->label('Set Primary')
                    ->icon('star')
                    ->color('warning')
                    ->visible(fn ($record) => !$record->is_primary)
                    ->action(function (Barcode $record) {
                        $this->setPrimary($record->id);
                    }),
                    
                Action::make('generate_new')
                    ->label('Generate New')
                    ->icon('plus')
                    ->color('success')
                    ->action(function (Barcode $record) {
                        $this->generateBarcode($record->product_variant_id, $record->barcode_type);
                    }),
                    
                Action::make('delete')
                    ->label('Delete')
                    ->icon('trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Barcode $record) {
                        $this->deleteBarcode($record->id);
                    }),
            ])
            ->bulkActions([
                BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon('trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $this->bulkDeleteBarcodes($records);
                    }),
                    
                BulkAction::make('set_type')
                    ->label('Change Type')
                    ->icon('edit')
                    ->color('warning')
                    ->action(function ($records) {
                        // This could open a modal to select new barcode type
                        session()->flash('info', 'Type change feature coming soon.');
                    }),
            ]);
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
        session()->flash('success', 'Selected barcodes have been deleted.');
    }

    public function render()
    {
        return view('livewire.pim.barcodes.barcode-index', [
            'barcodeTypes' => Barcode::BARCODE_TYPES,
        ]);
    }
}
