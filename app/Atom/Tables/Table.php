<?php

namespace App\Atom\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Component;

/**
 * FilamentPHP-Inspired Table Class
 * 
 * Core table configuration class that uses Builder pattern with Fluent API.
 * Implements Htmlable interface to enable magic method rendering: {{ $this->table }}
 */
class Table implements Htmlable
{
    protected ?Component $livewire = null;
    protected ?string $model = null;
    protected ?Builder $query = null;
    
    // Table Configuration
    protected string $title = '';
    protected string $subtitle = '';
    protected array $columns = [];
    protected array $searchableColumns = [];
    protected array $filters = [];
    protected array $actions = [];
    protected array $bulkActions = [];
    protected array $headerActions = [];
    protected array $with = [];
    protected array $withCount = [];
    protected int $recordsPerPage = 10;
    protected array $paginationPageOptions = [10, 25, 50, 100];
    
    // Empty state configuration
    protected string $emptyStateHeading = 'No items found';
    protected string $emptyStateDescription = 'No items to display.';
    protected string $emptyStateIcon = '📋';
    
    /**
     * Create a new table instance.
     */
    public static function make(?Component $livewire = null): static
    {
        return new static($livewire);
    }
    
    public function __construct(?Component $livewire = null)
    {
        if ($livewire) {
            $this->livewire = $livewire;
        }
    }
    
    /**
     * Set the Livewire component instance
     */
    public function livewire(Component $livewire): static
    {
        $this->livewire = $livewire;
        return $this;
    }
    
    /**
     * Set the model for the table.
     */
    public function query(Builder $query): static
    {
        $this->query = $query;
        return $this;
    }
    
    /**
     * Set the model class for the table.
     */
    public function model(string $model): static
    {
        $this->model = $model;
        return $this;
    }
    
    /**
     * Set table title.
     */
    public function title(string $title): static
    {
        $this->title = $title;
        return $this;
    }
    
    /**
     * Set table subtitle.
     */
    public function subtitle(string $subtitle): static
    {
        $this->subtitle = $subtitle;
        return $this;
    }
    
    /**
     * Set the columns for the table.
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;
        return $this;
    }
    
    /**
     * Set searchable columns.
     */
    public function searchable(array $columns): static
    {
        $this->searchableColumns = $columns;
        return $this;
    }
    
    /**
     * Set filters for the table.
     */
    public function filters(array $filters): static
    {
        $this->filters = $filters;
        return $this;
    }
    
    /**
     * Set header actions.
     */
    public function headerActions(array $actions): static
    {
        $this->headerActions = $actions;
        return $this;
    }
    
    /**
     * Set row actions.
     */
    public function actions(array $actions): static
    {
        $this->actions = $actions;
        return $this;
    }
    
    /**
     * Set bulk actions.
     */
    public function bulkActions(array $actions): static
    {
        $this->bulkActions = $actions;
        return $this;
    }
    
    /**
     * Set relationships to eager load.
     */
    public function with(array $relationships): static
    {
        $this->with = $relationships;
        return $this;
    }
    
    /**
     * Set relationships to count.
     */
    public function withCount(array $relationships): static
    {
        $this->withCount = $relationships;
        return $this;
    }
    
    /**
     * Set records per page.
     */
    public function recordsPerPage(int $count): static
    {
        $this->recordsPerPage = $count;
        return $this;
    }
    
    /**
     * Set pagination page options.
     */
    public function paginated(array $options = [10, 25, 50, 100]): static
    {
        $this->paginationPageOptions = $options;
        return $this;
    }
    
    /**
     * Set default pagination page option.
     */
    public function defaultPaginationPageOption(int $count): static
    {
        $this->recordsPerPage = $count;
        return $this;
    }
    
    /**
     * Set empty state heading.
     */
    public function emptyStateHeading(string $heading): static
    {
        $this->emptyStateHeading = $heading;
        return $this;
    }
    
    /**
     * Set empty state description.
     */
    public function emptyStateDescription(string $description): static
    {
        $this->emptyStateDescription = $description;
        return $this;
    }
    
    /**
     * Set empty state icon.
     */
    public function emptyStateIcon(string $icon): static
    {
        $this->emptyStateIcon = $icon;
        return $this;
    }
    
    /**
     * Get the base query for the table
     */
    protected function getBaseQuery(): Builder
    {
        if ($this->query) {
            return clone $this->query;
        } elseif ($this->model) {
            return (new $this->model)->newQuery();
        } else {
            throw new \Exception('Table must have either a model or query set');
        }
    }
    
    /**
     * Get the table query with all applied filters, search, etc.
     */
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
    
    /**
     * Apply search to the query
     */
    protected function applySearchToTableQuery(Builder $query): void
    {
        if (!$this->livewire) {
            return;
        }
        
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
    
    /**
     * Apply filters to the query
     */
    protected function applyFiltersToTableQuery(Builder $query): void
    {
        if (!$this->livewire) {
            return;
        }
        
        $activeFilters = $this->livewire->getTableFilters();
        
        foreach ($this->filters as $filter) {
            $filterKey = is_object($filter) ? $filter->getKey() : $filter['key'];
            $value = $activeFilters[$filterKey] ?? null;
            
            // Skip empty values (but allow 0, '0', false)
            if (is_null($value) || $value === '' || (is_array($value) && empty($value))) {
                // Check for default values
                if (is_object($filter) && $filter->hasDefault()) {
                    $value = $filter->getDefault();
                } else {
                    continue;
                }
            }
            
            // Apply the filter
            if (is_object($filter)) {
                $filterQuery = $filter->getQuery();
                if ($filterQuery) {
                    $filterQuery($query, $value);
                }
            } elseif (isset($filter['query']) && is_callable($filter['query'])) {
                $filter['query']($query, $value);
            }
        }
    }
    
    /**
     * Apply sorting to the query
     */
    protected function applySortingToTableQuery(Builder $query): void
    {
        if (!$this->livewire) {
            return;
        }
        
        $sortColumn = $this->livewire->getTableSortColumn();
        $sortDirection = $this->livewire->getTableSortDirection();
        
        if (empty($sortColumn)) {
            return;
        }
        
        if (str_contains($sortColumn, '.')) {
            // Relationship sorting
            [$relation, $column] = explode('.', $sortColumn, 2);
            $query->orderBy($relation . '.' . $column, $sortDirection);
        } else {
            // Direct column sorting
            $query->orderBy($sortColumn, $sortDirection);
        }
    }
    
    /**
     * Apply relationships to the query
     */
    protected function applyRelationshipsToTableQuery(Builder $query): void
    {
        if (!empty($this->with)) {
            $query->with($this->with);
        }
        
        if (!empty($this->withCount)) {
            $query->withCount($this->withCount);
        }
    }
    
    /**
     * Get the data for the table (paginated).
     */
    public function getData()
    {
        $query = $this->getQuery();
        
        $perPage = $this->livewire 
            ? $this->livewire->getTableRecordsPerPage() 
            : $this->recordsPerPage;
            
        return $query->paginate($perPage);
    }
    
    /**
     * Get pagination page options.
     */
    public function getPaginationPageOptions(): array
    {
        return $this->paginationPageOptions;
    }
    
    /**
     * Convert the table configuration to array format.
     */
    public function toArray(): array
    {
        return [
            // Basic configuration
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            
            // Data structure
            'columns' => array_map(function ($column) {
                return is_object($column) && method_exists($column, 'toArray') 
                    ? $column->toArray() 
                    : $column;
            }, $this->columns),
            
            // Search and filtering
            'searchable' => $this->searchableColumns,
            'filters' => array_map(function ($filter) {
                return is_object($filter) && method_exists($filter, 'toArray') 
                    ? $filter->toArray() 
                    : $filter;
            }, $this->filters),
            
            // Actions
            'headerActions' => array_map(function ($action) {
                return is_object($action) && method_exists($action, 'toArray') 
                    ? $action->toArray() 
                    : $action;
            }, $this->headerActions),
            'actions' => array_map(function ($action) {
                return is_object($action) && method_exists($action, 'toArray') 
                    ? $action->toArray() 
                    : $action;
            }, $this->actions),
            'bulkActions' => array_map(function ($action) {
                return is_object($action) && method_exists($action, 'toArray') 
                    ? $action->toArray() 
                    : $action;
            }, $this->bulkActions),
            
            // Pagination
            'recordsPerPage' => $this->recordsPerPage,
            'paginationPageOptions' => $this->paginationPageOptions,
            
            // Empty state
            'emptyStateHeading' => $this->emptyStateHeading,
            'emptyStateDescription' => $this->emptyStateDescription,
            'emptyStateIcon' => $this->emptyStateIcon,
            
            // Relationships
            'with' => $this->with,
            'withCount' => $this->withCount,
            
            // Current state (if Livewire is available)
            'currentSearch' => $this->livewire ? $this->livewire->getTableSearch() : '',
            'currentFilters' => $this->livewire ? $this->livewire->getTableFilters() : [],
            'currentSortColumn' => $this->livewire ? $this->livewire->getTableSortColumn() : '',
            'currentSortDirection' => $this->livewire ? $this->livewire->getTableSortDirection() : 'asc',
            'selectedRecords' => $this->livewire ? $this->livewire->selectedTableRecords : [],
        ];
    }
    
    /**
     * Convert the table to HTML (implements Htmlable interface)
     * This enables {{ $this->table }} to work in Blade templates
     */
    public function toHtml(): string
    {
        return view('components.table')->with([
            'table' => $this,
            'data' => $this->getData(),
        ])->render();
    }
    
    /**
     * Convert the table to string (enables string casting)
     */
    public function __toString(): string
    {
        return $this->toHtml();
    }
    
    /**
     * Get the configured model class
     */
    public function getModel(): ?string
    {
        return $this->model;
    }
    
    /**
     * Get the raw action objects (not just their array representation)
     */
    public function getActions(): array
    {
        return $this->actions;
    }
    
    /**
     * Get the raw bulk action objects
     */
    public function getBulkActions(): array
    {
        return $this->bulkActions;
    }
    
    /**
     * Get the raw header action objects
     */
    public function getHeaderActions(): array
    {
        return $this->headerActions;
    }
    
    /**
     * Resolve a record by ID from the configured model/query
     */
    public function resolveRecord($recordId): Model
    {
        if (!$this->model) {
            throw new \Exception('No model configured for table record resolution');
        }
        
        $query = $this->getQuery();
        $record = $query->find($recordId);
        
        if (!$record) {
            throw new \Exception("Record with ID [{$recordId}] not found");
        }
        
        return $record;
    }
}