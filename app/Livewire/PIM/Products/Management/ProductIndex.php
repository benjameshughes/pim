<?php

namespace App\Livewire\Pim\Products\Management;

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
class ProductIndex extends Component
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
            ->model(Product::class)
            ->title('Product Catalog')
            ->subtitle('Manage your product catalog with advanced filtering and bulk operations')
            ->searchable(['name', 'parent_sku', 'description'])
            ->columns([
                Column::make('name')
                    ->label('Product Name')
                    ->sortable(),

                Column::make('parent_sku')
                    ->label('SKU')
                    ->sortable()
                    ->font('font-mono'),

                Column::make('status')
                    ->label('Status')
                    ->badge([
                        'active' => 'bg-green-100 text-green-800',
                        'inactive' => 'bg-red-100 text-red-800',
                        'draft' => 'bg-yellow-100 text-yellow-800',
                    ])
                    ->sortable(),
                    
                Column::make('created_at')
                    ->label('Created')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive', 
                        'draft' => 'Draft',
                    ])
                    ->placeholder('All Statuses'),
                    
                Filter::make('created_recently')
                    ->label('Created Recently')
                    ->toggle()
                    ->query(function ($query, $value) {
                        if ($value) {
                            $query->where('created_at', '>=', now()->subDays(30));
                        }
                    }),
            ])
            ->headerActions([
                HeaderAction::make('create')
                    ->label('Create Product')
                    ->icon('plus')
                    ->color('primary'),
                    
                HeaderAction::make('import')
                    ->label('Import Products')
                    ->icon('upload')
                    ->color('secondary'),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('eye')
                    ->url(fn (Product $record) => "/product/{$record->id}/variants"),
                    
                Action::make('edit')
                    ->label('Edit')
                    ->icon('pencil'),
                    
                Action::make('delete')
                    ->label('Delete')
                    ->icon('trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Product $record) {
                        $record->delete();
                        session()->flash('success', 'Product deleted successfully.');
                    }),
            ])
            ->bulkActions([
                BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon('trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        Product::whereIn('id', $records)->delete();
                        session()->flash('success', count($records) . ' products deleted successfully.');
                    }),
                    
                BulkAction::make('activate')
                    ->label('Activate Selected')
                    ->icon('check')
                    ->color('success')
                    ->action(function ($records) {
                        Product::whereIn('id', $records)->update(['status' => 'active']);
                        session()->flash('success', count($records) . ' products activated.');
                    }),
                    
                BulkAction::make('deactivate')
                    ->label('Deactivate Selected')
                    ->icon('x')
                    ->color('warning')
                    ->action(function ($records) {
                        Product::whereIn('id', $records)->update(['status' => 'inactive']);
                        session()->flash('success', count($records) . ' products deactivated.');
                    }),
            ]);
    }

    public function render()
    {
        return view('livewire.pim.products.management.product-index');
    }
}