<?php

namespace App\StackedList\Resources\Lists;

use App\Models\Product;
use App\StackedList\StackedListBuilder;
use App\StackedList\Columns\Column;
use App\StackedList\Columns\Badge;
use App\StackedList\Actions\BulkAction;
use App\StackedList\Actions\Action;
use App\StackedList\Actions\HeaderAction;
use App\StackedList\Filters\Filter;
use App\StackedList\EmptyStateAction;

class ProductStackedList
{
    public static function definition(): StackedListBuilder
    {
        return app('stacked-list')
            ->model(Product::class)
            ->title('Product Catalog')
            ->subtitle('Manage your product catalog with advanced filtering and bulk operations')
            ->searchPlaceholder('Search products by name, SKU, or description...')
            ->searchable(['name', 'description', 'parent_sku'])
            ->withCount(['variants'])
            ->columns([
                Column::make('name')
                    ->label('Product Name')
                    ->sortable()
                    ->font('font-medium'),

                Column::make('parent_sku')
                    ->label('SKU')
                    ->sortable()
                    ->font('font-mono text-sm'),

                Column::make('variants_count')
                    ->label('# Variants')
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
                BulkAction::make('update_pricing')
                    ->label('Update Pricing')
                    ->icon('dollar-sign')
                    ->outline()
                    ->action(function($selectedIds) {
                        return app('stacked-list.component')->handleUpdatePricing($selectedIds);
                    }),

                BulkAction::export(),

                BulkAction::make('toggle_status')
                    ->label('Toggle Status')
                    ->icon('refresh-cw')
                    ->outline()
                    ->action(function($selectedIds) {
                        return app('stacked-list.component')->toggleProductStatus($selectedIds);
                    }),

                BulkAction::delete(),
            ])
            ->actions([
                Action::view()->route('products.view'),
                Action::edit()->route('products.product.edit'),
            ])
            ->filters([
                Filter::select('status')
                    ->option('active', 'Active')
                    ->option('inactive', 'Inactive')
                    ->option('discontinued', 'Discontinued'),
            ])
            ->headerActions([
                HeaderAction::create()
                    ->label('Create Product')
                    ->route('products.create')
                    ->primary(),
            ])
            ->emptyState(
                'No products found',
                'Create your first product to get started with your catalog.',
                EmptyStateAction::make('Create Product')
                    ->href(route('products.create'))
                    ->primary()
            );
    }
}