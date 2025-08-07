<?php

namespace App\Atom\Resources;

use App\Atom\Resources\Resource;
use App\Atom\Tables\Action;
use App\Atom\Tables\BulkAction;
use App\Atom\Tables\Column;
use App\Atom\Tables\Filter;
use App\Atom\Tables\HeaderAction;
use App\Atom\Tables\Table;
use App\Models\Product;

/**
 * Product Resource
 * 
 * Resource class for managing Product records across different systems.
 */
class ProductResource extends Resource
{
    /**
     * The resource's associated Eloquent model.
     */
    protected static ?string $model = App\Models\Product::class;
    
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
    protected static ?string $navigationGroup = null;
    
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
            ->columns([
                Column::make('id')
                    ->label('ID')
                    ->sortable(),

                Column::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),

                Column::make('created_at')
                    ->label('Created')
                    ->sortable(),
            ])
            ->filters([
                // Add your filters here
            ])
            ->headerActions([
                HeaderAction::make('create')
                    ->label('Create Product')
                    ->icon('plus')
                    ->color('primary'),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('pencil')
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),
                    
                Action::make('delete')
                    ->label('Delete')
                    ->icon('trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
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
                        App\Models\Product::whereIn('id', $records)->delete();
                        session()->flash('success', count($records) . ' products deleted successfully.');
                    }),
            ]);
    }
}