<?php

namespace App\Services\Import\Performance;

use App\Models\ImportSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Generator;

/**
 * 🧠 Import Chunker
 * 
 * Smart, adaptive chunking system specifically designed for import processing.
 * Automatically adjusts chunk sizes based on import complexity and system performance.
 * 
 * Usage:
 * $chunker = ImportChunker::forSession($session);
 * $chunker->processFile($filePath, fn($chunk) => $this->processChunk($chunk));
 */
class ImportChunker
{
    private ImportSession $session;
    private int $currentChunkSize;
    private int $minChunkSize = 5;
    private int $maxChunkSize = 200;
    private int $consecutiveSuccesses = 0;
    private int $consecutiveFailures = 0;
    private array $performanceHistory = [];

    public function __construct(ImportSession $session, int $initialChunkSize = null)
    {
        $this->session = $session;
        $this->currentChunkSize = $initialChunkSize ?? $this->calculateOptimalSize();
    }

    /**
     * 🎯 Create chunker for specific import session
     */
    public static function forSession(ImportSession $session): self
    {
        return new self($session);
    }

    /**
     * 🔥 Process file with adaptive chunking
     */
    public function processFile(string $filePath, callable $processor): Generator
    {
        $this->logStart();
        $chunkCount = 0;
        $startTime = microtime(true);

        if ($this->session->file_type === 'csv') {
            yield from $this->processCsvFile($filePath, $processor, $chunkCount);
        } else {
            yield from $this->processExcelFile($filePath, $processor, $chunkCount);
        }

        $this->logCompletion($chunkCount, microtime(true) - $startTime);
    }

    /**
     * 📄 Process CSV file in adaptive chunks
     */
    private function processCsvFile(string $filePath, callable $processor, int &$chunkCount): Generator
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception('Could not open CSV file');
        }

        // Skip header
        $headers = fgetcsv($handle);
        $columnMapping = $this->session->column_mapping ?? [];

        $chunk = [];
        $rowNumber = 2;

        while (($row = fgetcsv($handle)) !== false) {
            $chunk[] = [
                'row_number' => $rowNumber,
                'data' => $this->mapRowData($row, $headers, $columnMapping),
                'raw_data' => $row,
            ];

            if (count($chunk) >= $this->currentChunkSize) {
                yield from $this->processChunk($chunk, $processor, $chunkCount);
                $chunk = [];
            }

            $rowNumber++;
        }

        // Process remaining chunk
        if (!empty($chunk)) {
            yield from $this->processChunk($chunk, $processor, $chunkCount);
        }

        fclose($handle);
    }

    /**
     * 📊 Process Excel file in adaptive chunks
     */
    private function processExcelFile(string $filePath, callable $processor, int &$chunkCount): Generator
    {
        // Excel processing logic would go here
        // For now, falling back to CSV-like processing
        yield from $this->processCsvFile($filePath, $processor, $chunkCount);
    }

    /**
     * 🚀 Process a single chunk with performance monitoring
     */
    private function processChunk(array $chunk, callable $processor, int &$chunkCount): Generator
    {
        $chunkStartTime = microtime(true);
        $memoryBefore = memory_get_usage(true);
        
        try {
            // 🎯 Update session progress
            $this->session->updateProgress(
                stage: 'processing',
                operation: "Processing chunk " . ($chunkCount + 1),
                percentage: $this->calculateProgress($chunkCount)
            );

            // 🔥 Process the chunk
            $result = $processor($chunk);
            
            // 📊 Record success metrics
            $this->recordChunkSuccess($chunkStartTime, $memoryBefore, count($chunk));
            $this->adjustChunkSize('success');
            
            $chunkCount++;
            
            Log::debug('Import chunk processed', [
                'session_id' => $this->session->session_id,
                'chunk_number' => $chunkCount,
                'chunk_size' => count($chunk),
                'processing_time_ms' => round((microtime(true) - $chunkStartTime) * 1000, 2),
                'memory_used' => $this->formatBytes(memory_get_usage(true) - $memoryBefore),
                'current_chunk_size' => $this->currentChunkSize,
            ]);

            // 🧹 Memory cleanup if needed
            $this->manageMemory();

            yield $result;

        } catch (\Exception $e) {
            $this->recordChunkFailure($e, count($chunk));
            $this->adjustChunkSize('failure');
            
            Log::error('Import chunk failed', [
                'session_id' => $this->session->session_id,
                'chunk_number' => $chunkCount,
                'chunk_size' => count($chunk),
                'error' => $e->getMessage(),
                'new_chunk_size' => $this->currentChunkSize,
            ]);
            
            throw $e;
        }
    }

    /**
     * 🎯 Smart chunk size adjustment based on performance
     */
    private function adjustChunkSize(string $outcome): void
    {
        $oldSize = $this->currentChunkSize;
        
        switch ($outcome) {
            case 'success':
                $this->consecutiveSuccesses++;
                $this->consecutiveFailures = 0;
                
                // 🚀 Gradually increase if performing well
                if ($this->consecutiveSuccesses >= 3 && $this->canIncrease()) {
                    $this->currentChunkSize = min(
                        $this->maxChunkSize,
                        (int) ($this->currentChunkSize * 1.25)
                    );
                    $this->consecutiveSuccesses = 0;
                }
                break;
                
            case 'failure':
                $this->consecutiveFailures++;
                $this->consecutiveSuccesses = 0;
                
                // 🛡️ Immediately reduce on failure
                $this->currentChunkSize = max(
                    $this->minChunkSize,
                    (int) ($this->currentChunkSize * 0.6)
                );
                break;
                
            case 'memory_pressure':
                // 🧠 Aggressive reduction for memory issues
                $this->currentChunkSize = max(
                    $this->minChunkSize,
                    (int) ($this->currentChunkSize * 0.4)
                );
                break;
        }

        // 🔄 Update session configuration with new optimal size
        if ($oldSize !== $this->currentChunkSize) {
            $config = $this->session->configuration;
            $config['optimal_chunk_size'] = $this->currentChunkSize;
            $this->session->update(['configuration' => $config]);
            
            Log::info('Import chunk size adjusted', [
                'session_id' => $this->session->session_id,
                'reason' => $outcome,
                'old_size' => $oldSize,
                'new_size' => $this->currentChunkSize,
            ]);
        }
    }

    /**
     * 🧠 Memory management for imports
     */
    private function manageMemory(): void
    {
        $currentMemory = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimitBytes();
        $threshold = $memoryLimit * 0.7; // 70% threshold
        
        if ($currentMemory > $threshold) {
            $memoryBefore = $currentMemory;
            
            // 🔄 Force garbage collection
            gc_collect_cycles();
            
            $memoryAfter = memory_get_usage(true);
            $freed = $memoryBefore - $memoryAfter;
            
            Log::info('Import memory cleanup', [
                'session_id' => $this->session->session_id,
                'memory_before' => $this->formatBytes($memoryBefore),
                'memory_after' => $this->formatBytes($memoryAfter),
                'memory_freed' => $this->formatBytes($freed),
                'memory_limit' => $this->formatBytes($memoryLimit),
            ]);
            
            // 🚨 If still high, reduce chunk size
            if ($memoryAfter > $threshold) {
                $this->adjustChunkSize('memory_pressure');
            }
        }
    }

    /**
     * 📊 Record successful chunk processing
     */
    private function recordChunkSuccess(float $startTime, int $memoryBefore, int $recordCount): void
    {
        $duration = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $memoryBefore;
        
        $this->performanceHistory[] = [
            'timestamp' => time(),
            'duration' => $duration,
            'memory_used' => $memoryUsed,
            'record_count' => $recordCount,
            'records_per_second' => $recordCount / max($duration, 0.001),
            'memory_per_record' => $memoryUsed / max($recordCount, 1),
        ];
        
        // Keep only recent history
        if (count($this->performanceHistory) > 20) {
            $this->performanceHistory = array_slice($this->performanceHistory, -20);
        }
    }

    /**
     * 💥 Record chunk processing failure
     */
    private function recordChunkFailure(\Exception $e, int $recordCount): void
    {
        Log::warning('Import chunk failure', [
            'session_id' => $this->session->session_id,
            'error_class' => get_class($e),
            'error_message' => $e->getMessage(),
            'record_count' => $recordCount,
            'chunk_size' => $this->currentChunkSize,
        ]);
    }

    /**
     * 🎯 Check if we can safely increase chunk size
     */
    private function canIncrease(): bool
    {
        if (empty($this->performanceHistory)) {
            return true;
        }
        
        $recent = array_slice($this->performanceHistory, -5);
        $avgRecordsPerSecond = collect($recent)->avg('records_per_second');
        $avgMemoryPerRecord = collect($recent)->avg('memory_per_record');
        
        // Can increase if performance is good and memory usage reasonable
        return $avgRecordsPerSecond > 5 && $avgMemoryPerRecord < 2 * 1024 * 1024; // 2MB per record max
    }

    /**
     * 🎯 Calculate optimal initial chunk size based on import characteristics
     */
    private function calculateOptimalSize(): int
    {
        $fileSize = $this->session->file_size;
        $totalRows = $this->session->total_rows ?: 1000; // Default estimate
        $availableMemory = $this->getAvailableMemory();
        
        // Base calculation on file characteristics
        $baseSize = match(true) {
            $fileSize > 50 * 1024 * 1024 => 25,    // Large files: small chunks
            $fileSize > 10 * 1024 * 1024 => 50,    // Medium files: medium chunks  
            $fileSize > 1 * 1024 * 1024  => 100,   // Small files: larger chunks
            default => 50                          // Default
        };
        
        // Adjust for available memory
        $memoryMultiplier = match(true) {
            $availableMemory > 200 * 1024 * 1024 => 2.0,  // Lots of memory
            $availableMemory > 100 * 1024 * 1024 => 1.5,  // Good memory
            $availableMemory > 50 * 1024 * 1024  => 1.0,  // Normal memory
            default => 0.5                                 // Low memory
        };
        
        return (int) max($this->minChunkSize, min($this->maxChunkSize, $baseSize * $memoryMultiplier));
    }

    /**
     * 📏 Calculate progress percentage
     */
    private function calculateProgress(int $chunkCount): int
    {
        if (!$this->session->total_rows) {
            return min(100, $chunkCount * 5); // Rough estimate
        }
        
        $processedRows = $chunkCount * $this->currentChunkSize;
        return min(100, (int) (($processedRows / $this->session->total_rows) * 100));
    }

    /**
     * 🗺️ Map CSV row data to fields
     */
    private function mapRowData(array $row, array $headers, array $columnMapping): array
    {
        $mapped = [];
        
        foreach ($columnMapping as $columnIndex => $fieldName) {
            if (!empty($fieldName) && isset($row[$columnIndex])) {
                $mapped[$fieldName] = $row[$columnIndex];
            }
        }
        
        return $mapped;
    }

    /**
     * 💾 Get available memory in bytes
     */
    private function getAvailableMemory(): int
    {
        $limit = $this->getMemoryLimitBytes();
        $used = memory_get_usage(true);
        return max(0, $limit - $used);
    }

    /**
     * 📏 Get memory limit in bytes
     */
    private function getMemoryLimitBytes(): int
    {
        $limit = ini_get('memory_limit');
        
        if ($limit === '-1') {
            return 1024 * 1024 * 1024; // 1GB default
        }
        
        $unit = strtoupper(substr($limit, -1));
        $value = (int) $limit;
        
        return match($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value
        };
    }

    /**
     * 📏 Format bytes for human reading
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * 📝 Log processing start
     */
    private function logStart(): void
    {
        Log::info('Import chunking started', [
            'session_id' => $this->session->session_id,
            'file_name' => $this->session->original_filename,
            'file_size' => $this->formatBytes($this->session->file_size),
            'total_rows' => $this->session->total_rows,
            'initial_chunk_size' => $this->currentChunkSize,
            'available_memory' => $this->formatBytes($this->getAvailableMemory()),
        ]);
    }

    /**
     * 🏁 Log processing completion
     */
    private function logCompletion(int $chunkCount, float $totalTime): void
    {
        $recordsPerSecond = $this->session->total_rows / max($totalTime, 0.001);
        
        Log::info('Import chunking completed', [
            'session_id' => $this->session->session_id,
            'total_chunks' => $chunkCount,
            'total_time_seconds' => round($totalTime, 2),
            'records_per_second' => round($recordsPerSecond, 2),
            'final_chunk_size' => $this->currentChunkSize,
            'peak_memory' => $this->formatBytes(memory_get_peak_usage(true)),
        ]);
    }

    /**
     * 📊 Get current performance statistics
     */
    public function getPerformanceStats(): array
    {
        $recent = collect($this->performanceHistory);
        
        return [
            'session_id' => $this->session->session_id,
            'current_chunk_size' => $this->currentChunkSize,
            'consecutive_successes' => $this->consecutiveSuccesses,
            'consecutive_failures' => $this->consecutiveFailures,
            'avg_records_per_second' => $recent->avg('records_per_second'),
            'avg_memory_per_record' => $recent->avg('memory_per_record'),
            'total_chunks_processed' => $recent->count(),
        ];
    }
}