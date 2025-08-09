<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttributeDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'data_type',
        'category',
        'applies_to',
        'is_required',
        'validation_rules',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'validation_rules' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForProducts($query)
    {
        return $query->whereIn('applies_to', ['product', 'both']);
    }

    public function scopeForVariants($query)
    {
        return $query->whereIn('applies_to', ['variant', 'both']);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }

    public function validateValue($value): bool
    {
        if (! $this->validation_rules) {
            return true;
        }

        $rules = $this->validation_rules;

        // Type validation
        if (! $this->validateType($value)) {
            return false;
        }

        // Custom validation rules
        if (isset($rules['min']) && is_numeric($value) && $value < $rules['min']) {
            return false;
        }

        if (isset($rules['max']) && is_numeric($value) && $value > $rules['max']) {
            return false;
        }

        if (isset($rules['options']) && ! in_array($value, $rules['options'])) {
            return false;
        }

        return true;
    }

    private function validateType($value): bool
    {
        return match ($this->data_type) {
            'number' => is_numeric($value),
            'boolean' => is_bool($value) || in_array(strtolower($value), ['true', 'false', '1', '0']),
            'json' => is_array($value) || is_string($value) && json_decode($value) !== null,
            default => true, // string accepts anything
        };
    }
}
