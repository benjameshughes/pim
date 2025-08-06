<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Cache;

class FileProcessingProgress extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'file_name',
        'file_path',
        'processing_type',
        'status',
        'progress_percent',
        'current_step',
        'total_steps',
        'message',
        'result_data',
        'error_message',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'result_data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress_percent' => 'float'
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_ANALYZING = 'analyzing';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    // Processing type constants
    const TYPE_FILE_ANALYSIS = 'file_analysis';
    const TYPE_SAMPLE_DATA = 'sample_data';
    const TYPE_DRY_RUN_DATA = 'dry_run_data';
    const TYPE_FULL_IMPORT_DATA = 'full_import_data';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Update the progress status and optionally set message and result data
     */
    public function updateStatus(string $status, string $message = null, array $resultData = null): void
    {
        $updates = ['status' => $status];
        
        if ($message !== null) {
            $updates['message'] = $message;
        }
        
        if ($resultData !== null) {
            $updates['result_data'] = $resultData;
        }

        if ($status === self::STATUS_COMPLETED || $status === self::STATUS_FAILED) {
            $updates['completed_at'] = now();
        }

        if ($status === self::STATUS_ANALYZING || $status === self::STATUS_PROCESSING) {
            if (!$this->started_at) {
                $updates['started_at'] = now();
            }
        }

        $this->update($updates);
        
        // Cache the progress for real-time updates
        $this->cacheProgress();
    }

    /**
     * Update the progress percentage
     */
    public function updateProgress(float $percent, string $message = null): void
    {
        $updates = [
            'progress_percent' => max(0, min(100, $percent))
        ];
        
        if ($message !== null) {
            $updates['message'] = $message;
        }

        $this->update($updates);
        $this->cacheProgress();
    }

    /**
     * Mark the progress as failed with error message
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now()
        ]);
        
        $this->cacheProgress();
    }

    /**
     * Check if the processing is still active
     */
    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_ANALYZING,
            self::STATUS_PROCESSING
        ]);
    }

    /**
     * Check if the processing is completed successfully
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the processing has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Get the progress percentage as an integer
     */
    public function getProgressPercentage(): int
    {
        return (int) round($this->progress_percent ?? 0);
    }

    /**
     * Get the elapsed time in human readable format
     */
    public function getElapsedTime(): ?string
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();
        return $this->started_at->diffForHumans($endTime, true);
    }

    /**
     * Get the current processing step description
     */
    public function getCurrentStepDescription(): string
    {
        if ($this->total_steps && $this->current_step) {
            return "Step {$this->current_step} of {$this->total_steps}";
        }

        return match($this->status) {
            self::STATUS_PENDING => 'Waiting to start...',
            self::STATUS_ANALYZING => 'Analyzing file...',
            self::STATUS_PROCESSING => 'Processing data...',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Unknown status'
        };
    }

    /**
     * Cache the current progress for real-time updates
     */
    private function cacheProgress(): void
    {
        $progressData = [
            'id' => $this->id,
            'status' => $this->status,
            'progress_percent' => $this->getProgressPercentage(),
            'message' => $this->message,
            'current_step_description' => $this->getCurrentStepDescription(),
            'elapsed_time' => $this->getElapsedTime(),
            'is_active' => $this->isActive(),
            'is_completed' => $this->isCompleted(),
            'has_failed' => $this->hasFailed(),
            'error_message' => $this->error_message,
            'result_data' => $this->result_data,
            'updated_at' => $this->updated_at->toISOString()
        ];

        // Cache for 1 hour with user-specific key
        $cacheKey = "file_progress_{$this->user_id}_{$this->id}";
        Cache::put($cacheKey, $progressData, 3600);
        
        // Also cache with a general key for the session
        Cache::put("file_progress_{$this->id}", $progressData, 3600);
    }

    /**
     * Get cached progress data
     */
    public static function getCachedProgress(string $progressId, ?int $userId = null): ?array
    {
        $cacheKey = $userId 
            ? "file_progress_{$userId}_{$progressId}"
            : "file_progress_{$progressId}";
            
        return Cache::get($cacheKey);
    }

    /**
     * Create a new progress record for file analysis
     */
    public static function createForFileAnalysis(int $userId, string $fileName, string $filePath): self
    {
        return self::create([
            'user_id' => $userId,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'processing_type' => self::TYPE_FILE_ANALYSIS,
            'status' => self::STATUS_PENDING,
            'progress_percent' => 0,
            'message' => 'Queued for file analysis...'
        ]);
    }

    /**
     * Create a new progress record for data loading
     */
    public static function createForDataLoading(int $userId, string $fileName, string $dataType): self
    {
        $processingTypes = [
            'sample' => self::TYPE_SAMPLE_DATA,
            'dry_run' => self::TYPE_DRY_RUN_DATA,
            'full' => self::TYPE_FULL_IMPORT_DATA
        ];

        return self::create([
            'user_id' => $userId,
            'file_name' => $fileName,
            'processing_type' => $processingTypes[$dataType] ?? self::TYPE_SAMPLE_DATA,
            'status' => self::STATUS_PENDING,
            'progress_percent' => 0,
            'message' => "Queued for {$dataType} data loading..."
        ]);
    }

    /**
     * Clean up old progress records
     */
    public static function cleanupOldRecords(int $daysOld = 7): int
    {
        return self::where('created_at', '<', now()->subDays($daysOld))
            ->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED])
            ->delete();
    }

    /**
     * Get active processes for a user
     */
    public static function getActiveForUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('user_id', $userId)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_ANALYZING, self::STATUS_PROCESSING])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}