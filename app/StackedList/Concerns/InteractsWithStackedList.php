<?php

namespace App\StackedList\Concerns;

use App\StackedList\Table;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;

/**
 * Main trait for StackedList functionality - FilamentPHP style
 * 
 * This trait follows FilamentPHP's approach of using trait composition
 * to provide table functionality to Livewire components.
 */
trait InteractsWithStackedList
{
    use WithPagination;
    use CanSearchStackedList;
    use CanSortStackedList;
    use CanFilterStackedList;
    use CanSelectStackedList;
    use HasStackedListActions;

    protected ?Table $stackedListTable = null;

    /**
     * Boot the trait - called by Livewire during component boot
     */
    public function bootedInteractsWithStackedList(): void
    {
        // Initialize the table if not already done
        if (! $this->stackedListTable) {
            $this->stackedListTable = $this->makeStackedList();
        }
    }

    /**
     * Create and configure the StackedList table
     * This must be implemented by the using component
     */
    abstract public function stackedList(Table $table): Table;

    /**
     * Make a new StackedList table instance
     */
    protected function makeStackedList(): Table
    {
        $table = new Table();
        
        // Let the component configure the table
        $table = $this->stackedList($table);
        
        // Set the component reference for the table
        $table->livewire($this);
        
        return $table;
    }

    /**
     * Get the configured StackedList table
     */
    public function getStackedListTable(): Table
    {
        return $this->stackedListTable ??= $this->makeStackedList();
    }

    /**
     * Magic getter for the table property
     * This allows {{ $this->table }} in Blade templates, just like FilamentPHP
     */
    public function getTableProperty()
    {
        return $this->getStackedListTable()->render();
    }

    /**
     * Get the StackedList data for rendering
     */
    #[Computed]
    public function getStackedListDataProperty()
    {
        $table = $this->getStackedListTable();
        $query = $table->getQuery();
        
        return $query->paginate($table->getRecordsPerPage());
    }

    /**
     * Magic getter to forward stackedListData property access
     */
    public function __get($property)
    {
        if ($property === 'stackedListData') {
            return $this->getStackedListDataProperty();
        }
        
        return parent::__get($property);
    }

    /**
     * Reset search
     */
    public function resetSearch(): void
    {
        $this->stackedListSearch = '';
    }

    /**
     * Reset sort
     */
    public function resetSort(): void
    {
        $this->stackedListSortBy = '';
        $this->stackedListSortDirection = 'asc';
        $this->stackedListSortStack = [];
    }

    /**
     * Reset filters
     */
    public function resetFilters(): void
    {
        $this->stackedListFilters = [];
    }

    /**
     * Reset bulk actions
     */
    public function resetBulkActions(): void
    {
        $this->stackedListSelectedItems = [];
        $this->stackedListSelectAll = false;
    }

    /**
     * Get the view name for the StackedList table
     * Override this method to use a custom view
     */
    public function getStackedListView(): string
    {
        // Check if the component has a $view property
        if (property_exists($this, 'view') && !empty($this->view)) {
            return $this->view;
        }

        // Default to the standard stacked-list component
        return 'components.stacked-list';
    }

    /**
     * Reset the table state
     */
    public function resetStackedList(): void
    {
        $this->resetPage();
        $this->resetSearch();
        $this->resetSort();
        $this->resetFilters();
        $this->resetBulkActions();
        
        // Recreate the table
        $this->stackedListTable = null;
    }
}