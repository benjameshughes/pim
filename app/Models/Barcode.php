<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barcode extends Model
{
    protected $fillable = [
        'barcode',
        'sku',
        'title',
        'product_variant_id',
        'is_assigned',
    ];

    protected $casts = [
        'is_assigned' => 'boolean',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
