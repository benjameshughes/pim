<?php

namespace App\StackedList\Lists;

use App\StackedList\Actions\Action;
use App\StackedList\Actions\BulkAction;
use App\StackedList\Columns\ActionsColumn;
use App\StackedList\Columns\BadgeColumn;
use App\StackedList\Columns\TextColumn;
use App\StackedList\Filters\SelectFilter;
use App\StackedList\StackedList;

class ProductStackedList extends StackedList
{
    public function configure(): static
    {
        return $this
            ->title('Products')
            ->subtitle('Manage your product catalog')
            ->searchPlaceholder('Search products...')
            ->exportable()
            ->emptyState(
                'No products found',
                'Get started by creating your first product.',
                [
                    'label' => 'Create Product',
                    'href' => route('products.create'),
                    'icon' => 'plus',
                    'variant' => 'primary'
                ]
            )
            ->headerActions([
                Action::make('create')
                    ->label('Create Product')
                    ->icon('plus')
                    ->primary()
                    ->route('products.create'),

                Action::make('import')
                    ->label('Import Data')
                    ->icon('upload')
                    ->outline()
                    ->route('products.import')
            ])
            ->columns([
                TextColumn::make('name')
                    ->label('Product Name')
                    ->medium()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('variants_count')
                    ->label('Variants')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->status(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->sortable(),

                ActionsColumn::make('actions')
                    ->label('Actions')
                    ->actions([
                        Action::make('view')
                            ->label('View')
                            ->icon('eye')
                            ->route('products.show'),

                        Action::make('edit')
                            ->label('Edit')
                            ->icon('pencil')
                            ->route('products.edit'),

                        Action::make('variants')
                            ->label('Variants')
                            ->icon('layers')
                            ->route('products.variants.index')
                            ->outline()
                    ])
            ])
            ->bulkActions([
                BulkAction::activate()
                    ->key('activate'),

                BulkAction::deactivate()
                    ->key('deactivate'),

                BulkAction::delete()
                    ->key('delete')
                    ->requiresConfirmation(
                        'Delete Products',
                        'Are you sure you want to delete the selected products? This will also delete all associated variants.'
                    ),

                BulkAction::export()
                    ->key('export')
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'draft' => 'Draft'
                    ])
            ]);
    }
}