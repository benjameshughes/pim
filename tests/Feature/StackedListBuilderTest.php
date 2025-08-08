<?php

use App\Models\Product;
use App\StackedList\Actions\Action;
use App\StackedList\Actions\BulkAction;
use App\StackedList\Columns\Column;
use App\StackedList\Filters\Filter;
use App\StackedList\StackedListBuilder;

beforeEach(function () {
    $this->builder = new StackedListBuilder;
});

it('follows the builder pattern with method chaining', function () {
    $result = $this->builder
        ->model(Product::class)
        ->title('Products')
        ->subtitle('Manage your products')
        ->searchPlaceholder('Search products...')
        ->searchable(['name', 'sku'])
        ->sortableColumns(['name', 'sku', 'created_at'])
        ->defaultSort('created_at', 'desc')
        ->with(['variants'])
        ->withCount(['variants'])
        ->perPageOptions([10, 25, 50])
        ->export(true);

    expect($result)->toBeInstanceOf(StackedListBuilder::class);
    expect($result)->toBe($this->builder); // Same instance (chaining)
});

it('can set sortable columns explicitly', function () {
    $this->builder->sortableColumns(['name', 'sku', 'status']);

    $array = $this->builder->toArray();

    expect($array['sortable_columns'])->toBe(['name', 'sku', 'status']);
});

it('can set default sort column and direction', function () {
    $this->builder->defaultSort('created_at', 'desc');

    $array = $this->builder->toArray();

    expect($array['default_sort'])->toBe([
        'column' => 'created_at',
        'direction' => 'desc',
    ]);
});

it('extracts sortable columns from column definitions when not explicitly set', function () {
    $columns = [
        Column::make('name')->sortable(),
        Column::make('sku')->sortable(),
        Column::make('description'), // Not sortable
    ];

    $this->builder->columns($columns);

    $array = $this->builder->toArray();

    expect($array['sortable_columns'])->toBe(['name', 'sku']);
});

it('uses explicitly set sortable columns over auto-extracted ones', function () {
    // Set explicit sortable columns first
    $this->builder->sortableColumns(['custom1', 'custom2']);

    // Then set columns with sortable definitions
    $columns = [
        Column::make('name')->sortable(),
        Column::make('sku')->sortable(),
    ];
    $this->builder->columns($columns);

    $array = $this->builder->toArray();

    // Should keep the explicitly set columns
    expect($array['sortable_columns'])->toBe(['custom1', 'custom2']);
});

it('converts to array format correctly', function () {
    $this->builder
        ->model(Product::class)
        ->title('Products')
        ->subtitle('Product management')
        ->searchPlaceholder('Search...')
        ->searchable(['name', 'sku'])
        ->sortableColumns(['name', 'created_at'])
        ->defaultSort('created_at', 'desc')
        ->with(['category'])
        ->withCount(['variants'])
        ->perPageOptions([5, 10, 25])
        ->export(true);

    $array = $this->builder->toArray();

    expect($array)->toHaveKeys([
        'title',
        'subtitle',
        'search_placeholder',
        'searchable',
        'sortable_columns',
        'default_sort',
        'with',
        'withCount',
        'per_page_options',
        'export',
    ]);

    expect($array['title'])->toBe('Products');
    expect($array['searchable'])->toBe(['name', 'sku']);
    expect($array['sortable_columns'])->toBe(['name', 'created_at']);
    expect($array['default_sort']['column'])->toBe('created_at');
    expect($array['default_sort']['direction'])->toBe('desc');
});

it('filters null values from the array output', function () {
    $this->builder
        ->title('Products')
        ->searchable(['name']);

    $array = $this->builder->toArray();

    // These should not be in the array since they're null
    expect($array)->not->toHaveKey('subtitle');
    expect($array)->not->toHaveKey('default_sort');

    // These should be in the array
    expect($array)->toHaveKey('title');
    expect($array)->toHaveKey('searchable');
});

describe('Filter Builder Integration', function () {
    it('can add filters with column specification', function () {
        $filter = Filter::select('status')
            ->label('Status')
            ->column('product_status')
            ->option('active', 'Active')
            ->option('inactive', 'Inactive');

        $this->builder->filters([$filter]);

        $array = $this->builder->toArray();

        expect($array['filters'])->toHaveKey('status');
        expect($array['filters']['status']['column'])->toBe('product_status');
        expect($array['filters']['status']['options'])->toBe([
            'active' => 'Active',
            'inactive' => 'Inactive',
        ]);
    });

    it('can load filter options from model', function () {
        // Create some test products
        Product::factory()->create(['name' => 'Product A']);
        Product::factory()->create(['name' => 'Product B']);

        $filter = Filter::select('product_id')
            ->label('Product')
            ->optionsFromModel(Product::class, 'name', 'id', ['name']);

        $filterArray = $filter->toArray();

        expect($filterArray['options'])->toHaveCount(2);
        expect($filterArray['options'])->toHaveKeys([1, 2]);
    });
});

describe('Action Builder Integration', function () {
    it('creates bulk actions with proper chaining', function () {
        $action = BulkAction::make('delete')
            ->label('Delete Selected')
            ->icon('trash')
            ->danger()
            ->requiresConfirmation('Delete Items?', 'This cannot be undone.');

        expect($action)->toBeInstanceOf(BulkAction::class);

        $array = $action->toArray();
        expect($array['key'])->toBe('delete');
        expect($array['label'])->toBe('Delete Selected');
        expect($array['icon'])->toBe('trash');
        expect($array['variant'])->toBe('danger');
        expect($array['requiresConfirmation'])->toBeTrue();
    });

    it('creates row actions with navigation', function () {
        $action = Action::make('edit')
            ->label('Edit')
            ->route('products.edit')
            ->navigate(true);

        $array = $action->toArray();
        expect($array['route'])->toBe('products.edit');
        expect($array['navigate'])->toBeTrue();
    });
});

it('supports empty state configuration', function () {
    $this->builder->emptyState(
        'No products found',
        'Create your first product to get started.'
    );

    $array = $this->builder->toArray();

    expect($array['empty_title'])->toBe('No products found');
    expect($array['empty_description'])->toBe('Create your first product to get started.');
});
