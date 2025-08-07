<?php

namespace App\StackedList;

use App\StackedList\Actions\BulkAction;
use App\StackedList\Actions\Action;
use App\StackedList\Columns\Column;
use App\StackedList\Filters\Filter;
use App\StackedList\Actions\HeaderAction;

class StackedListBuilder
{
    protected ?string $model = null;
    protected ?string $title = null;
    protected ?string $subtitle = null;
    protected array $searchable = [];
    protected array $columns = [];
    protected array $bulkActions = [];
    protected array $actions = [];
    protected array $filters = [];
    protected array $headerActions = [];
    protected array $baseFilters = [];
    protected array $with = [];
    protected array $withCount = [];
    protected array $perPageOptions = [5, 10, 25, 50, 100];
    protected ?string $searchPlaceholder = null;
    protected array $sortableColumns = [];
    protected bool $export = true;
    protected ?string $defaultSortColumn = null;
    protected string $defaultSortDirection = 'asc';
    
    // Empty state configuration
    protected ?string $emptyTitle = null;
    protected ?string $emptyDescription = null;
    protected ?array $emptyAction = null;

    /**
     * Set the model class for the list.
     */
    public function model(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Set the title for the list.
     */
    public function title(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Set the subtitle for the list.
     */
    public function subtitle(string $subtitle): static
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    /**
     * Set the searchable fields.
     */
    public function searchable(array $fields): static
    {
        $this->searchable = $fields;
        return $this;
    }

    /**
     * Set the search placeholder text.
     */
    public function searchPlaceholder(string $placeholder): static
    {
        $this->searchPlaceholder = $placeholder;
        return $this;
    }

    /**
     * Set sortable columns explicitly.
     */
    public function sortableColumns(array $columns): static
    {
        $this->sortableColumns = $columns;
        return $this;
    }

    /**
     * Set the default sort column and direction.
     */
    public function defaultSort(string $column, string $direction = 'asc'): static
    {
        $this->defaultSortColumn = $column;
        $this->defaultSortDirection = $direction;
        return $this;
    }

    /**
     * Set the columns for the list.
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;
        
        // Auto-extract sortable columns from column definitions if not explicitly set
        if (empty($this->sortableColumns)) {
            $this->sortableColumns = collect($columns)
                ->filter(fn($column) => method_exists($column, 'isSortable') && $column->isSortable())
                ->map(fn($column) => $column->getName())
                ->values()
                ->toArray();
        }
            
        return $this;
    }

    /**
     * Set the bulk actions for the list.
     */
    public function bulkActions(array $actions): static
    {
        $this->bulkActions = $actions;
        return $this;
    }

    /**
     * Set the row actions for the list.
     */
    public function actions(array $actions): static
    {
        $this->actions = $actions;
        return $this;
    }

    /**
     * Set the filters for the list.
     */
    public function filters(array $filters): static
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * Set the header actions for the list.
     */
    public function headerActions(array $actions): static
    {
        $this->headerActions = $actions;
        return $this;
    }

    /**
     * Set base filters (applied to every query).
     */
    public function baseFilters(array $filters): static
    {
        $this->baseFilters = $filters;
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
     * Set per-page options.
     */
    public function perPageOptions(array $options): static
    {
        $this->perPageOptions = $options;
        return $this;
    }

    /**
     * Enable or disable export functionality.
     */
    public function export(bool $enabled = true): static
    {
        $this->export = $enabled;
        return $this;
    }

    /**
     * Configure empty state.
     */
    public function emptyState(string $title, ?string $description = null, EmptyStateAction|array|null $action = null): static
    {
        $this->emptyTitle = $title;
        $this->emptyDescription = $description;
        
        if ($action instanceof EmptyStateAction) {
            $this->emptyAction = $action->toArray();
        } else {
            $this->emptyAction = $action;
        }
        
        return $this;
    }

    /**
     * Convert to the legacy array format for backward compatibility.
     */
    public function toArray(): array
    {
        return array_filter([
            // Header Configuration
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            
            // Search & Filter Configuration
            'search_placeholder' => $this->searchPlaceholder ?? 'Search...',
            'searchable' => $this->searchable,
            'sortable_columns' => $this->sortableColumns,
            'default_sort' => $this->defaultSortColumn ? [
                'column' => $this->defaultSortColumn,
                'direction' => $this->defaultSortDirection
            ] : null,
            
            // Data Configuration
            'with' => $this->with,
            'withCount' => $this->withCount,
            'per_page_options' => $this->perPageOptions,
            'export' => $this->export,
            'baseFilters' => $this->baseFilters,
            
            // Header Actions
            'header_actions' => collect($this->headerActions)
                ->map(fn($action) => $action instanceof HeaderAction ? $action->toArray() : $action)
                ->toArray(),
            
            // Filters
            'filters' => collect($this->filters)
                ->mapWithKeys(function($filter, $key) {
                    if ($filter instanceof Filter) {
                        return [$filter->getKey() => $filter->toArray()];
                    }
                    return is_numeric($key) ? [] : [$key => $filter];
                })
                ->toArray(),
            
            // Table Columns
            'columns' => collect($this->columns)
                ->map(function($column) {
                    if ($column instanceof Column) {
                        return $column->toArray();
                    }
                    return $column;
                })
                ->toArray(),
            
            // Bulk Actions
            'bulk_actions' => collect($this->bulkActions)
                ->map(fn($action) => $action instanceof BulkAction ? $action->toArray() : $action)
                ->toArray(),
            
            // Row Actions (embedded in actions column)
            'actions' => collect($this->actions)
                ->map(fn($action) => $action instanceof Action ? $action->toArray() : $action)
                ->toArray(),
            
            // Empty State Configuration
            'empty_title' => $this->emptyTitle,
            'empty_description' => $this->emptyDescription,
            'empty_action' => $this->emptyAction,
        ], fn($value) => $value !== null);
    }

    /**
     * Get the model class.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Get all bulk actions (for execution handling).
     */
    public function getBulkActions(): array
    {
        return $this->bulkActions;
    }

    /**
     * Get a specific bulk action by key.
     */
    public function getBulkAction(string $key): ?BulkAction
    {
        return collect($this->bulkActions)
            ->first(fn($action) => $action instanceof BulkAction && $action->getKey() === $key);
    }
}