# StackedList Reusable Component Architecture

This document outlines the new FilamentPHP-inspired architecture for making the StackedList component truly reusable across the application.

## Architecture Overview

The new architecture follows FilamentPHP's patterns with three key components:

### 1. Contracts (`App\Contracts\StackedListConfigurable`)
Defines the interface that components must implement to use StackedList:

```php
interface StackedListConfigurable
{
    public function getStackedListConfig(): array;
    public function handleStackedListBulkAction(string $action, array $selectedIds): void;
    public function getStackedListData(): array;
    public function getStackedListModel(): string;
}
```

### 2. Concerns/Traits (`App\Concerns\HasStackedList`)
Provides shared functionality for components using StackedList:
- Configuration merging
- Event handling
- Common utility methods

### 3. Action System (`App\Actions\StackedList\*`)
Reusable action classes for bulk operations:
- `StackedListAction` - Abstract base class
- `DeleteProductsAction` - Handles deletion with archiving
- `UpdateProductStatusAction` - Handles status updates

## Usage Guide

### Implementing in a New Component

```php
<?php

namespace App\Livewire\MyModule;

use App\Contracts\StackedListConfigurable;
use App\Concerns\HasStackedList;
use App\Actions\StackedList\DeleteAction;
use Livewire\Component;

class MyListComponent extends Component implements StackedListConfigurable
{
    use HasStackedList;
    
    protected array $stackedListActions;

    public function mount()
    {
        $this->stackedListActions = [
            new DeleteAction(),
            // Add more actions as needed
        ];
    }

    public function getStackedListConfig(): array
    {
        return [
            'title' => 'My Items',
            'subtitle' => 'Manage your items',
            'searchable_fields' => [
                'name' => ['column' => 'name'],
                'description' => ['column' => 'description']
            ],
            'sortable_columns' => ['name', 'created_at'],
            'columns' => [
                [
                    'key' => 'name',
                    'label' => 'Name',
                    'type' => 'text',
                    'sortable' => true
                ],
                // More columns...
            ],
            'bulk_actions' => collect($this->stackedListActions)
                ->map(fn($action) => $action->toArray())
                ->toArray(),
        ];
    }

    public function handleStackedListBulkAction(string $action, array $selectedIds): void
    {
        $actionHandler = collect($this->stackedListActions)
            ->firstWhere(fn($handler) => $handler->getKey() === $action);

        if ($actionHandler) {
            $actionHandler->handle($selectedIds, $this->getActionContext($action));
            $this->refreshStackedList();
        }
    }

    public function getStackedListData(): array
    {
        return [
            'with_count' => ['related_items'],
            // Additional query modifications
        ];
    }

    public function getStackedListModel(): string
    {
        return MyModel::class;
    }

    public function render()
    {
        return view('livewire.my-module.my-list', [
            'stackedListConfig' => $this->getCompleteStackedListConfig()
        ]);
    }
}
```

### Blade Template

```blade
<livewire:components.stacked-list 
    :config="$stackedListConfig"
    :wire:key="$this->getStackedListKey()"
/>
```

## Configuration Options

### Core Configuration
- `title` - List title
- `subtitle` - List subtitle
- `model` - Model class (set automatically via contract)
- `export` - Enable CSV/Excel export
- `search_placeholder` - Search input placeholder

### Fields Configuration
```php
'searchable_fields' => [
    'name' => ['column' => 'name'],
    'description' => ['column' => 'description'],
    'user_name' => [
        'column' => 'name',
        'relation' => 'user'  // For relationship searches
    ]
],

'sortable_columns' => ['name', 'created_at', 'status'],
```

### Filters Configuration
```php
'filters' => [
    'status' => [
        'type' => 'select',
        'label' => 'Status',
        'column' => 'status',
        'options' => [
            'active' => 'Active',
            'inactive' => 'Inactive'
        ]
    ]
]
```

### Columns Configuration
```php
'columns' => [
    [
        'key' => 'name',
        'label' => 'Name',
        'type' => 'text',
        'sortable' => true
    ],
    [
        'key' => 'status',
        'label' => 'Status',
        'type' => 'badge',
        'badges' => [
            'active' => [
                'label' => 'Active',
                'class' => 'bg-green-100 text-green-800',
                'icon' => 'check-circle'
            ]
        ]
    ],
    [
        'key' => 'actions',
        'label' => 'Actions',
        'type' => 'actions',
        'actions' => [
            [
                'label' => 'Edit',
                'route' => 'my.edit',
                'icon' => 'pencil'
            ]
        ]
    ]
]
```

## Creating Custom Actions

```php
<?php

namespace App\Actions\StackedList;

class MyCustomAction extends StackedListAction
{
    public function __construct()
    {
        parent::__construct(
            key: 'my_action',
            label: 'My Action',
            icon: 'star',
            variant: 'primary'
        );
    }

    public function handle(array $selectedIds, array $context = []): void
    {
        // Your action logic here
        MyModel::whereIn('id', $selectedIds)->update(['custom_field' => 'value']);
        
        session()->flash('message', count($selectedIds) . ' items processed.');
    }
}
```

## Backward Compatibility

The StackedList component maintains backward compatibility with the old configuration format:

### Old Format (Still Supported)
```php
'searchable' => ['name', 'description'],
'sortable' => ['name', 'created_at'],
'with' => ['user', 'category']
```

### New Format (Recommended)
```php
'searchable_fields' => [
    'name' => ['column' => 'name'],
    'description' => ['column' => 'description']
],
'sortable_columns' => ['name', 'created_at'],
'with_relations' => ['user', 'category']
```

## Migration Guide

### Existing Components
1. Implement `StackedListConfigurable` interface
2. Add `HasStackedList` trait
3. Move configuration to `getStackedListConfig()` method
4. Convert bulk action handling to action classes
5. Update blade template to use `$stackedListConfig`

### Benefits of Migration
- **Type Safety**: Interface ensures all required methods are implemented
- **Reusability**: Action classes can be shared between components
- **Maintainability**: Centralized configuration and consistent patterns
- **Testability**: Actions can be unit tested independently
- **Extensibility**: Easy to add new features and column types

## Best Practices

1. **Always implement the interface** - Don't just use the trait without the contract
2. **Create reusable actions** - Share common actions between components
3. **Use proper configuration format** - Follow the new format for better maintainability
4. **Handle errors gracefully** - Actions should handle exceptions and provide user feedback
5. **Add wire:key** - Always use `getStackedListKey()` for proper Livewire updates
6. **Test thoroughly** - Unit test your custom actions and integration test your components

This architecture makes the StackedList component truly reusable while maintaining FilamentPHP's architectural principles.