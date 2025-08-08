<?php

namespace App\Services;

use App\Models\BarcodePool;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Variant Performance Service
 * 
 * Handles performance optimizations for variant creation and management
 */
class VariantPerformanceService
{
    /**
     * Warm up frequently accessed caches
     */
    public static function warmupCaches(): void
    {
        // Warm up barcode pool counts for all types
        $barcodeTypes = ['EAN13', 'UPC', 'CODE128', 'CODE39'];
        
        foreach ($barcodeTypes as $type) {
            Cache::remember("barcode_count_{$type}", 300, function () use ($type) {
                return BarcodePool::where('status', 'available')
                    ->where('barcode_type', $type)
                    ->count();
            });
        }
        
        // Warm up variant count per product
        Cache::remember('total_variant_count', 300, function () {
            return ProductVariant::count();
        });
    }
    
    /**
     * Get cached barcode pool stats
     */
    public static function getBarcodePoolStats(string $type = null): array
    {
        if ($type) {
            return [
                'available' => Cache::remember("barcode_count_{$type}", 300, function () use ($type) {
                    return BarcodePool::where('status', 'available')
                        ->where('barcode_type', $type)
                        ->count();
                }),
                'assigned' => Cache::remember("barcode_assigned_{$type}", 300, function () use ($type) {
                    return BarcodePool::where('status', 'assigned')
                        ->where('barcode_type', $type)
                        ->count();
                }),
            ];
        }
        
        return Cache::remember('barcode_pool_stats_all', 300, function () {
            return DB::table('barcode_pools')
                ->select('barcode_type', 'status', DB::raw('count(*) as count'))
                ->groupBy('barcode_type', 'status')
                ->get()
                ->groupBy('barcode_type')
                ->map(function ($group) {
                    return $group->pluck('count', 'status')->toArray();
                })
                ->toArray();
        });
    }
    
    /**
     * Invalidate relevant caches after variant creation
     */
    public static function invalidateVariantCaches(string $barcodeType = null): void
    {
        Cache::forget('total_variant_count');
        
        if ($barcodeType) {
            Cache::forget("barcode_count_{$barcodeType}");
            Cache::forget("barcode_assigned_{$barcodeType}");
        }
        
        Cache::forget('barcode_pool_stats_all');
    }
    
    /**
     * Batch create multiple variants efficiently
     */
    public static function batchCreateVariants(array $variantData): array
    {
        $createdVariants = [];
        
        DB::transaction(function () use ($variantData, &$createdVariants) {
            foreach ($variantData as $data) {
                $variant = ProductVariant::create($data);
                $createdVariants[] = $variant->id;
            }
        });
        
        // Eager load all relationships in one query
        return ProductVariant::whereIn('id', $createdVariants)
            ->with(['barcodes', 'pricing', 'attributes', 'product:id,name,sku'])
            ->get();
    }
    
    /**
     * Get performance metrics
     */
    public static function getPerformanceMetrics(): array
    {
        return Cache::remember('variant_performance_metrics', 600, function () {
            return [
                'total_variants' => ProductVariant::count(),
                'variants_with_barcodes' => ProductVariant::whereHas('barcodes')->count(),
                'variants_with_pricing' => ProductVariant::whereHas('pricing')->count(),
                'average_attributes_per_variant' => DB::table('variant_attributes')
                    ->selectRaw('AVG(attributes_count) as avg_count')
                    ->from(DB::raw('(SELECT variant_id, COUNT(*) as attributes_count FROM variant_attributes GROUP BY variant_id) as subquery'))
                    ->value('avg_count') ?? 0,
            ];
        });
    }
    
    /**
     * Optimize database tables (should be run periodically)
     */
    public static function optimizeTables(): array
    {
        $results = [];
        
        $tables = ['product_variants', 'barcodes', 'pricing', 'variant_attributes', 'barcode_pools'];
        
        foreach ($tables as $table) {
            try {
                // SQLite doesn't support OPTIMIZE TABLE, but we can run ANALYZE
                DB::statement("ANALYZE {$table}");
                $results[$table] = 'analyzed';
            } catch (\Exception $e) {
                $results[$table] = 'error: ' . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Clear old performance logs
     */
    public static function cleanupPerformanceLogs(): int
    {
        // This would clean up performance monitoring logs older than 7 days
        // Implementation would depend on how logs are stored
        return 0; // Placeholder
    }
}