<?php

namespace App\Table;

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
    protected array $with = [];
    protected array $withCount = [];
    protected int $recordsPerPage = 10;
    
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
     * Get the data for the table (paginated).
     */
    public function getData()
    {
        $query = $this->getQuery();
        
        // For now, just return paginated data
        // TODO: Add search, filters, sorting
        return $query->paginate($this->recordsPerPage);
    }
    
    /**
     * Convert the table configuration to array format.
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'columns' => array_map(function ($column) {
                return is_object($column) && method_exists($column, 'toArray') 
                    ? $column->toArray() 
                    : $column;
            }, $this->columns),
            'searchable' => $this->searchableColumns,
            'filters' => $this->filters,
            'actions' => $this->actions,
            'bulkActions' => $this->bulkActions,
            'with' => $this->with,
            'withCount' => $this->withCount,
            'recordsPerPage' => $this->recordsPerPage,
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
}