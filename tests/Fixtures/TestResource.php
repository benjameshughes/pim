<?php

namespace Tests\Fixtures;

use App\Atom\Resources\Resource;
use App\Atom\Core\Tables\Table;
use App\Atom\Core\Tables\Column;
use App\Atom\Core\Tables\Action;
use App\Atom\Core\Tables\BulkAction;
use App\Atom\Core\Tables\HeaderAction;
use App\Atom\Core\Tables\Filter;

/**
 * Test Resource for Atom Framework Testing
 */
class TestResource extends Resource
{
    protected static ?string $model = TestModel::class;
    protected static ?string $navigationLabel = 'Test Models';
    protected static ?string $navigationIcon = 'test-icon';
    protected static ?string $navigationGroup = 'Testing';
    protected static ?string $modelLabel = 'test model';
    protected static ?string $pluralModelLabel = 'test models';
    protected static ?string $recordTitleAttribute = 'name';

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
                
                Column::make('email')
                    ->label('Email')
                    ->searchable(),
                    
                Column::make('active')
                    ->label('Active')
                    ->sortable(),
                    
                Column::make('priority')
                    ->label('Priority')
                    ->sortable(),
                    
                Column::make('created_at')
                    ->label('Created')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('active')
                    ->label('Active Only')
                    ->query(fn ($query, $value) => $query->where('active', true)),
            ])
            ->headerActions([
                HeaderAction::make('create')
                    ->label('Create Test Model')
                    ->icon('plus')
                    ->color('primary'),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('eye')
                    ->url(fn ($record) => static::getUrl('view', ['record' => $record])),
                    
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
                        session()->flash('success', 'Test model deleted successfully.');
                    }),
            ])
            ->bulkActions([
                BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon('trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        TestModel::whereIn('id', $records)->delete();
                        session()->flash('success', count($records) . ' test models deleted successfully.');
                    }),
            ]);
    }
}