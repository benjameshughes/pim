<?php

namespace App\Casts\Import;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Laravel attribute cast for clean integer handling
 * Sanitizes and validates integer values from Excel imports
 */
class CleanInteger implements CastsAttributes
{
    private ?int $min;
    private ?int $max;
    private int $default;
    
    public function __construct(?int $min = null, ?int $max = null, int $default = 0)
    {
        $this->min = $min;
        $this->max = $max;
        $this->default = $default;
    }
    
    /**
     * Cast the given value for storage
     */
    public function set(Model $model, string $key, $value, array $attributes): int
    {
        if ($value === null || $value === '') {
            return $this->default;
        }
        
        // Handle Excel scientific notation
        if (is_string($value) && preg_match('/^\d+\.?\d*E[+-]?\d+$/i', $value)) {
            $value = number_format((float) $value, 0, '', '');
        }
        
        // Clean the value - remove non-numeric characters except minus sign
        $cleanValue = preg_replace('/[^0-9-]/', '', (string) $value);
        
        // Convert to integer
        $intValue = (int) $cleanValue;
        
        // Apply range validation
        if ($this->min !== null && $intValue < $this->min) {
            throw new \InvalidArgumentException("Value {$intValue} is below minimum {$this->min}");
        }
        
        if ($this->max !== null && $intValue > $this->max) {
            throw new \InvalidArgumentException("Value {$intValue} is above maximum {$this->max}");
        }
        
        return $intValue;
    }
    
    /**
     * Cast the given value for retrieval
     */
    public function get(Model $model, string $key, $value, array $attributes): int
    {
        return (int) $value;
    }
}