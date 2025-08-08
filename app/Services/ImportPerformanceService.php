<?php

namespace App\Services;

use App\DTOs\Import\PerformanceMetrics;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Performance optimization service for large Excel imports
 * Handles chunking, memory management, and processing optimization
 */
class ImportPerformanceService
{
    private const DEFAULT_CHUNK_SIZE = 1000;

    private const MEMORY_LIMIT_PERCENTAGE = 80; // Use up to 80% of available memory

    private const CACHE_CHUNK_PREFIX = 'import_chunk_';

    private PerformanceMetrics $metrics;

    private int $chunkSize;

    private int $memoryLimit;

    private bool $useCache;

    public function __construct(?int $chunkSize = null, bool $useCache = true)
    {
        $this->metrics = new PerformanceMetrics;
        $this->chunkSize = $chunkSize ?? $this->calculateOptimalChunkSize();
        $this->memoryLimit = $this->calculateMemoryLimit();
        $this->useCache = $useCache;

        Log::info('Performance service initialized', [
            'chunk_size' => $this->chunkSize,
            'memory_limit' => $this->memoryLimit,
            'use_cache' => $this->useCache,
        ]);
    }

    /**
     * Process large dataset in optimized chunks
     */
    public function processInChunks(array $data, callable $processor, ?callable $progressCallback = null): array
    {
        $this->metrics->startTimer('total_processing');
        $totalRows = count($data);
        $results = [];
        $processedRows = 0;

        Log::info('Starting chunked processing', [
            'total_rows' => $totalRows,
            'chunk_size' => $this->chunkSize,
            'estimated_chunks' => ceil($totalRows / $this->chunkSize),
        ]);

        foreach (array_chunk($data, $this->chunkSize, true) as $chunkIndex => $chunk) {
            $this->metrics->startTimer("chunk_{$chunkIndex}");

            // Memory management
            $this->optimizeMemoryBeforeChunk();

            try {
                // Process chunk with error handling
                $chunkResults = $this->processChunkWithErrorHandling($chunk, $processor, $chunkIndex);
                $results = array_merge($results, $chunkResults);

                $processedRows += count($chunk);
                $this->metrics->incrementProcessedRows(count($chunk));

                // Update progress
                if ($progressCallback) {
                    $progress = ($processedRows / $totalRows) * 100;
                    $progressCallback([
                        'progress' => $progress,
                        'processed_rows' => $processedRows,
                        'total_rows' => $totalRows,
                        'current_chunk' => $chunkIndex + 1,
                        'memory_usage' => $this->getCurrentMemoryUsage(),
                    ]);
                }

                $this->metrics->endTimer("chunk_{$chunkIndex}");

                // Cache chunk results if enabled
                if ($this->useCache) {
                    $this->cacheChunkResults($chunkIndex, $chunkResults);
                }

            } catch (\Throwable $e) {
                Log::error('Chunk processing failed', [
                    'chunk_index' => $chunkIndex,
                    'chunk_size' => count($chunk),
                    'error' => $e->getMessage(),
                ]);

                $this->metrics->incrementErrorCount();
                throw $e;
            }

            // Memory pressure check
            if ($this->isMemoryPressureHigh()) {
                $this->handleMemoryPressure();
            }
        }

        $this->metrics->endTimer('total_processing');
        $this->logPerformanceMetrics($totalRows);

        return $results;
    }

    /**
     * Stream process very large files without loading all into memory
     */
    public function streamProcess(\SplFileObject $file, callable $processor, ?callable $progressCallback = null): array
    {
        $this->metrics->startTimer('stream_processing');
        $results = [];
        $processedRows = 0;
        $batchBuffer = [];

        Log::info('Starting stream processing');

        while (! $file->eof()) {
            $line = $file->fgetcsv();

            if ($line === null || $line === [null]) {
                continue;
            }

            $batchBuffer[] = $line;

            // Process when buffer reaches chunk size
            if (count($batchBuffer) >= $this->chunkSize) {
                $batchResults = $processor($batchBuffer);
                $results = array_merge($results, $batchResults);

                $processedRows += count($batchBuffer);
                $this->metrics->incrementProcessedRows(count($batchBuffer));

                // Update progress
                if ($progressCallback) {
                    $progressCallback([
                        'processed_rows' => $processedRows,
                        'memory_usage' => $this->getCurrentMemoryUsage(),
                        'stream_position' => $file->ftell(),
                    ]);
                }

                $batchBuffer = [];
                $this->optimizeMemoryBeforeChunk();
            }
        }

        // Process remaining buffer
        if (! empty($batchBuffer)) {
            $batchResults = $processor($batchBuffer);
            $results = array_merge($results, $batchResults);
            $processedRows += count($batchBuffer);
        }

        $this->metrics->endTimer('stream_processing');
        Log::info('Stream processing completed', [
            'total_rows_processed' => $processedRows,
            'final_memory_usage' => $this->getCurrentMemoryUsage(),
        ]);

        return $results;
    }

    /**
     * Optimize database operations for bulk inserts
     */
    public function optimizeDatabaseOperations(): void
    {
        // Disable foreign key checks temporarily for better performance
        DB::statement('PRAGMA foreign_keys=OFF');

        // Set optimal SQLite settings for bulk operations
        DB::statement('PRAGMA synchronous=OFF');
        DB::statement('PRAGMA journal_mode=MEMORY');
        DB::statement('PRAGMA temp_store=MEMORY');
        DB::statement('PRAGMA cache_size=10000');

        Log::info('Database optimizations applied for bulk operations');
    }

    /**
     * Restore normal database operations
     */
    public function restoreNormalDatabaseOperations(): void
    {
        DB::statement('PRAGMA foreign_keys=ON');
        DB::statement('PRAGMA synchronous=FULL');
        DB::statement('PRAGMA journal_mode=DELETE');
        DB::statement('PRAGMA temp_store=DEFAULT');

        Log::info('Database optimizations restored to normal');
    }

    /**
     * Process chunk with comprehensive error handling
     */
    private function processChunkWithErrorHandling(array $chunk, callable $processor, int $chunkIndex): array
    {
        $startMemory = memory_get_usage(true);

        try {
            $results = $processor($chunk);

            $endMemory = memory_get_usage(true);
            $memoryDelta = $endMemory - $startMemory;

            $this->metrics->recordChunkMetrics($chunkIndex, [
                'rows_processed' => count($chunk),
                'memory_used' => $memoryDelta,
                'start_memory' => $startMemory,
                'end_memory' => $endMemory,
            ]);

            return $results;

        } catch (\Throwable $e) {
            $this->metrics->recordChunkError($chunkIndex, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate optimal chunk size based on available memory and data complexity
     */
    private function calculateOptimalChunkSize(): int
    {
        $availableMemory = $this->getAvailableMemory();
        $estimatedRowSize = 1024; // Estimate 1KB per row on average

        // Calculate chunk size to use ~10% of available memory per chunk
        $optimalSize = intval(($availableMemory * 0.1) / $estimatedRowSize);

        // Ensure reasonable bounds
        $chunkSize = max(100, min(5000, $optimalSize));

        Log::debug('Calculated optimal chunk size', [
            'available_memory' => $availableMemory,
            'estimated_row_size' => $estimatedRowSize,
            'calculated_size' => $optimalSize,
            'final_chunk_size' => $chunkSize,
        ]);

        return $chunkSize;
    }

    /**
     * Calculate memory limit for processing
     */
    private function calculateMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit === '-1') {
            return PHP_INT_MAX; // No limit
        }

        $bytes = $this->convertToBytes($memoryLimit);

        return intval($bytes * (self::MEMORY_LIMIT_PERCENTAGE / 100));
    }

    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes(string $memoryLimit): int
    {
        $unit = strtolower(substr($memoryLimit, -1));
        $value = intval(substr($memoryLimit, 0, -1));

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value
        };
    }

    /**
     * Get available memory
     */
    private function getAvailableMemory(): int
    {
        $currentUsage = memory_get_usage(true);

        return max(0, $this->memoryLimit - $currentUsage);
    }

    /**
     * Get current memory usage information
     */
    private function getCurrentMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => $this->memoryLimit,
            'available' => $this->getAvailableMemory(),
            'percentage' => (memory_get_usage(true) / $this->memoryLimit) * 100,
        ];
    }

    /**
     * Check if memory pressure is high
     */
    private function isMemoryPressureHigh(): bool
    {
        $current = memory_get_usage(true);
        $pressureThreshold = $this->memoryLimit * 0.85; // 85% threshold

        return $current > $pressureThreshold;
    }

    /**
     * Handle high memory pressure
     */
    private function handleMemoryPressure(): void
    {
        Log::warning('High memory pressure detected, performing cleanup', [
            'current_usage' => memory_get_usage(true),
            'memory_limit' => $this->memoryLimit,
        ]);

        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            $collected = gc_collect_cycles();
            Log::info("Garbage collection freed {$collected} cycles");
        }

        // Clear cache if enabled
        if ($this->useCache) {
            $this->clearOldChunkCache();
            Log::info('Cleared old chunk cache to free memory');
        }

        // Reduce chunk size temporarily
        $this->chunkSize = max(100, intval($this->chunkSize * 0.8));
        Log::info("Reduced chunk size to {$this->chunkSize} due to memory pressure");
    }

    /**
     * Optimize memory before processing chunk
     */
    private function optimizeMemoryBeforeChunk(): void
    {
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        // Clear any unnecessary variables
        if (function_exists('gc_mem_caches')) {
            gc_mem_caches();
        }
    }

    /**
     * Cache chunk results for recovery
     */
    private function cacheChunkResults(int $chunkIndex, array $results): void
    {
        $cacheKey = self::CACHE_CHUNK_PREFIX.$chunkIndex;
        Cache::put($cacheKey, $results, now()->addHours(2));
    }

    /**
     * Clear old chunk cache entries
     */
    private function clearOldChunkCache(): void
    {
        // This would need implementation based on cache driver
        // For Redis/Memcached, we could use pattern matching
        Log::info('Cache cleanup requested (implementation depends on cache driver)');
    }

    /**
     * Log comprehensive performance metrics
     */
    private function logPerformanceMetrics(int $totalRows): void
    {
        $metrics = $this->metrics->getMetrics();

        Log::info('Import performance metrics', [
            'total_rows' => $totalRows,
            'processing_time' => $metrics['total_processing_time'] ?? 0,
            'rows_per_second' => $totalRows / max(1, $metrics['total_processing_time'] ?? 1),
            'peak_memory_usage' => memory_get_peak_usage(true),
            'memory_efficiency' => ($totalRows * 1024) / max(1, memory_get_peak_usage(true)),
            'chunk_count' => count($metrics['chunk_metrics'] ?? []),
            'error_count' => $metrics['error_count'] ?? 0,
        ]);
    }

    /**
     * Get performance metrics
     */
    public function getMetrics(): PerformanceMetrics
    {
        return $this->metrics;
    }

    /**
     * Reset metrics for new import
     */
    public function resetMetrics(): void
    {
        $this->metrics = new PerformanceMetrics;
    }
}
