<?php

namespace App\Table\Concerns;

use App\Table\Table;

/**
 * InteractsWithTable Trait
 * 
 * FilamentPHP-inspired trait that enables Livewire components to work with tables.
 * Provides magic property resolution for {{ $this->table }} rendering.
 */
trait InteractsWithTable
{
    /**
     * Cache for the table instance
     */
    protected ?Table $tableInstance = null;
    
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
    
    /**
     * Abstract method that must be implemented by components using this trait
     * This is where developers configure their table
     */
    abstract public function table(Table $table): Table;
}