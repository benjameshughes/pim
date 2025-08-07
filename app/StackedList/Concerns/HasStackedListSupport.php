<?php

namespace App\StackedList\Concerns;

use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use App\StackedList\StackedListService;
use App\StackedList\StackedListBuilder;

/**
 * Simple trait to enable StackedList support in any Livewire component.
 * Just like FilamentPHP - no separate component needed.
 */
trait HasStackedListSupport 
{
    use WithPagination;

    // StackedList properties - will be populated automatically
    public string $stackedListModel = '';
    public ?StackedListBuilder $stackedListBuilder = null;

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

    // Selection properties
    public array $stackedListSelectedItems = [];
    public bool $stackedListSelectAll = false;

    /**
     * Initialize StackedList support with a builder.
     */
    protected function initializeStackedList(StackedListBuilder $builder): void
    {
        $this->stackedListBuilder = $builder;
        $this->stackedListModel = $builder->getModel() ?? '';
    }

    /**
     * Get the StackedList data (computed property).
     */
    #[Computed]
    public function stackedListData()
    {
        if (!$this->stackedListBuilder) {
            return collect();
        }

        $query = $this->buildStackedListQuery();
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

    /**
     * Build the StackedList query.
     */
    protected function buildStackedListQuery()
    {
        if (!$this->stackedListModel) {
            throw new \Exception('StackedList model not set. Call initializeStackedList() first.');
        }

        $model = $this->stackedListModel;
        $query = (new $model)->newQuery();
        $config = $this->stackedListBuilder->toArray();
        
        // Apply search
        if (!empty($this->stackedListSearch) && !empty($config['searchable'])) {
            $query->where(function ($subQuery) use ($config) {
                foreach ($config['searchable'] as $field) {
                    if (str_contains($field, '.')) {
                        // Relationship search
                        [$relation, $column] = explode('.', $field, 2);
                        $subQuery->orWhereHas($relation, function ($relationQuery) use ($column) {
                            $relationQuery->where($column, 'like', "%{$this->stackedListSearch}%");
                        });
                    } else {
                        // Direct column search
                        $subQuery->orWhere($field, 'like', "%{$this->stackedListSearch}%");
                    }
                }
            });
        }
        
        // Apply filters
        foreach ($this->stackedListFilters as $key => $value) {
            if (empty($value)) continue;
            
            $filterConfig = $config['filters'][$key] ?? null;
            if (!$filterConfig) continue;

            $column = $filterConfig['column'] ?? $key;
            
            if (isset($filterConfig['relation'])) {
                // Relationship filter
                $query->whereHas($filterConfig['relation'], function ($relationQuery) use ($filterConfig, $value) {
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
        
        // Apply sorting
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
        
        return $query;
    }

    // Event handlers
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
        $pageIds = $this->stackedListData->pluck((new $this->stackedListModel)->getKeyName())->toArray();
        
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
        $pageIds = $this->stackedListData->pluck((new $this->stackedListModel)->getKeyName())->toArray();
        
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
}