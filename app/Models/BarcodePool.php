<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 🏊‍♂️ BARCODE POOL MODEL - GS1 BARCODE MANAGEMENT SYSTEM
 *
 * Manages the comprehensive pool of GS1 barcodes with:
 * - Smart assignment logic starting from row 40,000+
 * - Legacy/historical data preservation
 * - Assignment tracking and quality management
 * - Performance optimization for large datasets
 */
class BarcodePool extends Model
{
    use HasFactory;

    protected $table = 'barcode_pool';

    protected $fillable = [
        'barcode',
        'barcode_type',
        'status',
        'is_legacy',
        'row_number',
        'quality_score',
        'assigned_to_variant_id',
        'assigned_at',
        'date_first_used',
        'import_batch_id',
        'legacy_sku',
        'legacy_status',
        'legacy_product_name',
        'legacy_brand',
        'legacy_updated',
        'legacy_notes',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'is_legacy' => 'boolean',
        'quality_score' => 'integer',
        'row_number' => 'integer',
        'assigned_at' => 'datetime',
        'date_first_used' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 📦 VARIANT RELATIONSHIP
     *
     * The variant this barcode is assigned to (nullable)
     */
    public function assignedVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'assigned_to_variant_id');
    }

    /**
     * 🏠 PRODUCT ACCESSOR (through assigned variant)
     */
    public function getProductAttribute()
    {
        return $this->assignedVariant?->product;
    }

    // ================================
    // 🎯 ASSIGNMENT STATUS SCOPES
    // ================================

    /**
     * Scope: Available barcodes for assignment
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope: Assigned barcodes
     */
    public function scopeAssigned(Builder $query): Builder
    {
        return $query->where('status', 'assigned');
    }

    /**
     * Scope: Reserved barcodes (held but not assigned)
     */
    public function scopeReserved(Builder $query): Builder
    {
        return $query->where('status', 'reserved');
    }

    /**
     * Scope: Legacy archived barcodes (historical data)
     */
    public function scopeLegacyArchive(Builder $query): Builder
    {
        return $query->where('status', 'legacy_archive');
    }

    /**
     * Scope: Problematic barcodes (quality issues)
     */
    public function scopeProblematic(Builder $query): Builder
    {
        return $query->where('status', 'problematic');
    }

    // ================================
    // 🎨 QUALITY AND LEGACY SCOPES
    // ================================

    /**
     * Scope: Non-legacy barcodes only
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_legacy', false);
    }

    /**
     * Scope: Legacy barcodes only
     */
    public function scopeLegacy(Builder $query): Builder
    {
        return $query->where('is_legacy', true);
    }

    /**
     * Scope: High quality barcodes (quality score >= threshold)
     */
    public function scopeHighQuality(Builder $query, int $threshold = 8): Builder
    {
        return $query->where('quality_score', '>=', $threshold);
    }

    /**
     * Scope: By barcode type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('barcode_type', $type);
    }

    // ================================
    // 🚀 SMART ASSIGNMENT SCOPES
    // ================================

    /**
     * Scope: Ready for assignment (available, non-legacy, from row 40,000+)
     * This is the primary scope for barcode assignment
     */
    public function scopeReadyForAssignment(Builder $query, string $type = 'EAN13'): Builder
    {
        return $query->where('status', 'available')
            ->where('barcode_type', $type)
            ->where('is_legacy', false)
            ->where('row_number', '>=', 40000); // Start from row 40,000 as requested
    }

    /**
     * Scope: Assignment priority order (quality desc, row number asc)
     */
    public function scopeAssignmentPriority(Builder $query): Builder
    {
        return $query->orderByDesc('quality_score')
            ->orderBy('row_number');
    }

    /**
     * Scope: From specific import batch
     */
    public function scopeFromBatch(Builder $query, string $batchId): Builder
    {
        return $query->where('import_batch_id', $batchId);
    }

    // ================================
    // 🎯 ASSIGNMENT METHODS
    // ================================

    /**
     * Assign this barcode to a variant
     */
    public function assignTo(ProductVariant $variant): bool
    {
        if ($this->status !== 'available') {
            return false;
        }

        $this->update([
            'status' => 'assigned',
            'assigned_to_variant_id' => $variant->id,
            'assigned_at' => now(),
            'date_first_used' => $this->date_first_used ?? now(), // Preserve original if exists
        ]);

        // Create the barcode assignment record in the existing barcodes table
        Barcode::create([
            'product_variant_id' => $variant->id,
            'barcode' => $this->barcode,
            'type' => $this->barcode_type,
            'status' => 'active',
        ]);

        return true;
    }

    /**
     * Release this barcode (make available again)
     */
    public function release(): bool
    {
        if ($this->status !== 'assigned') {
            return false;
        }

        // Remove from barcodes table
        Barcode::where('barcode', $this->barcode)
            ->where('product_variant_id', $this->assigned_to_variant_id)
            ->delete();

        $this->update([
            'status' => 'available',
            'assigned_to_variant_id' => null,
            'assigned_at' => null,
            // Keep date_first_used for historical tracking
        ]);

        return true;
    }

    // ================================
    // 🎨 STATIC ASSIGNMENT METHODS
    // ================================

    /**
     * Get next available barcode for assignment
     */
    public static function getNextAvailable(string $type = 'EAN13'): ?self
    {
        return static::readyForAssignment($type)
            ->assignmentPriority()
            ->first();
    }

    /**
     * Bulk reserve barcodes for a specific purpose
     */
    public static function reserveRange(int $count, string $type = 'EAN13', ?string $notes = null): int
    {
        $barcodes = static::readyForAssignment($type)
            ->assignmentPriority()
            ->limit($count)
            ->get();

        $reserved = 0;
        foreach ($barcodes as $barcode) {
            if ($barcode->update(['status' => 'reserved', 'notes' => $notes])) {
                $reserved++;
            }
        }

        return $reserved;
    }

    // ================================
    // 🎨 STATUS CHECKERS
    // ================================

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isAssigned(): bool
    {
        return $this->status === 'assigned';
    }

    public function isLegacy(): bool
    {
        return $this->is_legacy;
    }

    public function isHighQuality(int $threshold = 8): bool
    {
        return ($this->quality_score ?? 0) >= $threshold;
    }

    public function isReadyForAssignment(): bool
    {
        return $this->isAvailable() &&
               ! $this->isLegacy() &&
               ($this->row_number >= 40000);
    }

    // ================================
    // 🎨 DISPLAY METHODS
    // ================================

    /**
     * Get formatted barcode with type
     */
    public function getFormattedBarcodeAttribute(): string
    {
        return "{$this->barcode_type}: {$this->barcode}";
    }

    /**
     * Get status badge color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'available' => 'green',
            'assigned' => 'blue',
            'reserved' => 'yellow',
            'legacy_archive' => 'gray',
            'problematic' => 'red',
            default => 'gray'
        };
    }

    /**
     * Get human-readable status
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'available' => 'Available',
            'assigned' => 'Assigned',
            'reserved' => 'Reserved',
            'legacy_archive' => 'Legacy Archive',
            'problematic' => 'Problematic',
            default => ucfirst($this->status)
        };
    }
}
