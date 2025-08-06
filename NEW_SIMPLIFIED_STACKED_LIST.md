# New Simplified StackedList Architecture

This is the **new, clean approach** you wanted - much simpler and more direct!

## Core Concept

✅ **Pass in the model class**  
✅ **Use concerns for all functionality**  
✅ **Work directly with `$this->model->query()->where(...)`**  
✅ **Simple, intuitive, clean**

## Architecture

### 1. One Main Concern: `InteractsWithStackedList`
Does all the heavy lifting:
- Query building with `$this->model->query()`
- Filtering, searching, sorting
- Pagination, bulk actions
- All the logic lives here

### 2. Simple Contract: `HasStackedList`
Just two methods:
```php
interface HasStackedList
{
    public function handleBulkAction(string $action, array $selectedIds): void;
    public function getStackedListConfig(): array;
}
```

### 3. Dead Simple Component Usage

```php
<?php

class MyListComponent extends Component implements HasStackedList
{
    use InteractsWithStackedList;

    public function mount()
    {
        // That's it! Pass model and config
        $this->mountStackedList(MyModel::class, $this->getStackedListConfig());
    }

    public function getStackedListConfig(): array
    {
        return [
            'title' => 'My Items',
            'searchable' => ['name', 'description'],
            'columns' => [...],
            'bulk_actions' => [...],
        ];
    }

    public function handleBulkAction(string $action, array $selectedIds): void
    {
        // Handle your bulk actions
        match($action) {
            'delete' => MyModel::whereIn('id', $selectedIds)->delete(),
            'activate' => MyModel::whereIn('id', $selectedIds)->update(['status' => 'active']),
        };
    }
}
```

### 4. Simple Blade Template
```blade
<livewire:components.simple-stacked-list :config="$this->getStackedListConfig()" />
```

## How It Works

1. **Concern does everything**: `InteractsWithStackedList` handles queries, filtering, pagination
2. **Model injection**: Pass any model class, concern works with `$this->model->query()`
3. **Configuration-driven**: Everything defined in simple config array
4. **Delegate pattern**: Simple StackedList component delegates all calls to parent

## Example: Complete Working Component

See `SimpleProductIndex` - it's **25 lines of actual logic**:

```php
class SimpleProductIndex extends Component implements HasStackedList
{
    use InteractsWithStackedList;

    public function mount()
    {
        $this->mountStackedList(Product::class, $this->getStackedListConfig());
    }

    public function getStackedListConfig(): array
    {
        return [
            'title' => 'Products',
            'searchable' => ['name', 'description', 'parent_sku'],
            'filters' => ['status' => [...]],
            'columns' => [...],
            'bulk_actions' => [...],
            'withCount' => ['variants']
        ];
    }

    public function handleBulkAction(string $action, array $selectedIds): void
    {
        match($action) {
            'delete' => Product::whereIn('id', $selectedIds)->delete(),
            'activate' => Product::whereIn('id', $selectedIds)->update(['status' => 'active']),
        };
    }
}
```

**That's it!** No complex contracts, no action classes, no inheritance chains.

## Configuration Options

### Basic Config
```php
[
    'title' => 'My List',
    'subtitle' => 'Manage items',
    'searchable' => ['name', 'email', 'user.name'], // Simple array
    'with' => ['user', 'category'], // Relationships to load
    'withCount' => ['orders'], // Counts to load
    'baseFilters' => ['active' => true], // Always applied filters
]
```

### Filters
```php
'filters' => [
    'status' => [
        'type' => 'select',
        'label' => 'Status', 
        'column' => 'status',
        'options' => ['active' => 'Active', 'inactive' => 'Inactive']
    ]
]
```

### Columns
```php
'columns' => [
    ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
    ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'badges' => [...]],
    ['key' => 'actions', 'label' => 'Actions', 'type' => 'actions', 'actions' => [...]]
]
```

## Benefits

✅ **Ultra Simple**: Component is just config + bulk action handling  
✅ **Direct**: Work directly with your models via `$this->model->query()`  
✅ **Reusable**: One concern works with any model  
✅ **Clean**: No complex inheritance or action classes  
✅ **Intuitive**: Feels natural, like how you'd write it manually  

## Test It

Visit `/products/simple` to see it in action!

This is exactly what you wanted - **pass in the model, use concerns, work directly with queries**. Much cleaner than the complex contract/action system!