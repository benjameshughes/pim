<?php

namespace App\Atom\Resources;

use App\Models\Product;
use App\Atom\Navigation\NavigationBuilder;
use App\Atom\Tables\Column;
use App\Atom\Tables\Filter;
use App\Atom\Tables\SelectFilter;
use App\Atom\Tables\Action;
use App\Atom\Tables\HeaderAction;
use App\Atom\Tables\BulkAction;
use App\Atom\Tables\Table;
use App\UI\Toasts\Facades\Toast;

/**
 * Product Resource
 * 
 * Pure PHP resource class that defines how products should be managed
 * across different systems (Livewire tables, API endpoints, etc.).
 */
class ProductResource extends Resource
{
    /**
     * The resource's associated Eloquent model.
     */
    protected static ?string $model = Product::class;
    
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
    protected static ?string $navigationGroup = 'Product Management';
    
    /**
     * The resource navigation sort order.
     */
    protected static ?int $navigationSort = 1;
    
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
     * Custom navigation configuration.
     */
    public static function getNavigation(): ?NavigationBuilder
    {
        return NavigationBuilder::resource(static::class)
            ->label('Products')
            ->icon('cube')
            ->group('Product Management')
            ->sort(1)
            ->badge(fn() => Product::count())
            ->subNavigation([
                'variants' => 'Variants',
                'pricing' => 'Pricing',
                'images' => 'Images',
            ])
            ->relationships([
                'variants' => \App\Resources\ProductVariantResource::class,
            ]);
    }
    
    /**
     * Get sub-navigation items for product records.
     */
    public static function getRecordSubNavigation($record = null): array
    {
        if (!$record) {
            return [];
        }
        
        return [
            'view' => 'Overview',
            'variants' => 'Variants (' . $record->variants()->count() . ')',
            'pricing' => 'Pricing',
            'images' => 'Images (' . $record->images()->count() . ')',
        ];
    }
    
    /**
     * Get available pages for this resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => [
                'component' => 'resources.list-records',
                'route' => '/',
            ],
            'create' => [
                'component' => 'resources.create-record',
                'route' => '/create',
            ],
            'view' => [
                'component' => 'resources.view-record',
                'route' => '/{record}',
            ],
            'edit' => [
                'component' => 'resources.edit-record',
                'route' => '/{record}/edit',
            ],
            'variants' => [
                'component' => 'resources.manage-variants',
                'route' => '/{record}/variants',
            ],
            'pricing' => [
                'component' => 'resources.manage-pricing',
                'route' => '/{record}/pricing',
            ],
            'images' => [
                'component' => 'resources.manage-images',
                'route' => '/{record}/images',
            ],
        ];
    }
    
    /**
     * Configure the resource table.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->title('Product Catalog')
            ->subtitle('Manage your product catalog with advanced filtering and bulk operations')
            ->searchable(['name', 'parent_sku', 'description'])
            ->columns([
                Column::make('name')
                    ->label('Product Name')
                    ->sortable(),

                Column::make('parent_sku')
                    ->label('SKU')
                    ->sortable()
                    ->font('font-mono'),

                Column::make('status')
                    ->label('Status')
                    ->badge([
                        'active' => 'bg-green-100 text-green-800',
                        'inactive' => 'bg-red-100 text-red-800',
                        'draft' => 'bg-yellow-100 text-yellow-800',
                    ])
                    ->sortable(),
                    
                Column::make('created_at')
                    ->label('Created')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive', 
                        'draft' => 'Draft',
                    ])
                    ->placeholder('All Statuses'),
                    
                Filter::make('created_recently')
                    ->label('Created Recently')
                    ->toggle()
                    ->query(function ($query, $value) {
                        if ($value) {
                            $query->where('created_at', '>=', now()->subDays(30));
                        }
                    }),
            ])
            ->headerActions([
                HeaderAction::make('create')
                    ->label('Create Product')
                    ->icon('plus')
                    ->color('primary'),
                    
                HeaderAction::make('import')
                    ->label('Import Products')
                    ->icon('upload')
                    ->color('secondary'),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('eye')
                    ->url(fn (Product $record) => route('resources.products.view', ['record' => $record])),
                    
                Action::make('edit')
                    ->label('Edit')
                    ->icon('pencil')
                    ->url(fn (Product $record) => route('resources.products.edit', ['record' => $record])),
                    
                Action::make('delete')
                    ->label('Delete')
                    ->icon('trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Product $record, $livewire = null) {
                        $productName = $record->name;
                        $record->delete();
                        
                        // Use simplified Livewire notifications
                        if ($livewire && method_exists($livewire, 'notifySuccess')) {
                            $livewire->notifySuccess('Product Deleted', "Product '{$productName}' was deleted successfully.");
                        }
                    }),
            ])
            ->bulkActions([
                BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon('trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        Product::whereIn('id', $records)->delete();
                        Toast::success('Products Deleted', count($records) . ' products were deleted successfully.')
                            ->persist()
                            ->send();
                    }),
                    
                BulkAction::make('activate')
                    ->label('Activate Selected')
                    ->icon('check')
                    ->color('success')
                    ->action(function ($records) {
                        Product::whereIn('id', $records)->update(['status' => 'active']);
                        Toast::success('Products Activated', count($records) . ' products were activated.')
                            ->persist()
                            ->send();
                    }),
                    
                BulkAction::make('deactivate')
                    ->label('Deactivate Selected')
                    ->icon('x')
                    ->color('warning')
                    ->action(function ($records) {
                        Product::whereIn('id', $records)->update(['status' => 'inactive']);
                        Toast::warning('Products Deactivated', count($records) . ' products were deactivated.')
                            ->persist()
                            ->send();
                    }),
            ]);
    }
}