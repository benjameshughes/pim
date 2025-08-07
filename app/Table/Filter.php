<?php

namespace App\Table;

use Closure;

/**
 * Base Filter Class
 * 
 * FilamentPHP-inspired filter system with fluent API for configuration.
 * Uses factory pattern: Filter::make('key')->query(fn($query, $value) => ...)
 */
class Filter
{
    protected string $key;
    protected ?string $label = null;
    protected string $type = 'toggle';
    protected ?Closure $query = null;
    protected mixed $default = null;
    protected bool $hasDefault = false;
    
    public function __construct(string $key)
    {
        $this->key = $key;
        $this->label = ucwords(str_replace(['_', '-'], ' ', $key));
    }
    
    /**
     * Factory method to create a new filter
     */
    public static function make(string $key): static
    {
        return new static($key);
    }
    
    /**
     * Set the filter label
     */
    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }
    
    /**
     * Set the query callback for filtering
     */
    public function query(Closure $callback): static
    {
        $this->query = $callback;
        return $this;
    }
    
    /**
     * Set default value
     */
    public function default(mixed $value = true): static
    {
        $this->default = $value;
        $this->hasDefault = true;
        return $this;
    }
    
    /**
     * Set as toggle filter
     */
    public function toggle(): static
    {
        $this->type = 'toggle';
        return $this;
    }
    
    /**
     * Set as checkbox filter
     */
    public function checkbox(): static
    {
        $this->type = 'checkbox';
        return $this;
    }
    
    /**
     * Get the filter key
     */
    public function getKey(): string
    {
        return $this->key;
    }
    
    /**
     * Get the query callback
     */
    public function getQuery(): ?Closure
    {
        return $this->query;
    }
    
    /**
     * Check if has default value
     */
    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }
    
    /**
     * Get default value
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }
    
    /**
     * Convert filter to array for rendering
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'default' => $this->default,
            'hasDefault' => $this->hasDefault,
            'query' => $this->query !== null,
        ];
    }
}