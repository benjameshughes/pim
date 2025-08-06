<?php

namespace App\StackedList\Lists;

use App\StackedList\Actions\Action;
use App\StackedList\Actions\BulkAction;
use App\StackedList\Columns\ActionsColumn;
use App\StackedList\Columns\BadgeColumn;
use App\StackedList\Columns\TextColumn;
use App\StackedList\Filters\SelectFilter;
use App\StackedList\StackedList;

class VariantStackedList extends StackedList
{
    public function configure(): static
    {
        return $this
            ->title('Product Variants')
            ->subtitle('Manage individual product variants')
            ->searchPlaceholder('Search variants...')
            ->exportable()
            ->emptyState(
                'No variants found',
                'Create variants to manage different options of your products.',
                [
                    'label' => 'Create Variant',
                    'href' => route('products.variants.create'),
                    'icon' => 'plus',
                    'variant' => 'primary'
                ]
            )
            ->headerActions([
                Action::make('create')
                    ->label('Create Variant')
                    ->icon('plus')
                    ->primary()
                    ->route('products.variants.create'),

                Action::make('import')
                    ->label('Import Data')
                    ->icon('upload')
                    ->outline()
                    ->route('products.import')
            ])
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->medium()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->monospace()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('color')
                    ->label('Color')
                    ->sortable(),

                TextColumn::make('dimensions')
                    ->label('Size')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->status(),

                TextColumn::make('stock_level')
                    ->label('Stock')
                    ->sortable(),

                ActionsColumn::make('actions')
                    ->label('Actions')
                    ->actions([
                        Action::make('view')
                            ->label('View')
                            ->icon('eye')
                            ->route('products.variants.view'),

                        Action::make('edit')
                            ->label('Edit')
                            ->icon('pencil')
                            ->route('products.variants.edit')
                    ])
            ])
            ->bulkActions([
                BulkAction::activate()
                    ->key('activate'),

                BulkAction::deactivate()
                    ->key('deactivate'),

                BulkAction::make('assignBarcodes')
                    ->label('Assign Barcodes')
                    ->icon('barcode')
                    ->outline()
                    ->key('assign_barcodes'),

                BulkAction::delete()
                    ->key('delete')
                    ->requiresConfirmation(
                        'Delete Variants',
                        'Are you sure you want to delete the selected variants?'
                    )
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'out_of_stock' => 'Out of Stock'
                    ]),

                SelectFilter::make('color')
                    ->label('Color')
                    ->optionsFromModel(\App\Models\ProductVariant::class, 'color', 'color'),

                SelectFilter::make('product_id')
                    ->label('Product')
                    ->optionsFromModel(\App\Models\Product::class, 'id', 'name')
            ]);
    }
}