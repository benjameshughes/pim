<?php

namespace App\Casts\Import;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Laravel attribute cast for safe decimal handling
 * Prevents precision loss and handles Excel's scientific notation
 */
class SafeDecimal implements CastsAttributes
{
    private int $precision;

    private ?float $min;

    private ?float $max;

    public function __construct(int $precision = 2, ?float $min = null, ?float $max = null)
    {
        $this->precision = $precision;
        $this->min = $min;
        $this->max = $max;
    }

    /**
     * Cast the given value for storage
     */
    public function set(Model $model, string $key, $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Handle Excel scientific notation
        if (is_string($value) && preg_match('/^\d+\.?\d*E[+-]?\d+$/i', $value)) {
            $value = (float) $value;
        }

        // Convert to float and validate
        $floatValue = (float) $value;

        // Apply range validation
        if ($this->min !== null && $floatValue < $this->min) {
            throw new \InvalidArgumentException("Value {$floatValue} is below minimum {$this->min}");
        }

        if ($this->max !== null && $floatValue > $this->max) {
            throw new \InvalidArgumentException("Value {$floatValue} is above maximum {$this->max}");
        }

        // Format with specified precision to prevent floating point issues
        return number_format($floatValue, $this->precision, '.', '');
    }

    /**
     * Cast the given value for retrieval
     */
    public function get(Model $model, string $key, $value, array $attributes): ?float
    {
        if ($value === null) {
            return null;
        }

        return (float) $value;
    }
}
