<?php

namespace App\Actions\Barcodes;

use App\Actions\Base\BaseAction;
use App\Models\BarcodePool;

/**
 * 🔍 CHECK BARCODE AVAILABILITY ACTION
 *
 * Check barcode pool status and availability with:
 * - Available count by type and quality
 * - Pool health metrics
 * - Assignment readiness checks
 * - Legacy vs active breakdown
 */
class CheckBarcodeAvailabilityAction extends BaseAction
{
    /**
     * Check barcode pool availability and status
     *
     * @param string $type Barcode type to check (default: EAN13)
     * @param int $minQuality Minimum quality score (default: 7)
     * @return array Action result with availability metrics
     */
    protected function performAction(...$params): array
    {
        $type = $params[0] ?? 'EAN13';
        $minQuality = $params[1] ?? 7;

        // Get comprehensive pool statistics
        $stats = [
            'barcode_type' => $type,
            'min_quality_score' => $minQuality,
            
            // Ready for assignment (the key metric)
            'ready_for_assignment' => BarcodePool::readyForAssignment($type)
                ->highQuality($minQuality)
                ->count(),
            
            // Breakdown by status
            'available_total' => BarcodePool::available()->byType($type)->count(),
            'assigned_total' => BarcodePool::assigned()->byType($type)->count(),
            'reserved_total' => BarcodePool::reserved()->byType($type)->count(),
            'legacy_archive_total' => BarcodePool::legacyArchive()->byType($type)->count(),
            'problematic_total' => BarcodePool::problematic()->byType($type)->count(),
            
            // Quality breakdown for available barcodes
            'available_high_quality' => BarcodePool::available()
                ->byType($type)
                ->highQuality($minQuality)
                ->count(),
            
            'available_low_quality' => BarcodePool::available()
                ->byType($type)
                ->where('quality_score', '<', $minQuality)
                ->count(),
            
            // Legacy vs active breakdown
            'active_pool_size' => BarcodePool::active()->byType($type)->count(),
            'legacy_pool_size' => BarcodePool::legacy()->byType($type)->count(),
            
            // Row number insights
            'assignment_range_available' => BarcodePool::available()
                ->byType($type)
                ->where('row_number', '>=', 40000)
                ->count(),
        ];

        // Calculate derived metrics
        $stats['total_pool_size'] = array_sum([
            $stats['available_total'],
            $stats['assigned_total'], 
            $stats['reserved_total'],
            $stats['legacy_archive_total'],
            $stats['problematic_total']
        ]);

        $stats['utilization_rate'] = $stats['total_pool_size'] > 0 
            ? round(($stats['assigned_total'] / $stats['total_pool_size']) * 100, 2)
            : 0;

        // Health indicators
        $stats['health'] = [
            'sufficient_supply' => $stats['ready_for_assignment'] > 1000,
            'low_supply_warning' => $stats['ready_for_assignment'] < 100,
            'critical_supply' => $stats['ready_for_assignment'] < 10,
            'quality_ratio_good' => $stats['available_total'] > 0 
                ? ($stats['available_high_quality'] / $stats['available_total']) > 0.8
                : false,
        ];

        // Next available barcode preview
        $nextAvailable = BarcodePool::readyForAssignment($type)
            ->highQuality($minQuality)
            ->assignmentPriority()
            ->first();

        $stats['next_available'] = $nextAvailable ? [
            'barcode' => $nextAvailable->barcode,
            'quality_score' => $nextAvailable->quality_score,
            'row_number' => $nextAvailable->row_number,
        ] : null;

        return [
            'statistics' => $stats,
            'availability_status' => $this->determineAvailabilityStatus($stats),
            'message' => $this->buildAvailabilityMessage($stats)
        ];
    }

    /**
     * Determine overall availability status
     */
    private function determineAvailabilityStatus(array $stats): string
    {
        if ($stats['health']['critical_supply']) {
            return 'critical';
        }

        if ($stats['health']['low_supply_warning']) {
            return 'low';
        }

        if ($stats['health']['sufficient_supply']) {
            return 'excellent';
        }

        return 'good';
    }

    /**
     * Build human-readable availability message
     */
    private function buildAvailabilityMessage(array $stats): string
    {
        $ready = $stats['ready_for_assignment'];
        $type = $stats['barcode_type'];
        
        if ($ready === 0) {
            return "❌ No {$type} barcodes ready for assignment. Pool exhausted or quality too low.";
        }

        if ($ready < 10) {
            return "⚠️ Critical: Only {$ready} {$type} barcodes ready for assignment.";
        }

        if ($ready < 100) {
            return "🟡 Low supply: {$ready} {$type} barcodes ready for assignment.";
        }

        if ($ready < 1000) {
            return "🟢 Good supply: {$ready} {$type} barcodes ready for assignment.";
        }

        return "🚀 Excellent supply: {$ready} {$type} barcodes ready for assignment.";
    }
}