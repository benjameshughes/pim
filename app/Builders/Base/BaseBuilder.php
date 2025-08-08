<?php

namespace App\Builders\Base;

use InvalidArgumentException;

/**
 * Base Builder Class
 * 
 * Abstract base class implementing the Builder Pattern with fluent API.
 * Builders provide a clean, readable interface for constructing complex objects.
 * 
 * @package App\Builders\Base
 */
abstract class BaseBuilder
{
    /**
     * Data array to store builder configuration
     * 
     * @var array
     */
    protected array $data = [];
    
    /**
     * Validation rules for the builder data
     * 
     * @var array
     */
    protected array $rules = [];
    
    /**
     * Required fields that must be set before execution
     * 
     * @var array
     */
    protected array $required = [];
    
    /**
     * Create a new builder instance (static factory method)
     * 
     * @return static
     */
    public static function create(): static
    {
        return new static();
    }
    
    /**
     * Execute the builder and create/update the target object
     * 
     * @return mixed The created/updated object
     */
    abstract public function execute();
    
    /**
     * Set multiple data values at once
     * 
     * @param array $data Data to merge with current builder data
     * @return static
     */
    public function with(array $data): static
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }
    
    /**
     * Set a single data value
     * 
     * @param string $key Data key
     * @param mixed $value Data value
     * @return static
     */
    public function set(string $key, $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }
    
    /**
     * Get a data value
     * 
     * @param string $key Data key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }
    
    /**
     * Check if a data key exists
     * 
     * @param string $key Data key to check
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }
    
    /**
     * Get all current builder data
     * 
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
    
    /**
     * Clear all builder data
     * 
     * @return static
     */
    public function clear(): static
    {
        $this->data = [];
        return $this;
    }
    
    /**
     * Validate builder data before execution
     * 
     * @throws InvalidArgumentException If validation fails
     * @return void
     */
    protected function validate(): void
    {
        // Check required fields
        foreach ($this->required as $field) {
            if (!$this->has($field)) {
                throw new InvalidArgumentException("Required field '{$field}' is missing");
            }
        }
        
        // Run custom validation if implemented
        $this->customValidation();
    }
    
    /**
     * Custom validation hook for subclasses to override
     * 
     * @throws InvalidArgumentException If custom validation fails
     * @return void
     */
    protected function customValidation(): void
    {
        // Default: no additional validation
    }
    
    /**
     * Reset builder to initial state but preserve type
     * 
     * @return static
     */
    public function fresh(): static
    {
        return static::create();
    }
    
    /**
     * Clone the current builder with all data
     * 
     * @return static
     */
    public function clone(): static
    {
        return static::create()->with($this->data);
    }
    
    /**
     * Magic method to handle dynamic property setters
     * 
     * @param string $method Method name
     * @param array $arguments Method arguments
     * @return static
     * @throws InvalidArgumentException If method doesn't exist
     */
    public function __call(string $method, array $arguments): static
    {
        // Allow dynamic setters like ->name('value') -> set('name', 'value')
        if (count($arguments) === 1) {
            return $this->set($method, $arguments[0]);
        }
        
        throw new InvalidArgumentException("Method {$method} not found on builder");
    }
}