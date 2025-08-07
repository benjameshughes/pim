<?php

namespace App\Livewire\Pim\Products\Variants;

use App\Models\ProductVariant;
use App\Models\Product;
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
class VariantIndex extends Component
{
    use InteractsWithTable, WithPagination;

    public ?Product $product = null;

    protected $listeners = [
        'refreshList' => '$refresh'
    ];

    /**
     * Configure the table (FilamentPHP-style).
     */
    public function table(Table $table): Table
    {
        $query = ProductVariant::query();
        
        // If we have a product, filter variants to only that product
        if ($this->product) {
            $query->where('product_id', $this->product->id);
        }

        return $table
            ->query($query)
            ->title($this->product ? 'Product Variants' : 'Variant Management')
            ->subtitle($this->product ? 'Manage variants for ' . $this->product->name : 'Manage individual product variants across your catalog')
            ->searchable(['sku', 'color', 'size', 'product.name'])
            ->with(['product'])
            ->withCount(['barcodes'])
            ->columns([
                Column::make('sku')
                    ->label('Variant SKU')
                    ->sortable()
                    ->font('font-mono'),

                Column::make('product.name')
                    ->label('Product Name')
                    ->sortable(),

                Column::make('color')
                    ->label('Color')
                    ->sortable(),

                Column::make('size')
                    ->label('Size')
                    ->sortable(),

                Column::make('barcodes_count')
                    ->label('# Barcodes')
                    ->sortable(),

                Column::make('status')
                    ->label('Status')
                    ->badge([
                        'active' => 'bg-green-100 text-green-800',
                        'inactive' => 'bg-gray-100 text-gray-800',
                        'discontinued' => 'bg-red-100 text-red-800',
                    ])
                    ->sortable(),
                    
                Column::make('created_at')
                    ->label('Created')
                    ->sortable(),
            ])
            ->filters(array_filter([
                // Only show product filter if we're not viewing a specific product
                !$this->product ? SelectFilter::make('product_id')
                    ->label('Product')
                    ->options(Product::pluck('name', 'id')->toArray())
                    ->placeholder('All Products') : null,

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'discontinued' => 'Discontinued',
                    ])
                    ->placeholder('All Statuses'),

                Filter::make('has_barcodes')
                    ->label('With Barcodes Only')
                    ->toggle()
                    ->query(function ($query, $value) {
                        if ($value) {
                            $query->has('barcodes');
                        }
                    }),

                Filter::make('recent')
                    ->label('Created Recently')
                    ->toggle()
                    ->query(function ($query, $value) {
                        if ($value) {
                            $query->where('created_at', '>=', now()->subDays(7));
                        }
                    }),
            ]))
            ->headerActions([
                HeaderAction::make('create')
                    ->label('Create Variant')
                    ->icon('plus')
                    ->color('primary')
                    ->route('products.variants.create' . ($this->product ? '?product=' . $this->product->id : '')),

                HeaderAction::make('bulk_import')
                    ->label('Bulk Import')
                    ->icon('upload')
                    ->color('secondary')
                    ->route('products.variants.import'),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('eye')
                    ->route('products.variants.show'),

                Action::make('edit')
                    ->label('Edit')
                    ->icon('pencil')
                    ->route('products.variants.edit'),

                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('copy')
                    ->color('secondary')
                    ->action(function (ProductVariant $record) {
                        $newVariant = $record->replicate();
                        $newVariant->sku = $record->sku . '-copy-' . time();
                        $newVariant->save();
                        session()->flash('success', 'Variant duplicated successfully.');
                    }),

                Action::make('delete')
                    ->label('Delete')
                    ->icon('trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ProductVariant $record) {
                        $record->delete();
                        session()->flash('success', 'Variant deleted successfully.');
                    }),
            ])
            ->bulkActions([
                BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon('trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $this->deleteVariants($records);
                    }),

                BulkAction::make('activate')
                    ->label('Activate Selected')
                    ->icon('check')
                    ->color('success')
                    ->action(function ($records) {
                        $this->updateVariantStatus($records, 'active');
                    }),

                BulkAction::make('deactivate')
                    ->label('Deactivate Selected')
                    ->icon('x')
                    ->color('warning')
                    ->action(function ($records) {
                        $this->updateVariantStatus($records, 'inactive');
                    }),

                BulkAction::make('discontinue')
                    ->label('Discontinue Selected')
                    ->icon('ban')
                    ->color('danger')
                    ->action(function ($records) {
                        $this->updateVariantStatus($records, 'discontinued');
                    }),
            ]);
    }

    private function deleteVariants(array $ids): void
    {
        ProductVariant::whereIn('id', $ids)->delete();
        session()->flash('success', count($ids) . ' variants deleted successfully.');
    }

    private function updateVariantStatus(array $ids, string $status): void
    {
        ProductVariant::whereIn('id', $ids)->update(['status' => $status]);
        session()->flash('success', count($ids) . ' variants updated to ' . $status . '.');
    }

    public function render()
    {
        return view('livewire.pim.products.variants.variant-index');
    }
}