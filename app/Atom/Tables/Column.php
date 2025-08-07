<?php

namespace App\Atom\Tables;

/**
 * Column Factory Class
 * 
 * FilamentPHP-inspired column system with fluent API for configuration.
 * Uses factory pattern: Column::make('field_name')->sortable()->searchable()
 */
class Column
{
    protected string $key;
    protected ?string $label = null;
    protected string $type = 'text';
    protected bool $sortable = false;
    protected bool $searchable = false;
    protected array $badges = [];
    protected string $class = '';
    protected ?string $font = null;
    
    public function __construct(string $key)
    {
        $this->key = $key;
        $this->label = ucwords(str_replace(['_', '-'], ' ', $key));
    }
    
    /**
     * Factory method to create a new column
     */
    public static function make(string $key): static
    {
        return new static($key);
    }
    
    /**
     * Set the column label
     */
    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }
    
    /**
     * Make the column sortable
     */
    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;
        return $this;
    }
    
    /**
     * Make the column searchable
     */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;
        return $this;
    }
    
    /**
     * Set column as badge type with configuration
     */
    public function badge(array $badges = []): static
    {
        $this->type = 'badge';
        $this->badges = $badges;
        return $this;
    }
    
    /**
     * Set additional CSS classes
     */
    public function class(string $class): static
    {
        $this->class = $class;
        return $this;
    }
    
    /**
     * Set font styling (e.g., 'font-mono' for monospace)
     */
    public function font(string $font): static
    {
        $this->font = $font;
        return $this;
    }
    
    /**
     * Convert column to array for rendering
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'sortable' => $this->sortable,
            'searchable' => $this->searchable,
            'badges' => $this->badges,
            'class' => $this->class,
            'font' => $this->font,
        ];
    }
}