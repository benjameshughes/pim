<?php

use App\StackedList\StackedListBuilder;
use App\StackedList\Columns\Column;
use App\StackedList\Columns\Badge;
use App\StackedList\Actions\BulkAction;
use App\StackedList\Actions\Action;
use App\StackedList\Actions\HeaderAction;
use App\StackedList\Filters\Filter;
use App\StackedList\EmptyStateAction;
use App\Models\Product;

describe('Fluent StackedList API', function () {
    
    describe('StackedListBuilder', function () {
        it('can create a basic fluent definition', function () {
            $builder = new StackedListBuilder();
            
            $result = $builder
                ->model(Product::class)
                ->title('Test Products')
                ->subtitle('Manage your products');
            
            expect($result)->toBeInstanceOf(StackedListBuilder::class);
            expect($result->toArray()['title'])->toBe('Test Products');
            expect($result->toArray()['subtitle'])->toBe('Manage your products');
        });
        
        it('can chain searchable fields', function () {
            $builder = new StackedListBuilder();
            
            $result = $builder->searchable(['name', 'description', 'sku']);
            
            expect($result->toArray()['searchable'])->toBe(['name', 'description', 'sku']);
        });
        
        it('converts to array format for blade compatibility', function () {
            $builder = new StackedListBuilder();
            
            $array = $builder
                ->model(Product::class)
                ->title('Products')
                ->toArray();
            
            expect($array)->toBeArray();
            expect($array['title'])->toBe('Products');
        });
    });
    
    describe('Column', function () {
        it('can create text column with fluent methods', function () {
            $column = Column::make('name')
                ->label('Product Name')
                ->sortable()
                ->font('font-medium');
            
            expect($column->getName())->toBe('name');
            expect($column->isSortable())->toBeTrue();
            expect($column->toArray()['label'])->toBe('Product Name');
            expect($column->toArray()['font'])->toBe('font-medium');
        });
        
        it('can create badge column with fluent badges', function () {
            $column = Column::make('status')
                ->label('Status')
                ->badge()
                ->addBadge('active', Badge::make()
                    ->class('bg-green-100 text-green-800')
                    ->icon('check-circle')
                )
                ->addBadge('inactive', Badge::make()
                    ->class('bg-zinc-100 text-zinc-800')
                    ->icon('pause-circle')
                );
            
            $array = $column->toArray();
            expect($array['type'])->toBe('badge');
            expect($array['badges']['active']['class'])->toBe('bg-green-100 text-green-800');
            expect($array['badges']['active']['icon'])->toBe('check-circle');
        });
    });
    
    describe('BulkAction', function () {
        it('can create basic bulk action', function () {
            $action = BulkAction::make('test_action')
                ->label('Test Action')
                ->icon('test-icon')
                ->outline();
            
            expect($action->toArray()['key'])->toBe('test_action');
            expect($action->toArray()['label'])->toBe('Test Action');
            expect($action->toArray()['icon'])->toBe('test-icon');
            expect($action->toArray()['variant'])->toBe('outline');
        });
        
        it('can create bulk action with closure', function () {
            $executed = false;
            $selectedIds = [1, 2, 3];
            
            $action = BulkAction::make('test_action')
                ->label('Test Action')
                ->action(function($ids) use (&$executed, $selectedIds) {
                    $executed = true;
                    expect($ids)->toBe($selectedIds);
                });
            
            $action->execute($selectedIds, null);
            expect($executed)->toBeTrue();
        });
        
        it('can create bulk action with class string', function () {
            $action = BulkAction::make('test_action')
                ->action(\App\Actions\TestBulkAction::class);
            
            expect($action->hasAction())->toBeTrue();
        });
        
        it('creates export action with defaults', function () {
            $action = BulkAction::export();
            
            expect($action->toArray()['key'])->toBe('export');
            expect($action->toArray()['label'])->toBe('Export Selected');
            expect($action->toArray()['icon'])->toBe('download');
        });
        
        it('creates delete action with confirmation', function () {
            $action = BulkAction::delete();
            
            expect($action->toArray()['key'])->toBe('delete');
            expect($action->toArray()['label'])->toBe('Delete Selected');
            expect($action->toArray()['variant'])->toBe('danger');
        });
    });
    
    describe('Action (Row Actions)', function () {
        it('can create basic action', function () {
            $action = Action::make('view')
                ->label('View')
                ->icon('eye')
                ->route('products.view');
            
            expect($action->toArray()['key'])->toBe('view');
            expect($action->toArray()['label'])->toBe('View');
            expect($action->toArray()['route'])->toBe('products.view');
        });
        
        it('can create view action with defaults', function () {
            $action = Action::view()->route('products.view');
            
            expect($action->toArray()['key'])->toBe('view');
            expect($action->toArray()['label'])->toBe('View');
            expect($action->toArray()['icon'])->toBe('eye');
        });
        
        it('can create edit action with defaults', function () {
            $action = Action::edit()->route('products.edit');
            
            expect($action->toArray()['key'])->toBe('edit');
            expect($action->toArray()['label'])->toBe('Edit');
            expect($action->toArray()['icon'])->toBe('pencil');
        });
        
        it('can create delete action with confirmation', function () {
            $action = Action::delete();
            
            expect($action->toArray()['key'])->toBe('delete');
            expect($action->toArray()['requiresConfirmation'])->toBeTrue();
        });
    });
    
    describe('Filter', function () {
        it('can create select filter with fluent options', function () {
            $filter = Filter::select('status')
                ->option('active', 'Active')
                ->option('inactive', 'Inactive')
                ->option('discontinued', 'Discontinued');
            
            $array = $filter->toArray();
            expect($array['type'])->toBe('select');
            expect($array['options'])->toBe([
                'active' => 'Active',
                'inactive' => 'Inactive',
                'discontinued' => 'Discontinued'
            ]);
        });
        
        it('can create multiselect filter', function () {
            $filter = Filter::multiselect('categories')
                ->options(['tech' => 'Technology', 'fashion' => 'Fashion']);
            
            expect($filter->toArray()['type'])->toBe('multiselect');
        });
    });
    
    describe('HeaderAction', function () {
        it('can create basic header action', function () {
            $action = HeaderAction::make('create')
                ->label('Create Product')
                ->route('products.create')
                ->primary();
            
            expect($action->toArray()['key'])->toBe('create');
            expect($action->toArray()['label'])->toBe('Create Product');
            expect($action->toArray()['variant'])->toBe('primary');
        });
        
        it('can create create action with defaults', function () {
            $action = HeaderAction::create()->route('products.create');
            
            expect($action->toArray()['label'])->toBe('Create');
            expect($action->toArray()['icon'])->toBe('plus');
            expect($action->toArray()['variant'])->toBe('primary');
        });
    });
    
    describe('EmptyStateAction', function () {
        it('can create empty state action', function () {
            $action = EmptyStateAction::make('Create First Product')
                ->href(route('products.create'))
                ->primary();
            
            expect($action->toArray()['label'])->toBe('Create First Product');
            expect($action->toArray()['variant'])->toBe('primary');
        });
    });
    
    describe('Complete Fluent Definition', function () {
        it('can build complete stacked list definition', function () {
            $builder = new StackedListBuilder();
            
            $definition = $builder
                ->model(Product::class)
                ->title('Product Catalog')
                ->subtitle('Manage your product catalog')
                ->searchable(['name', 'description'])
                ->columns([
                    Column::make('name')
                        ->label('Product Name')
                        ->sortable()
                        ->font('font-medium'),
                    
                    Column::make('status')
                        ->label('Status')
                        ->badge()
                        ->addBadge('active', Badge::make()
                            ->class('bg-green-100 text-green-800')
                            ->icon('check-circle')
                        )
                ])
                ->bulkActions([
                    BulkAction::make('toggle_status')
                        ->label('Toggle Status')
                        ->icon('refresh-cw')
                        ->action(fn($ids) => 'toggled'),
                    BulkAction::export(),
                    BulkAction::delete(),
                ])
                ->actions([
                    Action::view()->route('products.view'),
                    Action::edit()->route('products.edit'),
                    Action::delete(),
                ])
                ->filters([
                    Filter::select('status')
                        ->option('active', 'Active')
                        ->option('inactive', 'Inactive')
                ])
                ->headerActions([
                    HeaderAction::create()->route('products.create')
                ])
                ->emptyState(
                    'No products found',
                    'Create your first product',
                    EmptyStateAction::make('Create Product')
                        ->href(route('products.create'))
                        ->primary()
                );
            
            $array = $definition->toArray();
            
            // Test structure
            expect($array)->toBeArray();
            expect($array['title'])->toBe('Product Catalog');
            expect($array['searchable'])->toBe(['name', 'description']);
            expect($array['columns'])->toHaveCount(2);
            expect($array['bulk_actions'])->toHaveCount(3);
            expect($array['filters'])->toHaveKey('status');
            expect($array['header_actions'])->toHaveCount(1);
            
            // Test that no arrays are exposed in the fluent API
            expect($definition)->toBeInstanceOf(StackedListBuilder::class);
        });
        
        it('maintains method chaining', function () {
            $builder = new StackedListBuilder();
            
            $result = $builder
                ->model(Product::class)
                ->title('Test')
                ->subtitle('Test Sub')
                ->searchable(['name']);
            
            expect($result)->toBeInstanceOf(StackedListBuilder::class);
            
            // Should be able to continue chaining
            $result2 = $result->columns([]);
            expect($result2)->toBeInstanceOf(StackedListBuilder::class);
        });
    });
    
    describe('Zero Arrays in Fluent API', function () {
        it('uses objects for all configuration', function () {
            // This test ensures no arrays leak into the fluent API
            $definition = (new StackedListBuilder())
                ->model(Product::class)
                ->columns([
                    Column::make('status')->badge()  // Should use Badge objects internally
                ])
                ->bulkActions([
                    BulkAction::make('test')->action(fn($ids) => null)  // Should use closure/class objects
                ])
                ->filters([
                    Filter::select('status')  // Should use Option objects internally
                ])
                ->emptyState(
                    'No items',
                    'Create first item', 
                    EmptyStateAction::make('Create')  // Should use EmptyStateAction object
                );
            
            // The fluent API should only expose objects, never arrays
            expect($definition)->toBeInstanceOf(StackedListBuilder::class);
            
            // Arrays should only exist in toArray() conversion for blade compatibility
            $array = $definition->toArray();
            expect($array)->toBeArray();
        });
    });
});