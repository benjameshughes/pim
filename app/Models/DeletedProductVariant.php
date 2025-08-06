<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletedProductVariant extends Model
{
    // Disable Laravel's default timestamps since we manage deleted_at manually
    public $timestamps = false;

    protected $fillable = [
        'original_product_id',
        'original_variant_id',
        'product_name',
        'product_parent_sku',
        'product_description',
        'variant_sku',
        'color',
        'width',
        'drop',
        'primary_barcode',
        'barcode_type',
        'stock_level',
        'status',
        'deleted_at',
        'deleted_by',
        'deletion_reason',
        'deletion_notes',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
        'stock_level' => 'integer',
    ];

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function getDeletionReasonLabelAttribute(): string
    {
        return match ($this->deletion_reason) {
            'discontinued' => 'Product Discontinued',
            'duplicate' => 'Duplicate Record',
            'data_error' => 'Data Error',
            'customer_request' => 'Customer Request',
            'inventory_cleanup' => 'Inventory Cleanup',
            'other' => 'Other Reason',
            default => 'Unknown'
        };
    }

    public function getVariantDisplayNameAttribute(): string
    {
        $parts = array_filter([$this->color, $this->width, $this->drop]);
        return empty($parts) ? 'No Variants' : implode(' × ', $parts);
    }

    public function getWasParentProductAttribute(): bool
    {
        return is_null($this->product_parent_sku);
    }

    public static function createFromVariant(ProductVariant $variant, string $reason, ?string $notes = null): self
    {
        // Get the primary barcode for this variant
        $primaryBarcode = $variant->barcodes()->where('is_primary', true)->first();
        
        // Get color, width, drop from attributes system
        $color = $variant->attributes()->byKey('color')->first()?->attribute_value;
        $width = $variant->attributes()->byKey('width')->first()?->attribute_value;
        $drop = $variant->attributes()->byKey('drop')->first()?->attribute_value;

        return self::create([
            'original_product_id' => $variant->product_id,
            'original_variant_id' => $variant->id,
            'product_name' => $variant->product->name,
            'product_parent_sku' => $variant->product->parent_sku,
            'product_description' => $variant->product->description,
            'variant_sku' => $variant->sku,
            'color' => $color,
            'width' => $width,
            'drop' => $drop,
            'primary_barcode' => $primaryBarcode?->barcode,
            'barcode_type' => $primaryBarcode?->barcode_type ?? 'EAN13',
            'stock_level' => $variant->stock_level ?? 0,
            'status' => $variant->status,
            'deleted_at' => now(),
            'deleted_by' => auth()->id(),
            'deletion_reason' => $reason,
            'deletion_notes' => $notes,
        ]);
    }

    public static function getAvailableReasons(): array
    {
        return [
            'discontinued' => 'Product Discontinued',
            'duplicate' => 'Duplicate Record',
            'data_error' => 'Data Error',
            'customer_request' => 'Customer Request',
            'inventory_cleanup' => 'Inventory Cleanup',
            'other' => 'Other Reason',
        ];
    }
}