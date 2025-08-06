<?php

namespace App\StackedList\Lists;

use App\StackedList\Actions\Action;
use App\StackedList\Actions\BulkAction;
use App\StackedList\Columns\ActionsColumn;
use App\StackedList\Columns\BadgeColumn;
use App\StackedList\Columns\TextColumn;
use App\StackedList\StackedList;

class ProductVariantsListStackedList extends StackedList
{
    protected string $productId;

    public function __construct(string $productId = null)
    {
        $this->productId = $productId;
    }

    public static function make(string $productId = null): static
    {
        return new static($productId);
    }

    public function configure(): static
    {
        return $this
            ->title('Product Variants')
            ->subtitle('Variants for this specific product')
            ->searchPlaceholder('Search variants...')
            ->exportable()
            ->emptyState(
                'No variants found',
                'This product has no variants. Create some to manage different options.',
                [
                    'label' => 'Create Variant',
                    'href' => $this->productId ? route('products.variants.create', ['product' => $this->productId]) : '#',
                    'icon' => 'plus',
                    'variant' => 'primary'
                ]
            )
            ->headerActions([
                Action::make('create')
                    ->label('Create Variant')
                    ->icon('plus')
                    ->primary()
                    ->route('products.variants.create')
            ])
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->monospace()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('color')
                    ->label('Color')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('dimensions')
                    ->label('Size')
                    ->sortable()
                    ->searchable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->status(),

                TextColumn::make('stock_level')
                    ->label('Stock')
                    ->sortable(),

                TextColumn::make('pricing_count')
                    ->label('Pricing Rules')
                    ->sortable(false),

                TextColumn::make('barcodes_count')
                    ->label('Barcodes')
                    ->sortable(false),

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
                            ->route('products.variants.edit'),

                        Action::make('duplicate')
                            ->label('Duplicate')
                            ->icon('copy')
                            ->outline()
                    ])
            ])
            ->bulkActions([
                BulkAction::make('generateBarcodes')
                    ->label('Generate Barcodes')
                    ->icon('barcode')
                    ->outline()
                    ->key('generate_barcodes'),

                BulkAction::make('bulkPricing')
                    ->label('Bulk Pricing')
                    ->icon('dollar-sign')
                    ->outline()
                    ->key('bulk_pricing'),

                BulkAction::activate()
                    ->key('activate'),

                BulkAction::deactivate()
                    ->key('deactivate'),

                BulkAction::delete()
                    ->key('delete')
            ]);
    }
}