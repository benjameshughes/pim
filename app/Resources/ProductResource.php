<?php

namespace App\Resources;

use App\Models\Product;
use App\Table\Column;
use App\Table\Filter;
use App\Table\SelectFilter;
use App\Table\Action;
use App\Table\HeaderAction;
use App\Table\BulkAction;
use App\Table\Table;

/**
 * Product Resource
 * 
 * Pure PHP resource class that defines how products should be managed
 * across different systems (Livewire tables, API endpoints, etc.).
 */
class ProductResource extends Resource
{
    /**
     * The resource's associated Eloquent model.
     */
    protected static ?string $model = Product::class;
    
    /**
     * The resource navigation label.
     */
    protected static ?string $navigationLabel = 'Products';
    
    /**
     * The resource navigation icon.
     */
    protected static ?string $navigationIcon = 'cube';
    
    /**
     * The resource navigation group.
     */
    protected static ?string $navigationGroup = 'Product Management';
    
    /**
     * The resource navigation sort order.
     */
    protected static ?int $navigationSort = 1;
    
    /**
     * The model label (singular).
     */
    protected static ?string $modelLabel = 'product';
    
    /**
     * The plural model label.
     */
    protected static ?string $pluralModelLabel = 'products';
    
    /**
     * The record title attribute for identification.
     */
    protected static ?string $recordTitleAttribute = 'name';
    
    /**
     * Configure the resource table.
     */
    public static function table(Table $table): Table
    {
        return $table
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
                    ->icon('pencil')
                    ->url(fn (Product $record) => static::getUrl('edit', ['record' => $record])),
                    
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
}