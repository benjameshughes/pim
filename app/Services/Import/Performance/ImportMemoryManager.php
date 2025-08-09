<?php

namespace App\Services\Import\Performance;

use App\Models\ImportSession;
use Illuminate\Support\Facades\Log;

/**
 * 🧠 Import Memory Manager
 * 
 * Smart memory management specifically designed for import processing.
 * Monitors memory usage, performs cleanup, and prevents memory exhaustion.
 * 
 * Features:
 * - Real-time memory monitoring
 * - Automatic garbage collection
 * - Memory pressure detection
 * - Smart cleanup strategies
 * - Import-specific optimizations
 * 
 * Usage:
 * $manager = ImportMemoryManager::forSession($session);
 * $manager->monitor(); // Check and cleanup if needed
 * $manager->beforeChunkProcessing(); // Pre-chunk cleanup
 * $manager->afterChunkProcessing(); // Post-chunk cleanup
 */
class ImportMemoryManager
{
    private ImportSession $session;
    private int $memoryLimitBytes;
    private int $warningThresholdBytes;
    private int $criticalThresholdBytes;
    private array $memorySnapshots = [];
    private array $cleanupHistory = [];
    private int $lastCleanupTime = 0;
    private int $cleanupCooldown = 5; // 5 seconds between cleanups
    
    public function __construct(ImportSession $session, ?int $memoryLimitMb = null)
    {
        $this->session = $session;
        $this->memoryLimitBytes = $memoryLimitMb ? 
            $memoryLimitMb * 1024 * 1024 : 
            $this->getSystemMemoryLimit();
        
        $this->warningThresholdBytes = (int) ($this->memoryLimitBytes * 0.7);  // 70%
        $this->criticalThresholdBytes = (int) ($this->memoryLimitBytes * 0.85); // 85%
        
        $this->takeSnapshot('initialization');
    }

    /**
     * 🎯 Create memory manager for specific import session
     */
    public static function forSession(ImportSession $session, ?int $memoryLimitMb = null): self
    {
        return new self($session, $memoryLimitMb);
    }

    /**
     * 👀 Monitor memory usage and perform cleanup if needed
     */
    public function monitor(): array
    {
        $currentUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);
        
        $status = $this->getMemoryStatus($currentUsage);
        
        // Log detailed memory info in debug mode
        Log::debug('Import memory status', [
            'session_id' => $this->session->session_id,
            'current_usage' => $this->formatBytes($currentUsage),
            'peak_usage' => $this->formatBytes($peakUsage),
            'memory_limit' => $this->formatBytes($this->memoryLimitBytes),
            'status' => $status['level'],
            'usage_percentage' => $status['usage_percentage'],
        ]);
        
        // Perform cleanup if needed
        if ($status['needs_cleanup']) {
            $this->performCleanup($status['level']);
        }
        
        return $status;
    }

    /**
     * 🧹 Prepare for chunk processing
     */
    public function beforeChunkProcessing(int $chunkNumber): void
    {
        $this->takeSnapshot("before_chunk_{$chunkNumber}");
        
        // Proactive cleanup every 10 chunks or if memory is high
        if ($chunkNumber % 10 === 0 || $this->isMemoryHigh()) {
            $this->performCleanup('proactive');
        }
    }

    /**
     * 🧹 Cleanup after chunk processing
     */
    public function afterChunkProcessing(int $chunkNumber, bool $chunkFailed = false): void
    {
        $this->takeSnapshot("after_chunk_{$chunkNumber}");
        
        // Always cleanup after failed chunks to prevent memory leaks
        if ($chunkFailed) {
            $this->performCleanup('failure_recovery');
        }
        
        // Check if memory usage is growing unexpectedly
        $this->detectMemoryLeaks($chunkNumber);
    }

    /**
     * 🚨 Emergency memory cleanup
     */
    public function emergencyCleanup(): array
    {
        $memoryBefore = memory_get_usage(true);
        
        Log::warning('Import emergency memory cleanup initiated', [
            'session_id' => $this->session->session_id,
            'memory_before' => $this->formatBytes($memoryBefore),
            'memory_limit' => $this->formatBytes($this->memoryLimitBytes),
        ]);
        
        // Aggressive cleanup sequence
        $this->clearInternalCaches();
        $this->forceGarbageCollection();
        $this->clearModelCaches();
        $this->clearOpcodeCaches();
        
        $memoryAfter = memory_get_usage(true);
        $memoryFreed = $memoryBefore - $memoryAfter;
        
        $result = [
            'memory_before' => $memoryBefore,
            'memory_after' => $memoryAfter,
            'memory_freed' => $memoryFreed,
            'success' => $memoryFreed > 0,
        ];
        
        $this->recordCleanup('emergency', $result);
        
        Log::warning('Import emergency cleanup completed', [
            'session_id' => $this->session->session_id,
            'memory_freed' => $this->formatBytes($memoryFreed),
            'memory_after' => $this->formatBytes($memoryAfter),
        ]);
        
        return $result;
    }

    /**
     * 🔄 Perform memory cleanup based on severity level
     */
    private function performCleanup(string $level): array
    {
        // Prevent too frequent cleanups
        if (time() - $this->lastCleanupTime < $this->cleanupCooldown) {
            return ['skipped' => true, 'reason' => 'cooldown'];
        }
        
        $memoryBefore = memory_get_usage(true);
        
        switch ($level) {
            case 'warning':
            case 'proactive':
                $this->lightCleanup();
                break;
                
            case 'critical':
            case 'failure_recovery':
                $this->aggressiveCleanup();
                break;
                
            case 'emergency':
                return $this->emergencyCleanup();
                
            default:
                $this->forceGarbageCollection();
        }
        
        $memoryAfter = memory_get_usage(true);
        $memoryFreed = $memoryBefore - $memoryAfter;
        
        $result = [
            'level' => $level,
            'memory_before' => $memoryBefore,
            'memory_after' => $memoryAfter,
            'memory_freed' => $memoryFreed,
            'timestamp' => time(),
        ];
        
        $this->recordCleanup($level, $result);
        $this->lastCleanupTime = time();
        
        Log::info('Import memory cleanup completed', [
            'session_id' => $this->session->session_id,
            'level' => $level,
            'memory_freed' => $this->formatBytes($memoryFreed),
            'memory_after' => $this->formatBytes($memoryAfter),
        ]);
        
        return $result;
    }

    /**
     * 🧹 Light cleanup for normal operations
     */
    private function lightCleanup(): void
    {
        // Force garbage collection
        $this->forceGarbageCollection();
        
        // Clear internal snapshots if too many
        if (count($this->memorySnapshots) > 50) {
            $this->memorySnapshots = array_slice($this->memorySnapshots, -25);
        }
    }

    /**
     * 🧹 Aggressive cleanup for high memory usage
     */
    private function aggressiveCleanup(): void
    {
        // All light cleanup actions
        $this->lightCleanup();
        
        // Clear more caches
        $this->clearInternalCaches();
        $this->clearModelCaches();
        
        // Multiple garbage collection cycles
        for ($i = 0; $i < 3; $i++) {
            gc_collect_cycles();
        }
    }

    /**
     * 🗑️ Clear internal caches
     */
    private function clearInternalCaches(): void
    {
        // Clear our own caches
        $this->memorySnapshots = array_slice($this->memorySnapshots, -10);
        $this->cleanupHistory = array_slice($this->cleanupHistory, -10);
    }

    /**
     * 🗑️ Clear Laravel/Eloquent model caches
     */
    private function clearModelCaches(): void
    {
        // Clear Eloquent model cache if available
        if (class_exists('\Illuminate\Database\Eloquent\Model')) {
            \Illuminate\Database\Eloquent\Model::clearBootedModels();
        }
        
        // Clear relation cache
        if (method_exists($this->session, 'unsetRelations')) {
            $this->session->unsetRelations();
        }
    }

    /**
     * 🗑️ Clear opcode caches if available
     */
    private function clearOpcodeCaches(): void
    {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    /**
     * 🔄 Force garbage collection
     */
    private function forceGarbageCollection(): int
    {
        $cycles = gc_collect_cycles();
        
        if ($cycles > 0) {
            Log::debug('Garbage collection freed cycles', [
                'session_id' => $this->session->session_id,
                'cycles_freed' => $cycles,
            ]);
        }
        
        return $cycles;
    }

    /**
     * 📊 Get current memory status
     */
    private function getMemoryStatus(int $currentUsage): array
    {
        $usagePercentage = ($currentUsage / $this->memoryLimitBytes) * 100;
        
        $level = match(true) {
            $currentUsage >= $this->criticalThresholdBytes => 'critical',
            $currentUsage >= $this->warningThresholdBytes => 'warning',
            default => 'normal'
        };
        
        return [
            'current_usage' => $currentUsage,
            'memory_limit' => $this->memoryLimitBytes,
            'usage_percentage' => round($usagePercentage, 2),
            'level' => $level,
            'needs_cleanup' => $level !== 'normal',
            'available_memory' => $this->memoryLimitBytes - $currentUsage,
        ];
    }

    /**
     * 🔍 Check if memory usage is high
     */
    private function isMemoryHigh(): bool
    {
        return memory_get_usage(true) >= $this->warningThresholdBytes;
    }

    /**
     * 🔍 Detect potential memory leaks
     */
    private function detectMemoryLeaks(int $chunkNumber): void
    {
        // Only check every 5 chunks to avoid noise
        if ($chunkNumber % 5 !== 0 || count($this->memorySnapshots) < 10) {
            return;
        }
        
        $recent = array_slice($this->memorySnapshots, -10);
        $memoryGrowth = [];
        
        for ($i = 1; $i < count($recent); $i++) {
            $growth = $recent[$i]['memory'] - $recent[$i-1]['memory'];
            $memoryGrowth[] = $growth;
        }
        
        $avgGrowth = array_sum($memoryGrowth) / count($memoryGrowth);
        $growthMb = $avgGrowth / 1024 / 1024;
        
        // If memory consistently grows by more than 2MB per chunk, warn about potential leak
        if ($growthMb > 2) {
            Log::warning('Potential memory leak detected in import', [
                'session_id' => $this->session->session_id,
                'chunk_number' => $chunkNumber,
                'average_growth_mb' => round($growthMb, 2),
                'current_usage' => $this->formatBytes(memory_get_usage(true)),
            ]);
            
            $this->session->addWarning(
                "Potential memory leak detected. Average memory growth: " . 
                round($growthMb, 2) . "MB per chunk. Consider reducing chunk size."
            );
        }
    }

    /**
     * 📸 Take memory usage snapshot
     */
    private function takeSnapshot(string $label): void
    {
        $this->memorySnapshots[] = [
            'label' => $label,
            'timestamp' => microtime(true),
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];
        
        // Keep only recent snapshots
        if (count($this->memorySnapshots) > 100) {
            $this->memorySnapshots = array_slice($this->memorySnapshots, -50);
        }
    }

    /**
     * 📝 Record cleanup operation
     */
    private function recordCleanup(string $level, array $result): void
    {
        $this->cleanupHistory[] = array_merge($result, [
            'level' => $level,
            'timestamp' => time(),
        ]);
        
        // Keep only recent cleanup history
        if (count($this->cleanupHistory) > 20) {
            $this->cleanupHistory = array_slice($this->cleanupHistory, -10);
        }
    }

    /**
     * 💾 Get system memory limit in bytes
     */
    private function getSystemMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        
        if ($limit === '-1') {
            return 512 * 1024 * 1024; // 512MB default for unlimited
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
     * 📊 Get memory statistics
     */
    public function getStats(): array
    {
        $currentStatus = $this->monitor();
        
        return [
            'session_id' => $this->session->session_id,
            'current_status' => $currentStatus,
            'memory_limit' => $this->formatBytes($this->memoryLimitBytes),
            'warning_threshold' => $this->formatBytes($this->warningThresholdBytes),
            'critical_threshold' => $this->formatBytes($this->criticalThresholdBytes),
            'snapshots_count' => count($this->memorySnapshots),
            'cleanups_performed' => count($this->cleanupHistory),
            'recent_snapshots' => array_slice($this->memorySnapshots, -5),
            'recent_cleanups' => array_slice($this->cleanupHistory, -3),
        ];
    }

    /**
     * 🎯 Set custom memory thresholds
     */
    public function setThresholds(float $warningPercent = 0.7, float $criticalPercent = 0.85): self
    {
        $this->warningThresholdBytes = (int) ($this->memoryLimitBytes * $warningPercent);
        $this->criticalThresholdBytes = (int) ($this->memoryLimitBytes * $criticalPercent);
        
        return $this;
    }
}