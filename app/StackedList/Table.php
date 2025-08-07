<?php

namespace App\StackedList;

use Livewire\Component;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Htmlable;

class Table implements Htmlable
{
    protected Component $livewire;
    protected ?string $model = null;
    protected ?Builder $query = null;
    protected array $columns = [];
    protected array $searchableColumns = [];
    protected array $filters = [];
    protected array $bulkActions = [];
    protected array $actions = [];
    protected int $recordsPerPage = 10;
    protected string $title = '';
    protected string $subtitle = '';
    protected array $with = [];
    protected array $withCount = [];

    public function __construct(?Component $livewire = null)
    {
        if ($livewire) {
            $this->livewire = $livewire;
        }
    }

    /**
     * Create a new table instance.
     */
    public static function make(?Component $livewire = null): static
    {
        return new static($livewire);
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
    public function model(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Set the base query for the table.
     */
    public function query(Builder $query): static
    {
        $this->query = $query;
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
     * Set bulk actions.
     */
    public function bulkActions(array $actions): static
    {
        $this->bulkActions = $actions;
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
     * Set records per page.
     */
    public function recordsPerPage(int $count): static
    {
        $this->recordsPerPage = $count;
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
     * Get the table query with all applied filters, search, etc.
     */
    public function getQuery(): Builder
    {
        // Start with the base query or model
        if ($this->query) {
            $query = clone $this->query;
        } elseif ($this->model) {
            $query = (new $this->model)->newQuery();
        } else {
            throw new \Exception('Table must have either a model or query set');
        }

        // Apply search
        $this->applySearch($query);

        // Apply filters
        $this->applyFilters($query);

        // Apply sorting
        $this->applySorting($query);

        // Apply relationships
        if (!empty($this->with)) {
            $query->with($this->with);
        }

        if (!empty($this->withCount)) {
            $query->withCount($this->withCount);
        }

        return $query;
    }

    /**
     * Apply search to the query.
     */
    protected function applySearch(Builder $query): void
    {
        if (!property_exists($this->livewire, 'stackedListSearch')) {
            return;
        }

        $search = $this->livewire->stackedListSearch ?? '';
        
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
     * Apply filters to the query.
     */
    protected function applyFilters(Builder $query): void
    {
        if (!property_exists($this->livewire, 'stackedListFilters')) {
            return;
        }

        $filters = $this->livewire->stackedListFilters ?? [];

        foreach ($filters as $key => $value) {
            if (empty($value)) continue;
            
            $filterConfig = $this->filters[$key] ?? null;
            if (!$filterConfig) continue;

            $column = $filterConfig['column'] ?? $key;
            
            if (isset($filterConfig['relation'])) {
                // Relationship filter
                $query->whereHas($filterConfig['relation'], function (Builder $relationQuery) use ($filterConfig, $value) {
                    $relationQuery->where($filterConfig['column'], $value);
                });
            } else {
                // Direct column filter
                match ($filterConfig['type'] ?? 'select') {
                    'select' => $query->where($column, $value),
                    'multiselect' => $query->whereIn($column, (array) $value),
                    default => $query->where($column, $value)
                };
            }
        }
    }

    /**
     * Apply sorting to the query.
     */
    protected function applySorting(Builder $query): void
    {
        if (!property_exists($this->livewire, 'stackedListSortBy')) {
            return;
        }

        $sortBy = $this->livewire->stackedListSortBy ?? '';
        $sortDirection = $this->livewire->stackedListSortDirection ?? 'asc';

        if (empty($sortBy)) {
            return;
        }

        if (str_contains($sortBy, '.')) {
            [$relation, $column] = explode('.', $sortBy, 2);
            $query->orderBy($relation . '.' . $column, $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }
    }

    /**
     * Get records per page.
     */
    public function getRecordsPerPage(): int
    {
        // Get from livewire property if available, otherwise use default
        return $this->livewire->stackedListPerPage ?? $this->recordsPerPage;
    }

    /**
     * Get the Livewire component.
     */
    public function getLivewire(): Component
    {
        return $this->livewire;
    }

    /**
     * Render the table using the stacked-list view component
     */
    public function render(): string
    {
        return $this->toHtml();
    }

    /**
     * Convert the table to HTML (implements Htmlable interface)
     * This enables {{ $this->table }} to work in Blade templates
     */
    public function toHtml(): string
    {
        $viewName = $this->getViewName();
        
        return view($viewName)->with([
            'table' => $this,
            'livewire' => $this->livewire,
            'data' => $this->livewire->stackedListData ?? collect(),
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
     * Get the view name for rendering the table
     */
    protected function getViewName(): string
    {
        // Check if the Livewire component has a custom view defined
        if (method_exists($this->livewire, 'getStackedListView')) {
            return $this->livewire->getStackedListView();
        }

        // Default to the standard stacked-list component
        return 'components.stacked-list';
    }

    /**
     * Convert table configuration to array format for the blade component.
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'searchable' => $this->searchableColumns,
            'columns' => array_map(function ($column) {
                return is_object($column) && method_exists($column, 'toArray') 
                    ? $column->toArray() 
                    : $column;
            }, $this->columns),
            'filters' => array_map(function ($filter) {
                return is_object($filter) && method_exists($filter, 'toArray') 
                    ? $filter->toArray() 
                    : $filter;
            }, $this->filters),
            'bulk_actions' => array_map(function ($action) {
                return is_object($action) && method_exists($action, 'toArray') 
                    ? $action->toArray() 
                    : $action;
            }, $this->bulkActions),
            'actions' => array_map(function ($action) {
                return is_object($action) && method_exists($action, 'toArray') 
                    ? $action->toArray() 
                    : $action;
            }, $this->actions),
            'with' => $this->with,
            'withCount' => $this->withCount,
            'per_page_options' => [5, 10, 25, 50, 100],
            'export' => true,
        ];
    }
}