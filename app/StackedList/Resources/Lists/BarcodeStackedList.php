<?php

namespace App\StackedList\Resources\Lists;

use App\Models\Barcode;
use App\StackedList\Actions\Action;
use App\StackedList\Columns\Column;
use App\StackedList\EmptyStateAction;
use App\StackedList\StackedListBuilder;

class BarcodeStackedList
{
    public static function definition(): StackedListBuilder
    {
        return app('stacked-list')
            ->model(Barcode::class)
            ->title('Barcode manager')
            ->searchable(['barcode', 'productVariant.product.name'])
            ->with(['productVariant.product'])
            ->columns([
                Column::make('barcode')
            ])
            ->actions([
                Action::view()->route('barcode.view')
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