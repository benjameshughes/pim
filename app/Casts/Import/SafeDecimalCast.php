<?php

namespace App\Casts\Import;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Safe decimal casting for import data
 * Handles numeric validation, precision, and range checking
 */
class SafeDecimalCast implements CastsAttributes
{
    private int $precision;
    private int $scale;
    private ?float $minValue;
    private ?float $maxValue;
    private float $defaultValue;
    
    public function __construct(
        int $precision = 10, 
        int $scale = 2, 
        ?float $minValue = null, 
        ?float $maxValue = null,
        float $defaultValue = 0.0
    ) {
        $this->precision = $precision;
        $this->scale = $scale;
        $this->minValue = $minValue;
        $this->maxValue = $maxValue;
        $this->defaultValue = $defaultValue;
    }
    
    /**
     * Cast the given value when retrieving from database
     */
    public function get(Model $model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        
        return number_format((float) $value, $this->scale, '.', '');
    }
    
    /**
     * Cast the given value when storing to database
     */
    public function set(Model $model, string $key, $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        // Handle string values that might contain formatting
        if (is_string($value)) {
            // Remove common currency symbols and thousands separators
            $value = preg_replace('/[£$€,\s]/', '', $value);
            
            // Handle percentage values
            if (str_ends_with($value, '%')) {
                $value = rtrim($value, '%');
                $value = (float) $value / 100;
            }
        }
        
        // Convert to float
        if (!is_numeric($value)) {
            Log::warning('Non-numeric value provided for decimal cast, using default', [
                'model' => get_class($model),
                'key' => $key,
                'value' => $value,
                'default' => $this->defaultValue
            ]);
            $value = $this->defaultValue;
        }
        
        $floatValue = (float) $value;
        
        // Apply range validation
        if ($this->minValue !== null && $floatValue < $this->minValue) {
            Log::info('Value below minimum, clamping', [
                'model' => get_class($model),
                'key' => $key,
                'value' => $floatValue,
                'min' => $this->minValue
            ]);
            $floatValue = $this->minValue;
        }
        
        if ($this->maxValue !== null && $floatValue > $this->maxValue) {
            Log::info('Value above maximum, clamping', [
                'model' => get_class($model),
                'key' => $key,
                'value' => $floatValue,
                'max' => $this->maxValue
            ]);
            $floatValue = $this->maxValue;
        }
        
        // Round to specified precision
        $roundedValue = round($floatValue, $this->scale);
        
        // Check total precision (digits before and after decimal)
        $totalDigits = strlen(str_replace('.', '', (string) $roundedValue));
        if ($totalDigits > $this->precision) {
            Log::warning('Value exceeds precision limit, truncating', [
                'model' => get_class($model),
                'key' => $key,
                'value' => $roundedValue,
                'precision' => $this->precision
            ]);
            
            $maxValue = pow(10, $this->precision - $this->scale) - pow(10, -$this->scale);
            $roundedValue = min($roundedValue, $maxValue);
        }
        
        return number_format($roundedValue, $this->scale, '.', '');
    }
}