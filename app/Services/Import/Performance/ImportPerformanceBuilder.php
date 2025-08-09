<?php

namespace App\Services\Import\Performance;

use App\Models\ImportSession;
use Illuminate\Support\Facades\Log;
use Generator;

/**
 * 🚀 Import Performance Builder
 * 
 * Fluent API builder for configuring and executing high-performance import processing.
 * Combines adaptive chunking, memory management, and circuit breaker patterns.
 * 
 * Usage Examples:
 * 
 * // 🔥 Basic high-performance processing
 * ImportPerformanceBuilder::forSession($session)
 *     ->withSmartChunking()
 *     ->withMemoryManagement()
 *     ->withCircuitBreaker()
 *     ->processFile($filePath, $processor);
 * 
 * // 🎯 Custom configuration
 * ImportPerformanceBuilder::forSession($session)
 *     ->chunkSize(25)
 *     ->memoryLimit(100)
 *     ->failureThreshold(3)
 *     ->processFile($filePath, $processor);
 * 
 * // 💎 Maximum performance mode
 * ImportPerformanceBuilder::forSession($session)
 *     ->maximize()
 *     ->processFile($filePath, $processor);
 */
class ImportPerformanceBuilder
{
    private ImportSession $session;
    private ?ImportChunker $chunker = null;
    private ?ImportMemoryManager $memoryManager = null;
    private ?ImportCircuitBreaker $circuitBreaker = null;
    
    // Configuration
    private bool $useAdaptiveChunking = true;
    private int $initialChunkSize = 50;
    private bool $useMemoryManagement = true;
    private ?int $memoryLimitMb = null;
    private bool $useCircuitBreaker = true;
    private int $failureThreshold = 5;
    private int $recoveryTimeout = 30;
    private bool $enableDetailedLogging = false;
    private array $performanceMetrics = [];

    public function __construct(ImportSession $session)
    {
        $this->session = $session;
    }

    /**
     * 🎯 Create performance builder for specific import session
     */
    public static function forSession(ImportSession $session): self
    {
        return new self($session);
    }

    /**
     * 🧠 Enable smart adaptive chunking
     */
    public function withSmartChunking(int $initialSize = null): self
    {
        $this->useAdaptiveChunking = true;
        if ($initialSize) {
            $this->initialChunkSize = $initialSize;
        }
        
        return $this;
    }

    /**
     * 📦 Use fixed chunk size (disable adaptive chunking)
     */
    public function chunkSize(int $size): self
    {
        $this->useAdaptiveChunking = false;
        $this->initialChunkSize = $size;
        
        return $this;
    }

    /**
     * 🧠 Enable memory management
     */
    public function withMemoryManagement(int $limitMb = null): self
    {
        $this->useMemoryManagement = true;
        if ($limitMb) {
            $this->memoryLimitMb = $limitMb;
        }
        
        return $this;
    }

    /**
     * 💾 Set memory limit
     */
    public function memoryLimit(int $limitMb): self
    {
        $this->memoryLimitMb = $limitMb;
        
        return $this;
    }

    /**
     * 🛡️ Enable circuit breaker protection
     */
    public function withCircuitBreaker(int $failureThreshold = 5, int $recoveryTimeoutSeconds = 30): self
    {
        $this->useCircuitBreaker = true;
        $this->failureThreshold = $failureThreshold;
        $this->recoveryTimeout = $recoveryTimeoutSeconds;
        
        return $this;
    }

    /**
     * 🎯 Set failure threshold for circuit breaker
     */
    public function failureThreshold(int $threshold): self
    {
        $this->failureThreshold = $threshold;
        
        return $this;
    }

    /**
     * ⏰ Set recovery timeout for circuit breaker
     */
    public function recoveryTimeout(int $seconds): self
    {
        $this->recoveryTimeout = $seconds;
        
        return $this;
    }

    /**
     * 📊 Enable detailed performance logging
     */
    public function withDetailedLogging(bool $enabled = true): self
    {
        $this->enableDetailedLogging = $enabled;
        
        return $this;
    }

    /**
     * 🚀 Maximize performance (enable all optimizations)
     */
    public function maximize(): self
    {
        return $this->withSmartChunking()
                   ->withMemoryManagement()
                   ->withCircuitBreaker(3, 20) // More aggressive circuit breaker
                   ->withDetailedLogging()
                   ->optimizeForSystem();
    }

    /**
     * ⚡ Optimize settings for current system
     */
    public function optimizeForSystem(): self
    {
        $availableMemory = $this->getAvailableMemoryMb();
        $processorCount = $this->getProcessorCount();
        
        // Optimize chunk size based on system resources
        $optimalChunkSize = match(true) {
            $availableMemory > 200 => min(100, $processorCount * 10),
            $availableMemory > 100 => min(50, $processorCount * 5),
            $availableMemory > 50  => min(25, $processorCount * 3),
            default => 10
        };
        
        $this->initialChunkSize = $optimalChunkSize;
        $this->memoryLimitMb = (int) ($availableMemory * 0.8); // 80% of available
        
        Log::info('Import performance optimized for system', [
            'session_id' => $this->session->session_id,
            'available_memory_mb' => $availableMemory,
            'processor_count' => $processorCount,
            'optimal_chunk_size' => $optimalChunkSize,
            'memory_limit_mb' => $this->memoryLimitMb,
        ]);
        
        return $this;
    }

    /**
     * 🔧 Build and configure all performance components
     */
    private function build(): void
    {
        if ($this->useAdaptiveChunking || !$this->chunker) {
            $this->chunker = ImportChunker::forSession($this->session, $this->initialChunkSize);
        }
        
        if ($this->useMemoryManagement || !$this->memoryManager) {
            $this->memoryManager = ImportMemoryManager::forSession($this->session, $this->memoryLimitMb);
        }
        
        if ($this->useCircuitBreaker || !$this->circuitBreaker) {
            $this->circuitBreaker = ImportCircuitBreaker::forSession($this->session, [
                'failure_threshold' => $this->failureThreshold,
                'recovery_timeout' => $this->recoveryTimeout,
            ]);
        }
    }

    /**
     * 🚀 Process import file with all performance optimizations
     */
    public function processFile(string $filePath, callable $processor): Generator
    {
        $this->build();
        $this->logProcessingStart($filePath);
        $startTime = microtime(true);
        
        try {
            // 🛡️ Wrap processing in circuit breaker if enabled
            if ($this->useCircuitBreaker) {
                yield from $this->circuitBreaker->execute(function() use ($filePath, $processor) {
                    return $this->executeFileProcessing($filePath, $processor);
                });
            } else {
                yield from $this->executeFileProcessing($filePath, $processor);
            }
            
        } catch (\Exception $e) {
            $this->logProcessingError($e, $filePath);
            throw $e;
            
        } finally {
            $this->logProcessingCompletion($filePath, microtime(true) - $startTime);
        }
    }

    /**
     * 🔥 Execute the actual file processing with performance monitoring
     */
    private function executeFileProcessing(string $filePath, callable $processor): Generator
    {
        $chunkCount = 0;
        
        // 🚀 Process file using adaptive chunker
        foreach ($this->chunker->processFile($filePath, function($chunk) use ($processor, &$chunkCount) {
            $chunkCount++;
            
            // 🧠 Memory management before chunk
            if ($this->useMemoryManagement) {
                $this->memoryManager->beforeChunkProcessing($chunkCount);
            }
            
            $chunkFailed = false;
            $result = null;
            
            try {
                // 🎯 Process the chunk
                $result = $processor($chunk);
                
                // 📊 Record performance metrics
                $this->recordChunkMetrics($chunkCount, count($chunk), true);
                
            } catch (\Exception $e) {
                $chunkFailed = true;
                $this->recordChunkMetrics($chunkCount, count($chunk), false);
                throw $e;
                
            } finally {
                // 🧠 Memory management after chunk
                if ($this->useMemoryManagement) {
                    $this->memoryManager->afterChunkProcessing($chunkCount, $chunkFailed);
                }
            }
            
            return $result;
            
        }) as $result) {
            yield $result;
        }
    }

    /**
     * 📊 Record chunk processing metrics
     */
    private function recordChunkMetrics(int $chunkNumber, int $recordCount, bool $success): void
    {
        $this->performanceMetrics[] = [
            'chunk_number' => $chunkNumber,
            'record_count' => $recordCount,
            'success' => $success,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(true),
        ];
        
        // Keep only recent metrics
        if (count($this->performanceMetrics) > 100) {
            $this->performanceMetrics = array_slice($this->performanceMetrics, -50);
        }
    }

    /**
     * 📊 Get comprehensive performance statistics
     */
    public function getPerformanceStats(): array
    {
        $stats = [
            'session_id' => $this->session->session_id,
            'configuration' => [
                'adaptive_chunking' => $this->useAdaptiveChunking,
                'initial_chunk_size' => $this->initialChunkSize,
                'memory_management' => $this->useMemoryManagement,
                'memory_limit_mb' => $this->memoryLimitMb,
                'circuit_breaker' => $this->useCircuitBreaker,
                'failure_threshold' => $this->failureThreshold,
                'recovery_timeout' => $this->recoveryTimeout,
            ],
            'metrics' => [
                'total_chunks' => count($this->performanceMetrics),
                'successful_chunks' => count(array_filter($this->performanceMetrics, fn($m) => $m['success'])),
                'failed_chunks' => count(array_filter($this->performanceMetrics, fn($m) => !$m['success'])),
            ],
        ];
        
        // Add component-specific stats
        if ($this->chunker) {
            $stats['chunker'] = $this->chunker->getPerformanceStats();
        }
        
        if ($this->memoryManager) {
            $stats['memory_manager'] = $this->memoryManager->getStats();
        }
        
        if ($this->circuitBreaker) {
            $stats['circuit_breaker'] = $this->circuitBreaker->getStatus();
        }
        
        return $stats;
    }

    /**
     * 🔄 Reset all performance components
     */
    public function reset(): self
    {
        $this->performanceMetrics = [];
        
        if ($this->circuitBreaker) {
            $this->circuitBreaker->reset();
        }
        
        // Rebuild components with fresh state
        $this->chunker = null;
        $this->memoryManager = null;
        $this->circuitBreaker = null;
        
        return $this;
    }

    /**
     * 🎛️ Get current component instances (for advanced usage)
     */
    public function getComponents(): array
    {
        $this->build();
        
        return [
            'chunker' => $this->chunker,
            'memory_manager' => $this->memoryManager,
            'circuit_breaker' => $this->circuitBreaker,
        ];
    }

    /**
     * 💾 Get available memory in MB
     */
    private function getAvailableMemoryMb(): int
    {
        $limit = ini_get('memory_limit');
        
        if ($limit === '-1') {
            return 512; // 512MB default
        }
        
        $bytes = $this->parseMemoryLimit($limit);
        $used = memory_get_usage(true);
        
        return max(32, (int) (($bytes - $used) / 1024 / 1024));
    }

    /**
     * ⚙️ Get processor count
     */
    private function getProcessorCount(): int
    {
        return (int) (shell_exec('nproc') ?: shell_exec('sysctl -n hw.ncpu') ?: 2);
    }

    /**
     * 🔧 Parse memory limit string
     */
    private function parseMemoryLimit(string $limit): int
    {
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
     * 📝 Log processing start
     */
    private function logProcessingStart(string $filePath): void
    {
        Log::info('High-performance import processing started', [
            'session_id' => $this->session->session_id,
            'file_name' => $this->session->original_filename,
            'file_path' => basename($filePath),
            'configuration' => [
                'adaptive_chunking' => $this->useAdaptiveChunking,
                'initial_chunk_size' => $this->initialChunkSize,
                'memory_management' => $this->useMemoryManagement,
                'memory_limit_mb' => $this->memoryLimitMb,
                'circuit_breaker' => $this->useCircuitBreaker,
                'detailed_logging' => $this->enableDetailedLogging,
            ],
            'system_info' => [
                'available_memory_mb' => $this->getAvailableMemoryMb(),
                'processor_count' => $this->getProcessorCount(),
            ],
        ]);
    }

    /**
     * 💥 Log processing error
     */
    private function logProcessingError(\Exception $e, string $filePath): void
    {
        Log::error('High-performance import processing failed', [
            'session_id' => $this->session->session_id,
            'file_name' => $this->session->original_filename,
            'error' => $e->getMessage(),
            'error_class' => get_class($e),
            'performance_stats' => $this->getPerformanceStats(),
        ]);
    }

    /**
     * 🏁 Log processing completion
     */
    private function logProcessingCompletion(string $filePath, float $totalTime): void
    {
        $stats = $this->getPerformanceStats();
        
        Log::info('High-performance import processing completed', [
            'session_id' => $this->session->session_id,
            'file_name' => $this->session->original_filename,
            'total_time_seconds' => round($totalTime, 2),
            'performance_summary' => [
                'total_chunks' => $stats['metrics']['total_chunks'],
                'successful_chunks' => $stats['metrics']['successful_chunks'],
                'failed_chunks' => $stats['metrics']['failed_chunks'],
                'success_rate' => $stats['metrics']['total_chunks'] > 0 
                    ? round(($stats['metrics']['successful_chunks'] / $stats['metrics']['total_chunks']) * 100, 2) 
                    : 0,
            ],
        ]);
    }
}