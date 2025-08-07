<?php

namespace App\Table;

use Closure;

/**
 * Select Filter Class
 * 
 * FilamentPHP-inspired select filter with options support.
 * Extends base Filter with dropdown-specific functionality.
 */
class SelectFilter extends Filter
{
    protected array $options = [];
    protected bool $multiple = false;
    protected ?string $relationship = null;
    protected ?string $relationshipColumn = null;
    protected bool $searchable = false;
    protected bool $preload = false;
    protected ?string $placeholder = null;
    
    public function __construct(string $key)
    {
        parent::__construct($key);
        $this->type = 'select';
        $this->placeholder = "Filter by {$this->label}";
    }
    
    /**
     * Set options for the select filter
     */
    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }
    
    /**
     * Make the select multiple
     */
    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;
        if ($multiple) {
            $this->type = 'multiselect';
        }
        return $this;
    }
    
    /**
     * Set relationship for options
     */
    public function relationship(string $name, string $column = 'name'): static
    {
        $this->relationship = $name;
        $this->relationshipColumn = $column;
        return $this;
    }
    
    /**
     * Make the select searchable
     */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;
        return $this;
    }
    
    /**
     * Preload options
     */
    public function preload(bool $preload = true): static
    {
        $this->preload = $preload;
        return $this;
    }
    
    /**
     * Set placeholder text
     */
    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;
        return $this;
    }
    
    /**
     * Convert filter to array for rendering
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'options' => $this->options,
            'multiple' => $this->multiple,
            'relationship' => $this->relationship,
            'relationshipColumn' => $this->relationshipColumn,
            'searchable' => $this->searchable,
            'preload' => $this->preload,
            'placeholder' => $this->placeholder,
        ]);
    }
}