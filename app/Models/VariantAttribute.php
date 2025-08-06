<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantAttribute extends Model
{
    protected $fillable = [
        'variant_id',
        'attribute_key',
        'attribute_value',
        'data_type',
        'category',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function getTypedValueAttribute()
    {
        return match ($this->data_type) {
            'number' => is_numeric($this->attribute_value) ? (float) $this->attribute_value : $this->attribute_value,
            'boolean' => filter_var($this->attribute_value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->attribute_value, true),
            default => $this->attribute_value,
        };
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('attribute_key', $key);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public static function setValue(int $variantId, string $key, $value, string $dataType = 'string', ?string $category = null): self
    {
        $attributeValue = match ($dataType) {
            'json' => json_encode($value),
            'boolean' => $value ? 'true' : 'false',
            default => (string) $value,
        };

        return static::updateOrCreate(
            ['variant_id' => $variantId, 'attribute_key' => $key],
            [
                'attribute_value' => $attributeValue,
                'data_type' => $dataType,
                'category' => $category,
            ]
        );
    }
}
