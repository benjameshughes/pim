<?php

namespace App\Resources;

use App\Models\Customer;
use App\Resources\Resource;
use App\Table\Action;
use App\Table\BulkAction;
use App\Table\Column;
use App\Table\Filter;
use App\Table\HeaderAction;
use App\Table\Table;

/**
 * Customer Resource
 * 
 * Resource class for managing Customer records across different systems.
 */
class CustomerResource extends Resource
{
    /**
     * The resource's associated Eloquent model.
     */
    protected static ?string $model = Customer::class;
    
    /**
     * The resource navigation label.
     */
    protected static ?string $navigationLabel = 'Customers';
    
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
    protected static ?string $modelLabel = 'customer';
    
    /**
     * The plural model label.
     */
    protected static ?string $pluralModelLabel = 'customers';
    
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
                    ->label('Create Customer')
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
                        session()->flash('success', 'Customer deleted successfully.');
                    }),
            ])
            ->bulkActions([
                BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon('trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        Customer::whereIn('id', $records)->delete();
                        session()->flash('success', count($records) . ' customers deleted successfully.');
                    }),
            ]);
    }
}