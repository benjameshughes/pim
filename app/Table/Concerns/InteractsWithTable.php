<?php

namespace App\Table\Concerns;

use App\Table\Table;
use Livewire\Attributes\Url;

/**
 * InteractsWithTable Trait
 * 
 * FilamentPHP-inspired trait that enables Livewire components to work with tables.
 * Provides complete table state management and magic property resolution.
 */
trait InteractsWithTable
{
    /**
     * Cache for the table instance
     */
    protected ?Table $tableInstance = null;
    
    // ===== CORE TABLE STATE PROPERTIES =====
    // Following FilamentPHP's property patterns
    
    /**
     * Current search term
     */
    #[Url(except: '', history: true)]
    public ?string $tableSearch = '';
    
    /**
     * Active filters
     */
    #[Url(except: [], history: true)]
    public ?array $tableFilters = [];
    
    /**
     * Current sort column
     */
    #[Url(except: '', history: true)]  
    public ?string $tableSortColumn = '';
    
    /**
     * Current sort direction
     */
    #[Url(except: 'asc', history: true)]
    public ?string $tableSortDirection = 'asc';
    
    /**
     * Records per page
     */
    #[Url(except: 10, history: true)]
    public int $tableRecordsPerPage = 10;
    
    /**
     * Selected records for bulk actions
     */
    public array $selectedTableRecords = [];
    
    /**
     * Select all records on current page
     */
    public bool $selectAllTableRecords = false;
    
    // ===== MAGIC PROPERTY METHODS =====
    
    /**
     * Magic property getter for $this->table
     * This enables {{ $this->table }} to work in Blade templates
     */
    public function getTableProperty()
    {
        if ($this->tableInstance === null) {
            $this->tableInstance = $this->table(Table::make($this));
        }
        
        return $this->tableInstance;
    }
    
    // ===== TABLE STATE METHODS =====
    
    /**
     * Get the current search term
     */
    public function getTableSearch(): ?string
    {
        return $this->tableSearch;
    }
    
    /**
     * Get active table filters
     */
    public function getTableFilters(): array
    {
        return $this->tableFilters ?? [];
    }
    
    /**
     * Get current sort column
     */
    public function getTableSortColumn(): ?string
    {
        return $this->tableSortColumn ?: $this->getDefaultTableSortColumn();
    }
    
    /**
     * Get current sort direction
     */
    public function getTableSortDirection(): string
    {
        return $this->tableSortDirection ?: $this->getDefaultTableSortDirection();
    }
    
    /**
     * Get records per page
     */
    public function getTableRecordsPerPage(): int
    {
        return $this->tableRecordsPerPage ?: $this->getDefaultTableRecordsPerPage();
    }
    
    // ===== TABLE ACTION METHODS =====
    
    /**
     * Reset table search
     */
    public function resetTableSearch(): void
    {
        $this->tableSearch = '';
        $this->resetPage();
    }
    
    /**
     * Reset table filters
     */
    public function resetTableFilters(): void
    {
        $this->tableFilters = [];
        $this->resetPage();
    }
    
    /**
     * Reset entire table state
     */
    public function resetTable(): void
    {
        $this->tableSearch = '';
        $this->tableFilters = [];
        $this->tableSortColumn = '';
        $this->tableSortDirection = 'asc';
        $this->selectedTableRecords = [];
        $this->selectAllTableRecords = false;
        $this->resetPage();
    }
    
    /**
     * Sort table by column
     */
    public function sortTableBy(string $column): void
    {
        if ($this->tableSortColumn === $column) {
            $this->tableSortDirection = $this->tableSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->tableSortColumn = $column;
            $this->tableSortDirection = 'asc';
        }
        
        $this->resetPage();
    }
    
    /**
     * Clear selected records
     */
    public function clearSelectedTableRecords(): void
    {
        $this->selectedTableRecords = [];
        $this->selectAllTableRecords = false;
    }
    
    /**
     * Toggle select all records on current page
     */
    public function toggleSelectAllTableRecords(): void
    {
        if ($this->selectAllTableRecords) {
            $this->selectedTableRecords = [];
            $this->selectAllTableRecords = false;
        } else {
            // This would be populated with actual record IDs from the current page
            $this->selectAllTableRecords = true;
        }
    }
    
    // ===== LIVEWIRE LIFECYCLE METHODS =====
    
    /**
     * Update records per page and reset pagination
     */
    public function updatedTableRecordsPerPage(): void
    {
        $this->resetPage();
    }
    
    /**
     * Reset pagination when search changes
     */
    public function updatedTableSearch(): void
    {
        $this->resetPage();
    }
    
    /**
     * Reset pagination when filters change
     */
    public function updatedTableFilters(): void
    {
        $this->resetPage();
    }
    
    // ===== PROTECTED CONFIGURATION METHODS =====
    // These can be overridden by components for customization
    
    /**
     * Get default records per page
     */
    protected function getDefaultTableRecordsPerPage(): int
    {
        return 10;
    }
    
    /**
     * Get default sort column
     */
    protected function getDefaultTableSortColumn(): ?string
    {
        return null;
    }
    
    /**
     * Get default sort direction
     */
    protected function getDefaultTableSortDirection(): string
    {
        return 'asc';
    }
    
    /**
     * Should persist search in session
     */
    protected function shouldPersistTableSearchInSession(): bool
    {
        return false;
    }
    
    /**
     * Should persist filters in session
     */
    protected function shouldPersistTableFiltersInSession(): bool
    {
        return false;
    }
    
    /**
     * Should persist sort in session
     */
    protected function shouldPersistTableSortInSession(): bool
    {
        return false;
    }
    
    // ===== ACTION EXECUTION METHODS =====
    
    /**
     * Execute a header action
     */
    public function executeTableHeaderAction(string $actionKey): void
    {
        $table = $this->getTableProperty();
        $config = $table->toArray();
        
        foreach ($config['headerActions'] as $actionConfig) {
            if ($actionConfig['key'] === $actionKey) {
                if ($actionConfig['hasAction']) {
                    // Execute the action callback if it exists
                    // This would need the actual Action object, so we'll handle it in a future iteration
                }
                break;
            }
        }
    }
    
    /**
     * Execute a row action
     */
    public function executeTableAction(string $actionKey, $recordId): void
    {
        $table = $this->getTableProperty();
        $config = $table->toArray();
        
        foreach ($config['actions'] as $actionConfig) {
            if ($actionConfig['key'] === $actionKey) {
                if ($actionConfig['hasAction']) {
                    // Execute the action callback if it exists
                    // This would need the actual Action object and record
                }
                break;
            }
        }
    }
    
    /**
     * Execute a bulk action
     */
    public function executeTableBulkAction(string $actionKey): void
    {
        if (empty($this->selectedTableRecords)) {
            return;
        }
        
        $table = $this->getTableProperty();
        $config = $table->toArray();
        
        foreach ($config['bulkActions'] as $actionConfig) {
            if ($actionConfig['key'] === $actionKey) {
                if ($actionConfig['hasAction']) {
                    // Execute the bulk action callback if it exists
                    // This would need the actual BulkAction object and selected records
                }
                
                // Clear selection if configured to do so
                if ($actionConfig['deselectRecordsAfterCompletion'] ?? true) {
                    $this->clearSelectedTableRecords();
                }
                break;
            }
        }
    }
    
    // ===== ABSTRACT METHODS =====
    
    /**
     * Abstract method that must be implemented by components using this trait
     * This is where developers configure their table
     */
    abstract public function table(Table $table): Table;
}