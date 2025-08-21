<?php

namespace App\Services;

use App\Exceptions\BarcodePoolExhaustedException;
use App\Models\BarcodePool;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🏊‍♂️ BARCODE ASSIGNMENT SERVICE - SMART GS1 MANAGEMENT
 *
 * Handles intelligent barcode assignment with:
 * - Smart assignment from row 40,000+ (high-quality pool)
 * - Bulk assignment operations for imports
 * - Pool exhaustion handling and recovery
 * - Assignment tracking and analytics
 * - Performance optimization for large operations
 */
class BarcodeAssignmentService
{
    /**
     * Minimum row number for assignment (user requirement)
     */
    public const ASSIGNMENT_START_ROW = 40000;

    /**
     * Default barcode type
     */
    public const DEFAULT_BARCODE_TYPE = 'EAN13';

    /**
     * Minimum quality score for assignment
     */
    public const MIN_QUALITY_SCORE = 7;

    /**
     * 🎯 SINGLE BARCODE ASSIGNMENT
     *
     * Assign a single barcode to a variant using smart assignment logic
     */
    public function assignToVariant(ProductVariant $variant, string $type = self::DEFAULT_BARCODE_TYPE): BarcodePool
    {
        Log::info("Assigning {$type} barcode to variant", [
            'variant_id' => $variant->id,
            'variant_sku' => $variant->sku,
            'type' => $type,
        ]);

        // Get the next available barcode
        $barcode = $this->getNextAvailableBarcode($type);

        if (! $barcode) {
            throw new BarcodePoolExhaustedException(
                "No available {$type} barcodes in the pool.",
                $type,
                $this->getPoolStatistics($type)
            );
        }

        // Perform the assignment in a transaction
        return DB::transaction(function () use ($barcode, $variant) {
            $success = $barcode->assignTo($variant);

            if (! $success) {
                throw new \RuntimeException("Failed to assign barcode {$barcode->barcode} to variant {$variant->sku}");
            }

            Log::info('Successfully assigned barcode', [
                'barcode' => $barcode->barcode,
                'variant_sku' => $variant->sku,
                'row_number' => $barcode->row_number,
                'quality_score' => $barcode->quality_score,
            ]);

            return $barcode;
        });
    }

    /**
     * 🚀 BULK ASSIGNMENT FOR IMPORTS
     *
     * Assign barcodes to multiple variants efficiently
     */
    public function assignBulkToVariants(Collection $variants, string $type = self::DEFAULT_BARCODE_TYPE): array
    {
        $results = [
            'successful' => [],
            'failed' => [],
            'pool_exhausted' => false,
        ];

        Log::info('Starting bulk barcode assignment', [
            'variant_count' => $variants->count(),
            'type' => $type,
        ]);

        // Pre-check if we have enough available barcodes
        $availableCount = $this->getAvailableCount($type);
        $requiredCount = $variants->count();

        if ($availableCount < $requiredCount) {
            Log::warning('Insufficient barcodes for bulk assignment', [
                'required' => $requiredCount,
                'available' => $availableCount,
                'shortfall' => $requiredCount - $availableCount,
            ]);
        }

        // Get barcodes in bulk for efficiency
        $barcodes = $this->getAvailableBarcodes($type, $requiredCount);

        if ($barcodes->count() < $requiredCount) {
            $results['pool_exhausted'] = true;
        }

        // Assign barcodes to variants
        $variants->zip($barcodes)->each(function ($pair) use (&$results) {
            [$variant, $barcode] = $pair;

            if (! $barcode) {
                $results['failed'][] = [
                    'variant' => $variant,
                    'reason' => 'No barcode available',
                ];

                return;
            }

            try {
                DB::transaction(function () use ($barcode, $variant) {
                    return $barcode->assignTo($variant);
                });

                $results['successful'][] = [
                    'variant' => $variant,
                    'barcode' => $barcode,
                ];

            } catch (\Exception $e) {
                $results['failed'][] = [
                    'variant' => $variant,
                    'barcode' => $barcode,
                    'reason' => $e->getMessage(),
                ];

                Log::error('Failed to assign barcode in bulk operation', [
                    'barcode' => $barcode->barcode,
                    'variant_sku' => $variant->sku,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        Log::info('Completed bulk barcode assignment', [
            'successful' => count($results['successful']),
            'failed' => count($results['failed']),
            'pool_exhausted' => $results['pool_exhausted'],
        ]);

        return $results;
    }

    /**
     * 🎯 GET NEXT AVAILABLE BARCODE
     *
     * Get the next barcode ready for assignment using smart priority logic
     */
    public function getNextAvailableBarcode(string $type = self::DEFAULT_BARCODE_TYPE): ?BarcodePool
    {
        return BarcodePool::readyForAssignment($type)
            ->assignmentPriority()
            ->first();
    }

    /**
     * 📦 GET AVAILABLE BARCODES IN BULK
     *
     * Get multiple barcodes for bulk operations
     */
    public function getAvailableBarcodes(string $type = self::DEFAULT_BARCODE_TYPE, int $count = 100): Collection
    {
        return BarcodePool::readyForAssignment($type)
            ->assignmentPriority()
            ->limit($count)
            ->get();
    }

    /**
     * 📊 GET POOL STATISTICS
     *
     * Get comprehensive pool statistics for a barcode type
     */
    public function getPoolStatistics(string $type = self::DEFAULT_BARCODE_TYPE): array
    {
        $stats = [
            'type' => $type,
            'total' => BarcodePool::byType($type)->count(),
            'available' => BarcodePool::byType($type)->available()->count(),
            'assigned' => BarcodePool::byType($type)->assigned()->count(),
            'reserved' => BarcodePool::byType($type)->reserved()->count(),
            'legacy' => BarcodePool::byType($type)->legacy()->count(),
            'problematic' => BarcodePool::byType($type)->problematic()->count(),
        ];

        // Add ready-for-assignment count (high-quality, non-legacy, from row 40k+)
        $stats['ready_for_assignment'] = BarcodePool::readyForAssignment($type)->count();

        // Calculate quality distribution
        $qualityDistribution = BarcodePool::byType($type)
            ->available()
            ->selectRaw('quality_score, COUNT(*) as count')
            ->groupBy('quality_score')
            ->pluck('count', 'quality_score')
            ->toArray();

        $stats['quality_distribution'] = $qualityDistribution;

        // Calculate assignment rate (percentage of total pool assigned)
        $stats['assignment_rate'] = $stats['total'] > 0
            ? round(($stats['assigned'] / $stats['total']) * 100, 2)
            : 0;

        return $stats;
    }

    /**
     * 🔍 GET AVAILABLE COUNT
     *
     * Quick count of available barcodes for a type
     */
    public function getAvailableCount(string $type = self::DEFAULT_BARCODE_TYPE): int
    {
        return BarcodePool::readyForAssignment($type)->count();
    }

    /**
     * 🏊‍♂️ RESERVE BARCODE RANGE
     *
     * Reserve a range of barcodes for specific use (e.g., product line)
     */
    public function reserveRange(int $count, string $type = self::DEFAULT_BARCODE_TYPE, ?string $reason = null): int
    {
        Log::info('Reserving barcode range', [
            'count' => $count,
            'type' => $type,
            'reason' => $reason,
        ]);

        $reserved = BarcodePool::reserveRange($count, $type, $reason);

        Log::info('Reserved barcode range', [
            'requested' => $count,
            'reserved' => $reserved,
            'type' => $type,
        ]);

        return $reserved;
    }

    /**
     * 🔄 RELEASE BARCODE
     *
     * Release a barcode back to the pool (make it available again)
     */
    public function releaseBarcode(string $barcode, string $type = self::DEFAULT_BARCODE_TYPE): bool
    {
        $barcodeRecord = BarcodePool::where('barcode', $barcode)
            ->where('barcode_type', $type)
            ->first();

        if (! $barcodeRecord) {
            Log::warning('Attempted to release non-existent barcode', [
                'barcode' => $barcode,
                'type' => $type,
            ]);

            return false;
        }

        $success = $barcodeRecord->release();

        if ($success) {
            Log::info('Released barcode back to pool', [
                'barcode' => $barcode,
                'type' => $type,
                'row_number' => $barcodeRecord->row_number,
            ]);
        }

        return $success;
    }

    /**
     * 🔍 CHECK POOL HEALTH
     *
     * Check if the barcode pool is healthy for assignment operations
     */
    public function checkPoolHealth(string $type = self::DEFAULT_BARCODE_TYPE): array
    {
        $stats = $this->getPoolStatistics($type);

        $health = [
            'type' => $type,
            'is_healthy' => true,
            'warnings' => [],
            'recommendations' => [],
            'available_count' => $stats['ready_for_assignment'],
            'total_count' => $stats['total'],
        ];

        // Check for low availability
        if ($stats['ready_for_assignment'] < 100) {
            $health['is_healthy'] = false;
            $health['warnings'][] = "Low barcode availability: only {$stats['ready_for_assignment']} barcodes ready for assignment";
            $health['recommendations'][] = 'Import additional barcodes or review pool configuration';
        }

        // Check for high assignment rate
        if ($stats['assignment_rate'] > 90) {
            $health['warnings'][] = "High assignment rate: {$stats['assignment_rate']}% of pool is assigned";
            $health['recommendations'][] = 'Consider expanding barcode pool or reviewing assignment patterns';
        }

        // Check for problematic barcodes
        if ($stats['problematic'] > 0) {
            $health['warnings'][] = "{$stats['problematic']} barcodes marked as problematic";
            $health['recommendations'][] = 'Review and resolve problematic barcodes';
        }

        return $health;
    }

    /**
     * 🎨 GET ASSIGNMENT HISTORY
     *
     * Get recent assignment history for monitoring
     */
    public function getRecentAssignments(string $type = self::DEFAULT_BARCODE_TYPE, int $days = 7): Collection
    {
        return BarcodePool::byType($type)
            ->assigned()
            ->where('assigned_at', '>=', now()->subDays($days))
            ->with('assignedVariant.product')
            ->orderByDesc('assigned_at')
            ->get();
    }

    /**
     * 🚀 BATCH IMPORT HOOK
     *
     * Hook for handling barcode assignment during batch imports
     * This integrates with the existing import system
     */
    public function handleImportBatch(Collection $variants, array $options = []): array
    {
        $barcodeType = $options['barcode_type'] ?? self::DEFAULT_BARCODE_TYPE;
        $skipAssignment = $options['skip_barcode_assignment'] ?? false;

        if ($skipAssignment) {
            return ['message' => 'Barcode assignment skipped per configuration'];
        }

        Log::info('Handling barcode assignment for import batch', [
            'variant_count' => $variants->count(),
            'barcode_type' => $barcodeType,
        ]);

        // Filter variants that don't already have barcodes
        $variantsNeedingBarcodes = $variants->filter(function ($variant) {
            return $variant->barcodes()->count() === 0;
        });

        if ($variantsNeedingBarcodes->isEmpty()) {
            return ['message' => 'All variants already have barcodes assigned'];
        }

        return $this->assignBulkToVariants($variantsNeedingBarcodes, $barcodeType);
    }
}
