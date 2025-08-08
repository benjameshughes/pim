<?php

namespace App\DTOs\Import;

/**
 * Performance metrics tracking for import operations
 */
class PerformanceMetrics
{
    private array $timers = [];

    private array $chunkMetrics = [];

    private int $processedRows = 0;

    private int $errorCount = 0;

    private array $memorySnapshots = [];

    private \DateTime $startTime;

    public function __construct()
    {
        $this->startTime = new \DateTime;
    }

    /**
     * Start a timer for a specific operation
     */
    public function startTimer(string $name): void
    {
        $this->timers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true),
        ];
    }

    /**
     * End a timer and calculate duration
     */
    public function endTimer(string $name): float
    {
        if (! isset($this->timers[$name])) {
            return 0;
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $duration = $endTime - $this->timers[$name]['start'];
        $memoryDelta = $endMemory - $this->timers[$name]['memory_start'];

        $this->timers[$name]['end'] = $endTime;
        $this->timers[$name]['duration'] = $duration;
        $this->timers[$name]['memory_end'] = $endMemory;
        $this->timers[$name]['memory_delta'] = $memoryDelta;

        return $duration;
    }

    /**
     * Record metrics for a processed chunk
     */
    public function recordChunkMetrics(int $chunkIndex, array $metrics): void
    {
        $this->chunkMetrics[$chunkIndex] = array_merge($metrics, [
            'timestamp' => microtime(true),
            'processing_time' => $this->timers["chunk_{$chunkIndex}"]['duration'] ?? 0,
        ]);
    }

    /**
     * Record chunk processing error
     */
    public function recordChunkError(int $chunkIndex, string $error): void
    {
        $this->chunkMetrics[$chunkIndex]['error'] = $error;
        $this->errorCount++;
    }

    /**
     * Increment processed rows counter
     */
    public function incrementProcessedRows(int $count): void
    {
        $this->processedRows += $count;
    }

    /**
     * Increment error counter
     */
    public function incrementErrorCount(): void
    {
        $this->errorCount++;
    }

    /**
     * Take a memory snapshot
     */
    public function takeMemorySnapshot(string $label): void
    {
        $this->memorySnapshots[$label] = [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Get comprehensive metrics
     */
    public function getMetrics(): array
    {
        return [
            'start_time' => $this->startTime->format('Y-m-d H:i:s'),
            'total_processing_time' => $this->timers['total_processing']['duration'] ?? 0,
            'processed_rows' => $this->processedRows,
            'error_count' => $this->errorCount,
            'chunk_metrics' => $this->chunkMetrics,
            'timers' => $this->timers,
            'memory_snapshots' => $this->memorySnapshots,
            'performance_summary' => $this->getPerformanceSummary(),
        ];
    }

    /**
     * Get performance summary
     */
    public function getPerformanceSummary(): array
    {
        $totalTime = $this->timers['total_processing']['duration'] ?? 1;
        $rowsPerSecond = $this->processedRows / $totalTime;

        $avgChunkTime = 0;
        $chunkCount = count($this->chunkMetrics);

        if ($chunkCount > 0) {
            $totalChunkTime = array_sum(array_column($this->chunkMetrics, 'processing_time'));
            $avgChunkTime = $totalChunkTime / $chunkCount;
        }

        return [
            'rows_per_second' => round($rowsPerSecond, 2),
            'average_chunk_time' => round($avgChunkTime, 4),
            'total_chunks' => $chunkCount,
            'error_rate' => $this->processedRows > 0 ? ($this->errorCount / $this->processedRows) * 100 : 0,
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'memory_efficiency_kb_per_row' => $this->processedRows > 0 ?
                round(memory_get_peak_usage(true) / 1024 / $this->processedRows, 2) : 0,
        ];
    }

    /**
     * Get timer duration
     */
    public function getTimerDuration(string $name): float
    {
        return $this->timers[$name]['duration'] ?? 0;
    }

    /**
     * Get slowest chunks
     */
    public function getSlowestChunks(int $limit = 5): array
    {
        $chunks = $this->chunkMetrics;

        uasort($chunks, function ($a, $b) {
            return ($b['processing_time'] ?? 0) <=> ($a['processing_time'] ?? 0);
        });

        return array_slice($chunks, 0, $limit, true);
    }

    /**
     * Get chunks with errors
     */
    public function getErrorChunks(): array
    {
        return array_filter($this->chunkMetrics, function ($chunk) {
            return isset($chunk['error']);
        });
    }

    /**
     * Export metrics to array for storage/reporting
     */
    public function toArray(): array
    {
        return [
            'metadata' => [
                'start_time' => $this->startTime->format('Y-m-d H:i:s'),
                'end_time' => (new \DateTime)->format('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
            ],
            'summary' => $this->getPerformanceSummary(),
            'detailed_metrics' => $this->getMetrics(),
            'analysis' => [
                'slowest_chunks' => $this->getSlowestChunks(),
                'error_chunks' => $this->getErrorChunks(),
                'memory_progression' => $this->memorySnapshots,
            ],
        ];
    }
}
