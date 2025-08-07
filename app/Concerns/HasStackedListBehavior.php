<?php

namespace App\Concerns;

use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Illuminate\Database\Eloquent\Builder;
use App\Actions\StackedList\ExportStackedListDataAction;
use App\StackedList\StackedListBuilder;
use App\StackedList\Actions\BulkAction;

/**
 * Trait for adding StackedList functionality to Livewire components.
 * 
 * Uses FilamentPHP-style naming conventions to avoid conflicts.
 * Components should implement HasStackedList contract.
 */
trait HasStackedListBehavior
{
    use WithPagination;

    // Core properties
    public string $stackedListModel;
    protected StackedListBuilder $stackedListBuilder;

    // URL-tracked properties
    #[Url(except: '')]
    public string $stackedListSearch = '';

    #[Url(except: '')]
    public string $stackedListSortBy = '';

    #[Url(except: 'asc')]
    public string $stackedListSortDirection = 'asc';

    #[Url]
    public array $stackedListFilters = [];

    #[Url]
    public array $stackedListSortStack = [];

    #[Url(except: 10)]
    public int $stackedListPerPage = 10;

    // Selection properties - SINGLE source of truth
    public array $stackedListSelectedItems = [];
    public bool $stackedListSelectAll = false;

    // Export properties
    public bool $showStackedListExportModal = false;
    public string $stackedListExportFormat = 'csv';

    public function bootHasStackedListBehavior()
    {
        // Boot method - runs after component is instantiated
    }

    public function initializeStackedList(string $modelClass, StackedListBuilder $builder)
    {
        $this->stackedListModel = $modelClass;
        $this->stackedListBuilder = $builder;
    }

    /**
     * Create a new StackedListBuilder instance.
     */
    protected function stackedList(): StackedListBuilder
    {
        return new StackedListBuilder();
    }

    /**
     * Get the stacked list builder for the component.
     */
    #[Computed]
    public function stackedListBuilder(): StackedListBuilder
    {
        return $this->stackedListBuilder;
    }

    #[Computed]
    public function stackedListQuery(): Builder
    {
        return $this->buildStackedListQuery();
    }

    #[Computed] 
    public function stackedListData()
    {
        $query = $this->stackedListQuery;
        $config = $this->stackedListBuilder->toArray();
        
        // Load relationships
        if (!empty($config['with'])) {
            $query->with($config['with']);
        }
        
        // Load counts
        if (!empty($config['withCount'])) {
            $query->withCount($config['withCount']);
        }
        
        return $query->paginate($this->stackedListPerPage);
    }

    #[Computed]
    public function stackedListPerPageOptions()
    {
        $config = $this->stackedListBuilder->toArray();
        return collect(data_get($config, 'per_page_options', [5, 10, 25, 50, 100]))
            ->mapWithKeys(fn($value) => [$value => $value === 1 ? '1 item' : "{$value} items"]);
    }

    #[Computed]
    public function stackedListTotalCount()
    {
        // Get total count without pagination for "select all" functionality
        return $this->buildStackedListQuery()->count();
    }



    protected function buildStackedListQuery(): Builder
    {
        $query = (new $this->stackedListModel)->newQuery();
        
        // Apply base filters
        $this->applyStackedListBaseFilters($query);
        
        // Apply search
        $this->applyStackedListSearch($query);
        
        // Apply filters  
        $this->applyStackedListFilters($query);
        
        // Apply sorting
        $this->applyStackedListSorting($query);
        
        return $query;
    }

    protected function applyStackedListBaseFilters(Builder $query): void
    {
        $config = $this->stackedListBuilder->toArray();
        foreach ($config['baseFilters'] ?? [] as $field => $value) {
            if (is_callable($value)) {
                $value($query);
            } else {
                $query->where($field, $value);
            }
        }
    }

    protected function applyStackedListSearch(Builder $query): void
    {
        $config = $this->stackedListBuilder->toArray();
        if (empty($this->stackedListSearch) || empty($config['searchable'])) {
            return;
        }

        $query->where(function (Builder $subQuery) use ($config) {
            foreach ($config['searchable'] as $field) {
                if (str_contains($field, '.')) {
                    // Relationship search
                    [$relation, $column] = explode('.', $field, 2);
                    $subQuery->orWhereHas($relation, function (Builder $relationQuery) use ($column) {
                        $relationQuery->where($column, 'like', "%{$this->stackedListSearch}%");
                    });
                } else {
                    // Direct column search
                    $subQuery->orWhere($field, 'like', "%{$this->stackedListSearch}%");
                }
            }
        });
    }

    protected function applyStackedListFilters(Builder $query): void
    {
        $config = $this->stackedListBuilder->toArray();
        foreach ($this->stackedListFilters as $key => $value) {
            if (empty($value)) continue;
            
            $filterConfig = $config['filters'][$key] ?? null;
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

    protected function applyStackedListSorting(Builder $query): void
    {
        if (!empty($this->stackedListSortStack)) {
            // Multi-column sorting
            foreach (collect($this->stackedListSortStack)->sortBy('priority') as $sort) {
                if (str_contains($sort['column'], '.')) {
                    [$relation, $column] = explode('.', $sort['column'], 2);
                    $query->orderBy($relation . '.' . $column, $sort['direction']);
                } else {
                    $query->orderBy($sort['column'], $sort['direction']);
                }
            }
        } elseif ($this->stackedListSortBy) {
            // Single column sorting
            if (str_contains($this->stackedListSortBy, '.')) {
                [$relation, $column] = explode('.', $this->stackedListSortBy, 2);
                $query->orderBy($relation . '.' . $column, $this->stackedListSortDirection);
            } else {
                $query->orderBy($this->stackedListSortBy, $this->stackedListSortDirection);
            }
        }
    }

    // Event handlers with prefixed names
    public function updatedStackedListSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStackedListFilters(): void
    {
        $this->resetPage();
    }

    public function stackedListSortColumn(string $column, bool $multi = false): void
    {
        if ($multi) {
            $this->addToStackedListSortStack($column);
        } else {
            $this->stackedListSortStack = [];
            
            if ($this->stackedListSortBy === $column) {
                $this->stackedListSortDirection = $this->stackedListSortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                $this->stackedListSortBy = $column;
                $this->stackedListSortDirection = 'asc';
            }
        }
        
        $this->resetPage();
    }

    protected function addToStackedListSortStack(string $column): void
    {
        $existingIndex = collect($this->stackedListSortStack)->search(fn($sort) => $sort['column'] === $column);

        if ($existingIndex !== false) {
            // Toggle direction
            $this->stackedListSortStack[$existingIndex]['direction'] = 
                $this->stackedListSortStack[$existingIndex]['direction'] === 'asc' ? 'desc' : 'asc';
        } else {
            // Add new column
            $this->stackedListSortStack[] = [
                'column' => $column,
                'direction' => 'asc',
                'priority' => count($this->stackedListSortStack) + 1
            ];
        }

        // Update primary sort
        $this->stackedListSortBy = $column;
        $this->stackedListSortDirection = collect($this->stackedListSortStack)
            ->firstWhere('column', $column)['direction'] ?? 'asc';
    }

    public function clearAllStackedListSorts(): void
    {
        $this->stackedListSortStack = [];
        $this->stackedListSortBy = '';
        $this->stackedListSortDirection = 'asc';
        $this->resetPage();
    }

    public function removeStackedListSortColumn(string $column): void
    {
        $this->stackedListSortStack = collect($this->stackedListSortStack)
            ->reject(fn($sort) => $sort['column'] === $column)
            ->values()
            ->toArray();
        
        // If we removed the current primary sort, update it
        if ($this->stackedListSortBy === $column) {
            $firstSort = collect($this->stackedListSortStack)->first();
            if ($firstSort) {
                $this->stackedListSortBy = $firstSort['column'];
                $this->stackedListSortDirection = $firstSort['direction'];
            } else {
                $this->stackedListSortBy = '';
                $this->stackedListSortDirection = 'asc';
            }
        }
        
        $this->resetPage();
    }

    public function clearStackedListFilters(): void
    {
        $this->stackedListSearch = '';
        $this->stackedListFilters = [];
        $this->resetPage();
    }


    public function updatedStackedListSelectedItems(): void
    {
        // Clean the array
        $this->stackedListSelectedItems = array_values(array_unique(array_filter($this->stackedListSelectedItems)));
        
        // Update header checkbox based on array
        $pageIds = $this->stackedListData->pluck($this->getStackedListModelKeyName())->toArray();
        
        if (empty($pageIds)) {
            $this->stackedListSelectAll = false;
            return;
        }
        
        // Check if ALL page items are selected
        $pageIds = array_map('strval', $pageIds);
        $selectedIds = array_map('strval', $this->stackedListSelectedItems);
        
        $allSelected = true;
        foreach ($pageIds as $id) {
            if (!in_array($id, $selectedIds)) {
                $allSelected = false;
                break;
            }
        }
        
        $this->stackedListSelectAll = $allSelected;
    }
    
    public function updatedStackedListSelectAll(): void
    {
        $pageIds = $this->stackedListData->pluck($this->getStackedListModelKeyName())->toArray();
        
        if (empty($pageIds)) {
            return;
        }
        
        if ($this->stackedListSelectAll) {
            // Add all page items to selection
            $this->stackedListSelectedItems = array_values(array_unique(array_merge($this->stackedListSelectedItems, $pageIds)));
        } else {
            // Remove all page items from selection
            $this->stackedListSelectedItems = array_values(array_diff($this->stackedListSelectedItems, $pageIds));
        }
    }


    public function clearStackedListSelection(): void
    {
        $this->stackedListSelectedItems = [];
        $this->stackedListSelectAll = false;
    }

    public function executeStackedListBulkAction(string $action): void
    {
        if (empty($this->stackedListSelectedItems)) {
            return;
        }

        // First try to find the action in the builder
        if ($this->stackedListBuilder) {
            $bulkAction = $this->stackedListBuilder->getBulkAction($action);
            if ($bulkAction && $bulkAction->hasAction()) {
                $bulkAction->execute($this->stackedListSelectedItems, $this);
                $this->clearStackedListSelection();
                return;
            }
        }

        // Fallback to the component's handleBulkAction method
        if (method_exists($this, 'handleBulkAction')) {
            $this->handleBulkAction($action, $this->stackedListSelectedItems);
        }
        
        $this->clearStackedListSelection();
    }

    // Export functionality
    public function showStackedListExportModal(): void
    {
        $this->showStackedListExportModal = true;
    }

    public function closeStackedListExportModal(): void
    {
        $this->showStackedListExportModal = false;
        $this->stackedListExportFormat = 'csv';
    }

    public function exportStackedListData(?string $format = null): void
    {
        $format = $format ?: $this->stackedListExportFormat;
        
        // Get all data for export (not paginated)
        $query = $this->buildStackedListQuery();
        
        // Load relationships for export
        $config = $this->stackedListBuilder->toArray();
        if (!empty($config['with'])) {
            $query->with($config['with']);
        }
        
        if (!empty($config['withCount'])) {
            $query->withCount($config['withCount']);
        }
        
        $data = $query->get();
        
        // Call the component's export method if it exists
        if (method_exists($this, 'handleExport')) {
            $this->handleExport($format, $data, [
                'search' => $this->stackedListSearch,
                'filters' => $this->stackedListFilters,
                'sortBy' => $this->stackedListSortBy,
                'sortDirection' => $this->stackedListSortDirection,
                'selectedItems' => $this->stackedListSelectedItems
            ]);
        } else {
            // Default export behavior
            $this->defaultStackedListExport($format, $data);
        }
        
        $this->closeStackedListExportModal();
    }

    protected function defaultStackedListExport(string $format, $data): void
    {
        try {
            $exportAction = app(ExportStackedListDataAction::class);
            
            // Get columns for export
            $config = $this->stackedListBuilder->toArray();
            $columns = collect($config['columns'] ?? [])
                ->where('type', '!=', 'actions')
                ->pluck('label', 'key')
                ->toArray();
            
            $metadata = [
                'title' => $config['title'] ?? 'Export',
                'search' => $this->stackedListSearch,
                'filters' => $this->stackedListFilters,
                'sortBy' => $this->stackedListSortBy,
                'sortDirection' => $this->stackedListSortDirection,
            ];

            $response = $exportAction->execute($format, $data, $columns, $metadata);
            $response->send();
            
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    protected function getStackedListModelKeyName(): string
    {
        return (new $this->stackedListModel)->getKeyName();
    }
}