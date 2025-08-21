<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 📝 SYNC LOG MODEL
 *
 * Comprehensive audit trail for all sync operations.
 * Tracks performance, success rates, and detailed operation logs.
 *
 * Perfect for debugging sync issues and monitoring system health.
 */
class SyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sync_account_id',
        'sync_status_id',
        'action',
        'status',
        'message',
        'details',
        'started_at',
        'completed_at',
        'duration_ms',
        'batch_id',
        'items_processed',
        'items_successful',
        'items_failed',
    ];

    protected $casts = [
        'details' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 📦 PRODUCT RELATIONSHIP
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 🏢 SYNC ACCOUNT RELATIONSHIP
     */
    public function syncAccount(): BelongsTo
    {
        return $this->belongsTo(SyncAccount::class);
    }

    /**
     * 📊 SYNC STATUS RELATIONSHIP
     */
    public function syncStatus(): BelongsTo
    {
        return $this->belongsTo(SyncStatus::class);
    }

    /**
     * ✅ STATUS CHECKS
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'started';
    }

    public function hasWarning(): bool
    {
        return $this->status === 'warning';
    }

    /**
     * 🎯 SCOPES
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'started');
    }

    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->whereHas('syncAccount', fn ($q) => $q->where('channel', $channel));
    }

    public function scopeRecentActivity($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeBatch($query, string $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    /**
     * 📈 PERFORMANCE METRICS
     */
    public function getDurationAttribute(): ?string
    {
        if (! $this->duration_ms) {
            return null;
        }

        if ($this->duration_ms < 1000) {
            return "{$this->duration_ms}ms";
        }

        return round($this->duration_ms / 1000, 2).'s';
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->items_processed === 0) {
            return 0.0;
        }

        return round(($this->items_successful / $this->items_processed) * 100, 1);
    }

    /**
     * 🎨 STATUS BADGE CLASS
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'success' => 'bg-green-100 text-green-800',
            'started' => 'bg-blue-100 text-blue-800',
            'failed' => 'bg-red-100 text-red-800',
            'warning' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    /**
     * 📊 CREATE LOG ENTRY
     *
     * Factory method for creating sync log entries
     */
    public static function createEntry(
        SyncAccount $account,
        string $action,
        ?Product $product = null,
        ?SyncStatus $syncStatus = null
    ): self {
        return static::create([
            'product_id' => $product?->id,
            'sync_account_id' => $account->id,
            'sync_status_id' => $syncStatus?->id,
            'action' => $action,
            'status' => 'started',
            'started_at' => now(),
        ]);
    }

    /**
     * ✅ MARK AS SUCCESSFUL
     */
    public function markAsSuccessful(?string $message = null, array $details = []): void
    {
        $this->update([
            'status' => 'success',
            'message' => $message ?: 'Operation completed successfully',
            'details' => array_merge($this->details ?? [], $details),
            'completed_at' => now(),
            'duration_ms' => $this->started_at ? now()->diffInMilliseconds($this->started_at) : null,
            'items_successful' => $this->items_processed ?: 1,
        ]);
    }

    /**
     * ❌ MARK AS FAILED
     */
    public function markAsFailed(string $message, array $details = []): void
    {
        $this->update([
            'status' => 'failed',
            'message' => $message,
            'details' => array_merge($this->details ?? [], $details),
            'completed_at' => now(),
            'duration_ms' => $this->started_at ? now()->diffInMilliseconds($this->started_at) : null,
            'items_failed' => $this->items_processed ?: 1,
        ]);
    }

    /**
     * ⚠️ MARK AS WARNING
     */
    public function markAsWarning(string $message, array $details = []): void
    {
        $this->update([
            'status' => 'warning',
            'message' => $message,
            'details' => array_merge($this->details ?? [], $details),
            'completed_at' => now(),
            'duration_ms' => $this->started_at ? now()->diffInMilliseconds($this->started_at) : null,
        ]);
    }

    /**
     * 📊 UPDATE BATCH PROGRESS
     */
    public function updateBatchProgress(int $processed, int $successful, int $failed): void
    {
        $this->update([
            'items_processed' => $processed,
            'items_successful' => $successful,
            'items_failed' => $failed,
        ]);
    }

    /**
     * 🔍 SEARCH LOGS
     */
    public static function searchLogs(array $filters = []): \Illuminate\Database\Eloquent\Builder
    {
        $query = static::with(['product:id,name', 'syncAccount'])
            ->latest();

        if (! empty($filters['channel'])) {
            $query->forChannel($filters['channel']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }

        if (! empty($filters['hours'])) {
            $query->recentActivity($filters['hours']);
        }

        return $query;
    }

    /**
     * 📈 GET PERFORMANCE STATS
     */
    public static function getPerformanceStats(?string $channel = null, int $hours = 24): array
    {
        $query = static::recentActivity($hours);

        if ($channel) {
            $query->forChannel($channel);
        }

        $logs = $query->get();

        return [
            'total_operations' => $logs->count(),
            'successful' => $logs->where('status', 'success')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'warnings' => $logs->where('status', 'warning')->count(),
            'avg_duration_ms' => $logs->whereNotNull('duration_ms')->avg('duration_ms'),
            'success_rate' => $logs->count() > 0
                ? round(($logs->where('status', 'success')->count() / $logs->count()) * 100, 1)
                : 0,
        ];
    }
}
