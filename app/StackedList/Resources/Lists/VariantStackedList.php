<?php

namespace App\StackedList\Resources\Lists;

use App\Models\ProductVariant;
use App\Models\Product;
use App\StackedList\StackedListBuilder;
use App\StackedList\Columns\Column;
use App\StackedList\Columns\Badge;
use App\StackedList\Actions\BulkAction;
use App\StackedList\Actions\Action;
use App\StackedList\Actions\HeaderAction;
use App\StackedList\Filters\Filter;
use App\StackedList\EmptyStateAction;

class VariantStackedList
{
    public static function definition(): StackedListBuilder
    {
        return app('stacked-list')
            ->model(ProductVariant::class)
            ->title('Variant Management')
            ->subtitle('Manage individual product variants across your catalog')
            ->searchPlaceholder('Search variants by SKU, color, size...')
            ->searchable(['sku', 'color', 'size', 'product.name'])
            ->sortableColumns(['sku', 'color', 'size', 'status', 'barcodes_count'])
            ->with(['product'])
            ->withCount(['barcodes'])
            ->perPageOptions([10, 25, 50, 100])
            ->export()
            ->defaultSort('created_at', 'desc')
            ->columns([
                Column::make('sku')
                    ->label('Variant SKU')
                    ->font('font-mono text-sm')
                    ->sortable(),

                Column::make('product.name')
                    ->label('Product Name')
                    ->font('font-medium'),

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
                    ->badge()
                    ->sortable()
                    ->addBadge('active', Badge::make()
                        ->class('bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800')
                        ->icon('check-circle')
                    )
                    ->addBadge('inactive', Badge::make()
                        ->class('bg-zinc-100 text-zinc-800 border-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:border-zinc-600')
                        ->icon('pause-circle')
                    )
                    ->addBadge('discontinued', Badge::make()
                        ->class('bg-red-100 text-red-800 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800')
                        ->icon('x-circle')
                    ),
            ])
            ->bulkActions([
                BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon('trash-2')
                    ->danger()
                    ->action(function($selectedIds) {
                        return app('stacked-list.component')->deleteVariants($selectedIds);
                    }),

                BulkAction::make('activate')
                    ->label('Activate')
                    ->icon('check-circle')
                    ->outline()
                    ->action(function($selectedIds) {
                        return app('stacked-list.component')->updateVariantStatus($selectedIds, 'active');
                    }),
            ])
            ->actions([
                Action::view()->route('products.variants.view'),
                Action::edit()->route('products.variants.edit'),
            ])
            ->filters([
                Filter::select('product_id')
                    ->label('Product')
                    ->column('product_id')
                    ->optionsFromModel(Product::class, 'name', 'id', ['name']),

                Filter::select('status')
                    ->option('active', 'Active')
                    ->option('inactive', 'Inactive')
                    ->option('discontinued', 'Discontinued'),
            ])
            ->headerActions([
                HeaderAction::make('create')
                    ->label('Create Variant')
                    ->route('products.variants.create')
                    ->primary(),
            ])
            ->emptyState(
                'No variants found',
                'Create your first variant to get started.',
                EmptyStateAction::make('Create Variant')
                    ->href(route('products.variants.create'))
                    ->primary()
            );
    }
}