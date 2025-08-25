<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 📦 STOCK MODEL - Independent Stock Management
 *
 * Stock operates independently but references variants
 */
class Stock extends Model
{
    use HasFactory;

    protected $table = 'stock';

    protected $fillable = [
        'product_variant_id',
        'quantity',
        'reserved',
        'incoming',
        'minimum_level',
        'maximum_level',
        'location',
        'bin_location',
        'status',
        'track_stock',
        'last_counted_at',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved' => 'integer',
        'incoming' => 'integer',
        'minimum_level' => 'integer',
        'maximum_level' => 'integer',
        'track_stock' => 'boolean',
        'last_counted_at' => 'datetime',
    ];

    // Set defaults in code, not database
    protected $attributes = [
        'status' => 'available',
        'track_stock' => true,
        'quantity' => 0,
    ];

    /**
     * 🔗 VARIANT REFERENCE
     * Stock references variant but doesn't belong to it conceptually
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * 🎯 SCOPES
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeTracked($query)
    {
        return $query->where('track_stock', true);
    }

    public function scopeForLocation($query, $location)
    {
        return $query->where('location', $location);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity', '<=', 'minimum_level')
            ->whereNotNull('minimum_level');
    }

    /**
     * 🧮 STOCK CALCULATIONS
     */
    public function getAvailableQuantity(): int
    {
        return max(0, $this->quantity - ($this->reserved ?? 0));
    }

    public function getTotalExpected(): int
    {
        return $this->quantity + ($this->incoming ?? 0);
    }

    public function isLowStock(): bool
    {
        return $this->minimum_level && $this->quantity <= $this->minimum_level;
    }

    public function isOverStock(): bool
    {
        return $this->maximum_level && $this->quantity > $this->maximum_level;
    }

    public function isInStock(): bool
    {
        return $this->getAvailableQuantity() > 0;
    }

    /**
     * 📊 STOCK OPERATIONS
     */
    public function adjustStock(int $adjustment, ?string $reason = null): self
    {
        $this->quantity += $adjustment;

        if ($reason) {
            $adjustmentText = $adjustment >= 0 ? "+{$adjustment}" : (string) $adjustment;
            $this->notes = ($this->notes ? $this->notes."\n" : '').
                          now()->format('Y-m-d H:i').": {$reason} ({$adjustmentText})";
        }

        $this->save();

        return $this;
    }

    public function setStock(int $newQuantity, ?string $reason = null): self
    {
        $oldQuantity = $this->quantity;
        $this->quantity = $newQuantity;

        if ($reason) {
            $this->notes = ($this->notes ? $this->notes."\n" : '').
                          now()->format('Y-m-d H:i').": {$reason} ({$oldQuantity} → {$newQuantity})";
        }

        $this->save();

        return $this;
    }

    public function reserveStock(int $quantity): bool
    {
        if ($this->getAvailableQuantity() >= $quantity) {
            $this->reserved = ($this->reserved ?? 0) + $quantity;
            $this->save();

            return true;
        }

        return false;
    }

    public function releaseReserved(int $quantity): self
    {
        $this->reserved = max(0, ($this->reserved ?? 0) - $quantity);
        $this->save();

        return $this;
    }
}
