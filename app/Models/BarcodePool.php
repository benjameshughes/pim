<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class BarcodePool extends Model
{
    use HasFactory;

    protected $fillable = [
        'barcode',
        'barcode_type',
        'status',
        'assigned_to_variant_id',
        'assigned_at',
        'notes',
        'legacy_notes',
        'date_first_used',
        'is_legacy',
        'import_batch_id',
    ];

    protected $casts = [
        'assigned_at' => 'timestamp',
        'date_first_used' => 'timestamp',
        'is_legacy' => 'boolean',
    ];

    protected static function booted()
    {
        static::saved(function () {
            cache()->forget('barcode_pool_stats');
        });

        static::deleted(function () {
            cache()->forget('barcode_pool_stats');
        });
    }

    public const STATUSES = [
        'available' => 'Available',
        'assigned' => 'Assigned',
        'reserved' => 'Reserved',
        'legacy_archive' => 'Legacy Archive',
    ];

    public function assignedVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'assigned_to_variant_id');
    }

    /**
     * Get the next available barcode from the pool (excludes legacy archive)
     */
    public static function getNextAvailable(string $type = 'EAN13'): ?self
    {
        return self::where('status', 'available')
            ->where('barcode_type', $type)
            ->where('is_legacy', false)
            ->orderBy('id')
            ->first();
    }

    /**
     * Assign this barcode to a variant
     */
    public function assignToVariant(ProductVariant $variant): bool
    {
        if ($this->status !== 'available') {
            return false;
        }

        return DB::transaction(function () use ($variant) {
            // Update barcode pool
            $this->update([
                'status' => 'assigned',
                'assigned_to_variant_id' => $variant->id,
                'assigned_at' => now(),
            ]);

            // Create or update the barcode record
            $existingBarcode = $variant->barcodes()
                ->where('barcode_type', $this->barcode_type)
                ->first();

            if ($existingBarcode) {
                $existingBarcode->update([
                    'barcode' => $this->barcode,
                ]);
            } else {
                Barcode::create([
                    'product_variant_id' => $variant->id,
                    'barcode' => $this->barcode,
                    'barcode_type' => $this->barcode_type,
                    'is_primary' => $variant->barcodes()->count() === 0,
                ]);
            }

            return true;
        });
    }

    /**
     * Release this barcode back to the pool
     */
    public function release(): bool
    {
        if ($this->status !== 'assigned') {
            return false;
        }

        return DB::transaction(function () {
            // Remove from variant barcodes
            if ($this->assigned_to_variant_id) {
                Barcode::where('product_variant_id', $this->assigned_to_variant_id)
                    ->where('barcode', $this->barcode)
                    ->delete();
            }

            // Update pool status
            $this->update([
                'status' => 'available',
                'assigned_to_variant_id' => null,
                'assigned_at' => null,
            ]);

            return true;
        });
    }

    /**
     * Import barcodes from array
     */
    public static function importBarcodes(array $barcodes, string $type = 'EAN13'): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($barcodes as $barcode) {
            try {
                $barcode = trim($barcode);

                if (empty($barcode)) {
                    $skipped++;

                    continue;
                }

                // Check if barcode already exists
                if (self::where('barcode', $barcode)->exists()) {
                    $skipped++;

                    continue;
                }

                self::create([
                    'barcode' => $barcode,
                    'barcode_type' => $type,
                    'status' => 'available',
                ]);

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Error importing barcode {$barcode}: ".$e->getMessage();
                $skipped++;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Get pool statistics including legacy archive data (cached for performance)
     */
    public static function getStats(): array
    {
        return cache()->remember('barcode_pool_stats', 300, function () { // 5 minute cache
            // Use raw queries for better performance with large datasets
            $totalStats = DB::table('barcode_pools')
                ->selectRaw('
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = "available" AND is_legacy = 0 THEN 1 END) as available,
                    COUNT(CASE WHEN status = "assigned" THEN 1 END) as assigned,
                    COUNT(CASE WHEN status = "reserved" THEN 1 END) as reserved,
                    COUNT(CASE WHEN status = "legacy_archive" THEN 1 END) as legacy_archive
                ')
                ->first();

            $byType = DB::table('barcode_pools')
                ->select('barcode_type', DB::raw('count(*) as count'))
                ->groupBy('barcode_type')
                ->pluck('count', 'barcode_type')
                ->toArray();

            $byStatus = DB::table('barcode_pools')
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            return [
                'total' => $totalStats->total ?? 0,
                'available' => $totalStats->available ?? 0,
                'assigned' => $totalStats->assigned ?? 0,
                'reserved' => $totalStats->reserved ?? 0,
                'legacy_archive' => $totalStats->legacy_archive ?? 0,
                'by_type' => $byType,
                'by_status' => $byStatus,
            ];
        });
    }

    /**
     * Get import batch statistics
     */
    public static function getBatchStats(?string $batchId = null): array
    {
        $query = self::query();

        if ($batchId) {
            $query->where('import_batch_id', $batchId);
        }

        return [
            'batches' => self::whereNotNull('import_batch_id')
                ->select('import_batch_id', DB::raw('count(*) as count'), 'created_at')
                ->groupBy('import_batch_id', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray(),
            'total_in_batch' => $batchId ? $query->count() : 0,
            'legacy_in_batch' => $batchId ? $query->where('is_legacy', true)->count() : 0,
            'available_in_batch' => $batchId ? $query->where('status', 'available')->count() : 0,
        ];
    }

    /**
     * Scope for non-legacy barcodes only
     */
    public function scopeNonLegacy($query)
    {
        return $query->where('is_legacy', false);
    }

    /**
     * Scope for legacy archive barcodes only
     */
    public function scopeLegacyArchive($query)
    {
        return $query->where('is_legacy', true);
    }

    /**
     * Scope for specific import batch
     */
    public function scopeFromBatch($query, string $batchId)
    {
        return $query->where('import_batch_id', $batchId);
    }

    /**
     * Check if barcode can be assigned (not legacy)
     */
    public function canBeAssigned(): bool
    {
        return $this->status === 'available' && ! $this->is_legacy;
    }

    /**
     * Mark barcode as first used
     */
    public function markFirstUsed(): bool
    {
        if ($this->date_first_used === null) {
            return $this->update(['date_first_used' => now()]);
        }

        return true;
    }
}
