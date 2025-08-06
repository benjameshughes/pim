<?php

namespace App\StackedList\Lists;

use App\Models\Barcode;
use App\Models\Product;
use App\StackedList\Actions\Action;
use App\StackedList\Actions\BulkAction;
use App\StackedList\Columns\ActionsColumn;
use App\StackedList\Columns\BadgeColumn;
use App\StackedList\Columns\TextColumn;
use App\StackedList\Filters\SelectFilter;
use App\StackedList\StackedList;

class BarcodeStackedList extends StackedList
{
    public function configure(): static
    {
        return $this
            ->title('Barcodes')
            ->subtitle('Manage product barcodes and identifiers')
            ->searchPlaceholder('Search barcodes...')
            ->exportable()
            ->emptyState(
                'No barcodes found',
                'No barcodes to display.',
                [
                    'label' => 'Generate Barcodes',
                    'href' => '#',
                    'icon' => 'plus',
                    'variant' => 'primary'
                ]
            )
            ->columns([
                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->monospace()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('productVariant.product.name')
                    ->label('Product')
                    ->medium()
                    ->sortable(false),

                TextColumn::make('productVariant.sku')
                    ->label('Variant SKU')
                    ->monospace()
                    ->extraSmall()
                    ->sortable(false),

                BadgeColumn::make('barcode_type')
                    ->label('Type')
                    ->sortable()
                    ->badge('CODE128', 'Code 128', 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/20 dark:text-blue-300 dark:border-blue-800', 'barcode')
                    ->badge('EAN13', 'EAN-13', 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800', 'barcode')
                    ->badge('UPC', 'UPC', 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-900/20 dark:text-purple-300 dark:border-purple-800', 'barcode')
                    ->default('Other', 'bg-zinc-100 text-zinc-800 border-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:border-zinc-600', 'barcode'),

                BadgeColumn::make('is_primary')
                    ->label('Primary')
                    ->sortable()
                    ->badge('1', 'Primary', 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800', 'star')
                    ->badge('0', 'Secondary', 'bg-zinc-100 text-zinc-800 border-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:border-zinc-600', 'minus'),

                ActionsColumn::make('actions')
                    ->label('Actions')
                    ->actions([
                        Action::make('setPrimary')
                            ->label('Primary')
                            ->icon('star')
                            ->method('setPrimary')
                            ->title('Set as primary barcode'),

                        Action::make('deleteBarcode')
                            ->label('Delete')
                            ->icon('trash-2')
                            ->method('deleteBarcode')
                            ->title('Delete barcode')
                            ->requiresConfirmation(
                                'Delete Barcode',
                                'Are you sure you want to delete this barcode?'
                            )
                    ])
            ])
            ->bulkActions([
                BulkAction::delete()
                    ->key('delete')
                    ->label('Delete Selected')
                    ->requiresConfirmation(
                        'Delete Barcodes',
                        'Are you sure you want to delete the selected barcodes?'
                    ),

                BulkAction::make('setPrimaryBulk')
                    ->label('Set as Primary')
                    ->icon('star')
                    ->outline()
                    ->key('set_primary')
            ])
            ->filters([
                SelectFilter::make('product_id')
                    ->label('Product')
                    ->optionsFromModel(Product::class, 'id', 'name')
                    ->placeholder('Filter by Product'),

                SelectFilter::make('barcode_type')
                    ->label('Type')
                    ->optionsFromConstant(Barcode::class, 'BARCODE_TYPES')
                    ->placeholder('Filter by Type')
            ]);
    }
}