# Complete Table Features Implementation Plan
*FilamentPHP-Inspired Architecture Enhancement*

## 🎯 **Implementation Strategy**

Build complete table functionality incrementally while maintaining our clean 3-file architecture:
- `app/Table/Table.php` - Core table with all features
- `app/Table/Column.php` - Enhanced column system  
- `app/Table/Concerns/InteractsWithTable.php` - Full Livewire integration

## 📋 **Phase 1: Livewire Properties & State Management**

### **Add Core Table Properties** (Following FilamentPHP patterns)
```php
// In InteractsWithTable trait
#[Url] public ?string $tableSearch = '';
#[Url] public ?array $tableFilters = null;
#[Url] public ?string $tableSortColumn = null;
#[Url] public ?string $tableSortDirection = null;
public array $selectedTableRecords = [];
public int $tableRecordsPerPage = 10;

// Property methods
public function getTableSearch(): ?string
public function resetTableFilters(): void
public function resetTableSearch(): void
public function resetTable(): void
```

### **Table State Methods** (Protected overridables)
```php
protected function getTableRecordsPerPage(): int { return 10; }
protected function getDefaultTableSortColumn(): ?string { return null; }
protected function getDefaultTableSortDirection(): ?string { return 'asc'; }
protected function shouldPersistTableSearchInSession(): bool { return false; }
```

## 📋 **Phase 2: Enhanced Query Building System**

### **Search Implementation**
```php
// In Table class
protected function applySearchToTableQuery(Builder $query): void
{
    $search = $this->livewire->getTableSearch();
    
    if (empty($search) || empty($this->searchableColumns)) {
        return;
    }
    
    $query->where(function (Builder $subQuery) use ($search) {
        foreach ($this->searchableColumns as $field) {
            if (str_contains($field, '.')) {
                // Relationship search
                [$relation, $column] = explode('.', $field, 2);
                $subQuery->orWhereHas($relation, function (Builder $relationQuery) use ($column, $search) {
                    $relationQuery->where($column, 'like', "%{$search}%");
                });
            } else {
                // Direct column search
                $subQuery->orWhere($field, 'like', "%{$search}%");
            }
        }
    });
}
```

### **Filter System Architecture**
```php
// Create Filter classes
class Filter {
    public static function make(string $key): static
    public function query(Closure $callback): static
    public function default(mixed $value = true): static
    public function toggle(): static
}

class SelectFilter extends Filter {
    public function options(array $options): static
    public function multiple(): static
    public function relationship(string $name, string $column): static
}
```

### **Enhanced Query Builder**
```php
public function getQuery(): Builder
{
    $query = $this->getBaseQuery();
    
    // Apply search
    $this->applySearchToTableQuery($query);
    
    // Apply filters  
    $this->applyFiltersToTableQuery($query);
    
    // Apply sorting
    $this->applySortingToTableQuery($query);
    
    // Apply relationships
    $this->applyRelationshipsToTableQuery($query);
    
    return $query;
}
```

## 📋 **Phase 3: Action System Implementation**

### **Action Architecture**
```php
// Base Action class
class Action {
    public static function make(string $key): static
    public function label(string $label): static
    public function icon(string $icon): static
    public function url(string|Closure $url): static
    public function route(string $route): static
    public function action(Closure $callback): static
    public function requiresConfirmation(): static
    public function button(): static
    public function link(): static
}

// Specialized Actions
class HeaderAction extends Action { /* Toolbar actions */ }
class BulkAction extends Action { /* Multi-select actions */ }
```

### **Action Integration**
```php
// In Table class
public function headerActions(array $actions): static
public function actions(array $actions): static  
public function bulkActions(array $actions): static

// In InteractsWithTable
public function executeTableAction(string $action, $record = null): void
public function executeTableBulkAction(string $action): void
public function executeTableHeaderAction(string $action): void
```

## 📋 **Phase 4: Advanced Column Features**

### **Enhanced Column System**
```php
class Column {
    // Existing methods +
    public function badge(array $config = []): static
    public function searchable(bool $global = true, bool $individual = false): static
    public function toggleable(bool $default = true): static
    public function wrap(): static
    public function limit(int $length): static
    public function tooltip(): static
    
    // Action columns
    public function actions(array $actions): static { $this->type = 'actions'; }
}

// Specialized columns
class TextColumn extends Column { /* Enhanced text rendering */ }
class BadgeColumn extends Column { /* Status badges */ }
class ActionsColumn extends Column { /* Row actions */ }
```

## 📋 **Phase 5: Advanced Table Features**

### **Pagination Enhancement**
```php
// In Table class  
public function paginated(array $options = [10, 25, 50, 100]): static
public function defaultPaginationPageOption(int $count): static
public function simplePaginated(): static

// Per-page selector in template
<select wire:model.live="tableRecordsPerPage">
    @foreach($table->getPaginationPageOptions() as $option)
        <option value="{{ $option }}">{{ $option }}</option>
    @endforeach
</select>
```

### **Empty States & Loading**
```php
// In Table class
public function emptyStateHeading(string $heading): static
public function emptyStateDescription(string $description): static  
public function emptyStateIcon(string $icon): static
public function emptyStateActions(array $actions): static

// Loading states
public function poll(string $interval = '10s'): static
public function deferLoading(): static
```

### **Sorting System**
```php
// In InteractsWithTable
public function sortTableBy(string $column): void {
    if ($this->tableSortColumn === $column) {
        $this->tableSortDirection = $this->tableSortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->tableSortColumn = $column;
        $this->tableSortDirection = 'asc';
    }
}
```

## 📋 **Phase 6: Comprehensive Template System**

### **Template Structure**
```blade
<div class="table-container">
    {{-- Header Section --}}
    <div class="table-header">
        <div class="title-section">{{ $config['title'] }}</div>
        <div class="header-actions">@include('table.header-actions')</div>
    </div>
    
    {{-- Controls Section --}}
    <div class="table-controls">
        <div class="search-filters">
            @include('table.search')
            @include('table.filters')
        </div>
        <div class="pagination-controls">
            @include('table.per-page-selector')
        </div>
    </div>
    
    {{-- Bulk Actions Bar (when items selected) --}}
    @include('table.bulk-actions-bar')
    
    {{-- Data Table --}}
    @if($data->isNotEmpty())
        @include('table.data-table')
        @include('table.pagination')
    @else
        @include('table.empty-state')
    @endif
    
    {{-- Loading States --}}
    @include('table.loading-states')
</div>
```

### **Modular Template Components**
- `table.search` - Debounced search input
- `table.filters` - Dynamic filter rendering
- `table.header-actions` - Toolbar actions
- `table.data-table` - Main table with sorting
- `table.bulk-actions-bar` - Floating bulk actions
- `table.empty-state` - No data state
- `table.pagination` - Enhanced pagination

## 📋 **Phase 7: Real-World Integration Features**

### **Advanced Search**
```php
// Multi-column search
->searchable(['name', 'email', 'profile.company.name'])

// Custom search logic
protected function applySearchToTableQuery(Builder $query): Builder {
    if ($search = $this->getTableSearch()) {
        // Scout integration for large datasets
        $query->whereIn('id', Product::search($search)->keys());
    }
    return $query;
}
```

### **Complex Filters**
```php
->filters([
    Filter::make('is_featured')->toggle()->default(),
    
    SelectFilter::make('status')
        ->options(['active' => 'Active', 'inactive' => 'Inactive'])
        ->multiple(),
        
    SelectFilter::make('category')
        ->relationship('category', 'name')
        ->searchable()
        ->preload(),
])
```

### **Rich Actions**
```php
->headerActions([
    HeaderAction::make('create')
        ->label('Create Product')
        ->icon('plus')
        ->button()
        ->route('products.create'),
])
->actions([
    Action::make('edit')
        ->icon('pencil')  
        ->route('products.edit'),
    Action::make('delete')
        ->icon('trash')
        ->requiresConfirmation()
        ->action(fn($record) => $record->delete()),
])
->bulkActions([
    BulkAction::make('delete')
        ->label('Delete Selected')
        ->icon('trash')
        ->requiresConfirmation()
        ->action(fn($records) => $records->each->delete()),
])
```

## 🎯 **Success Criteria**

After implementation, developers should be able to create fully-featured tables like this:

```php
public function table(Table $table): Table
{
    return $table
        ->model(Product::class)
        ->title('Product Catalog')
        ->subtitle('Manage your complete product inventory')
        ->searchable(['name', 'parent_sku', 'description'])
        ->columns([
            Column::make('name')->sortable()->searchable(),
            Column::make('status')->badge([
                'active' => 'bg-green-100 text-green-800',
                'inactive' => 'bg-red-100 text-red-800',
            ])->sortable(),
            Column::make('created_at')->sortable(),
            Column::make('actions')->actions([
                Action::make('edit')->route('products.edit'),
                Action::make('delete')->action(fn($r) => $r->delete()),
            ]),
        ])
        ->filters([
            Filter::make('active_only')->toggle(),
            SelectFilter::make('category')->relationship('category', 'name'),
        ])
        ->headerActions([
            HeaderAction::make('create')->route('products.create')->button(),
        ])
        ->bulkActions([
            BulkAction::make('delete')->requiresConfirmation(),
        ])
        ->defaultSort('created_at', 'desc')
        ->paginated([10, 25, 50, 100]);
}
```

## 🚀 **Implementation Order**

1. ✅ **Phase 1**: Livewire properties & state management
2. ✅ **Phase 2**: Enhanced query building (search + filters)
3. ✅ **Phase 3**: Action system (header, row, bulk)
4. ✅ **Phase 4**: Advanced column features
5. ✅ **Phase 5**: Pagination & sorting
6. ✅ **Phase 6**: Comprehensive template
7. ✅ **Phase 7**: Real-world testing & refinement

Each phase builds incrementally while maintaining the clean architecture and ensuring everything works perfectly before moving to the next phase.

**Target**: Complete FilamentPHP-quality table system with maximum functionality and minimal developer complexity! 🎯