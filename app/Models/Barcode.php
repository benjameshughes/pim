<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Barcode extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'barcode',
        'type',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 📦 VARIANT RELATIONSHIP
     *
     * Each barcode belongs to a specific variant
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * 🏠 PRODUCT ACCESSOR (through variant)
     *
     * Get the product this barcode belongs to via its variant
     */
    public function getProductAttribute()
    {
        return $this->productVariant?->product;
    }

    /**
     * ✅ IS ACTIVE
     *
     * Check if barcode is active
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * 🔍 SCOPE: Active barcodes only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * 🔍 SCOPE: By type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * 🔍 SCOPE: Caecus barcodes
     */
    public function scopeCaecus($query)
    {
        return $query->where('type', 'caecus');
    }

    /**
     * 🔍 SCOPE: System barcodes
     */
    public function scopeSystem($query)
    {
        return $query->where('type', 'system');
    }

    /**
     * 🎯 FIND BY BARCODE
     *
     * Find a barcode by its number and type
     */
    public static function findByBarcode(string $barcode, ?string $type = null)
    {
        $query = static::where('barcode', $barcode);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->first();
    }

    /**
     * 📱 FORMATTED BARCODE
     *
     * Display barcode with type label
     */
    public function getFormattedBarcodeAttribute()
    {
        $typeLabel = match ($this->type) {
            'caecus' => 'Caecus',
            'system' => 'System',
            'ean13' => 'EAN13',
            'upc' => 'UPC',
            default => ucfirst($this->type)
        };

        return "{$typeLabel}: {$this->barcode}";
    }
}
