<?php

namespace App\Services;

use App\Models\BarcodePool;

class BarcodePoolOptimizationService
{
    /**
     * Generate test data for performance testing
     */
    public static function generateTestData(int $count = 10000): array
    {
        $barcodes = [];
        $batchId = 'perf_test_'.now()->timestamp;

        // Get the highest existing barcode to avoid conflicts
        $lastBarcode = BarcodePool::where('barcode', 'like', '590123%')
            ->orderBy('barcode', 'desc')
            ->value('barcode');

        $startIndex = 1;
        if ($lastBarcode && preg_match('/590123(\d{6})4/', $lastBarcode, $matches)) {
            $startIndex = (int) $matches[1] + 1;
        }

        for ($i = 0; $i < $count; $i++) {
            $index = $startIndex + $i;
            // Generate unique EAN13 barcodes with timestamp prefix to avoid conflicts
            $barcode = '590123'.str_pad($index, 6, '0', STR_PAD_LEFT).'4';

            $barcodes[] = [
                'barcode' => $barcode,
                'barcode_type' => 'EAN13',
                'status' => 'available',
                'import_batch_id' => $batchId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insert in chunks to avoid memory issues
            if (count($barcodes) >= 1000) {
                BarcodePool::insert($barcodes);
                $barcodes = [];
            }
        }

        // Insert remaining records
        if (! empty($barcodes)) {
            BarcodePool::insert($barcodes);
        }

        return [
            'created' => $count,
            'batch_id' => $batchId,
        ];
    }

    /**
     * Clean up test data
     */
    public static function cleanupTestData(string $batchId): int
    {
        return BarcodePool::where('import_batch_id', $batchId)->delete();
    }

    /**
     * Memory usage analysis for the barcode pool page
     */
    public static function analyzeMemoryUsage(int $limit = 50): array
    {
        $startMemory = memory_get_usage(true);
        $startPeak = memory_get_peak_usage(true);

        // Simulate the optimized query
        $query = BarcodePool::query()
            ->select([
                'barcode_pools.id',
                'barcode_pools.barcode',
                'barcode_pools.barcode_type',
                'barcode_pools.status',
                'barcode_pools.assigned_to_variant_id',
                'barcode_pools.assigned_at',
            ])
            ->orderBy('id');

        $afterQueryMemory = memory_get_usage(true);

        // Test cursor pagination
        $barcodes = $query->cursorPaginate($limit);

        $afterPaginationMemory = memory_get_usage(true);

        // Test relationship loading
        $barcodes->load([
            'assignedVariant:id,product_id,sku',
            'assignedVariant.product:id,name',
        ]);

        $finalMemory = memory_get_usage(true);
        $finalPeak = memory_get_peak_usage(true);

        return [
            'total_records' => BarcodePool::count(),
            'paginated_records' => $barcodes->count(),
            'memory_usage' => [
                'start_mb' => round($startMemory / 1024 / 1024, 2),
                'after_query_mb' => round($afterQueryMemory / 1024 / 1024, 2),
                'after_pagination_mb' => round($afterPaginationMemory / 1024 / 1024, 2),
                'final_mb' => round($finalMemory / 1024 / 1024, 2),
                'peak_mb' => round($finalPeak / 1024 / 1024, 2),
                'query_overhead_mb' => round(($afterQueryMemory - $startMemory) / 1024 / 1024, 2),
                'pagination_overhead_mb' => round(($afterPaginationMemory - $afterQueryMemory) / 1024 / 1024, 2),
                'relationships_overhead_mb' => round(($finalMemory - $afterPaginationMemory) / 1024 / 1024, 2),
            ],
            'efficiency_score' => self::calculateEfficiencyScore($finalMemory - $startMemory, $barcodes->count()),
        ];
    }

    /**
     * Calculate efficiency score based on memory per record
     */
    private static function calculateEfficiencyScore(int $memoryUsed, int $recordCount): string
    {
        if ($recordCount === 0) {
            return 'N/A';
        }

        $memoryPerRecord = ($memoryUsed / 1024) / $recordCount; // KB per record

        if ($memoryPerRecord < 0.5) {
            return 'Excellent';
        }
        if ($memoryPerRecord < 2) {
            return 'Good';
        }
        if ($memoryPerRecord < 5) {
            return 'Average';
        }

        return 'Poor';
    }

    /**
     * Generate performance report
     */
    public static function generatePerformanceReport(): array
    {
        $analysis = self::analyzeMemoryUsage();

        return [
            'timestamp' => now()->toISOString(),
            'database_size' => $analysis['total_records'],
            'memory_analysis' => $analysis['memory_usage'],
            'efficiency_score' => $analysis['efficiency_score'],
            'recommendations' => self::getRecommendations($analysis),
        ];
    }

    /**
     * Get optimization recommendations based on analysis
     */
    private static function getRecommendations(array $analysis): array
    {
        $recommendations = [];

        if ($analysis['memory_usage']['final_mb'] > 50) {
            $recommendations[] = 'Consider reducing page size or implementing server-side filtering';
        }

        if ($analysis['memory_usage']['relationships_overhead_mb'] > 10) {
            $recommendations[] = 'Relationship loading is consuming significant memory - consider lazy loading strategies';
        }

        if ($analysis['memory_usage']['pagination_overhead_mb'] > 20) {
            $recommendations[] = 'Pagination overhead is high - cursor pagination is recommended for large datasets';
        }

        if ($analysis['efficiency_score'] === 'Poor') {
            $recommendations[] = 'Memory efficiency is poor - review query optimization and consider caching strategies';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Performance is optimal for current dataset size';
        }

        return $recommendations;
    }
}
