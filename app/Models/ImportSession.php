<?php

namespace App\Models;

use App\Events\ImportSessionCreated;
use App\Events\ImportSessionUpdated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ImportSession extends Model
{
    use HasFactory;
    protected $fillable = [
        'session_id',
        'user_id',
        'original_filename',
        'file_path',
        'file_type',
        'file_size',
        'file_hash',
        'status',
        'current_stage',
        'current_operation',
        'progress_percentage',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'skipped_rows',
        'started_at',
        'completed_at',
        'processing_time_seconds',
        'rows_per_second',
        'configuration',
        'column_mapping',
        'file_analysis',
        'dry_run_results',
        'final_results',
        'errors',
        'warnings',
        'failure_reason',
        'current_job_id',
        'job_chain_status',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'progress_percentage' => 'integer',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'successful_rows' => 'integer',
        'failed_rows' => 'integer',
        'skipped_rows' => 'integer',
        'processing_time_seconds' => 'integer',
        'rows_per_second' => 'decimal:2',
        'configuration' => 'array',
        'column_mapping' => 'array',
        'file_analysis' => 'array',
        'dry_run_results' => 'array',
        'final_results' => 'array',
        'errors' => 'array',
        'warnings' => 'array',
        'job_chain_status' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->session_id)) {
                $model->session_id = Str::random(32);
            }
        });

        // Broadcast when import session is created
        static::created(function ($model) {
            event(new ImportSessionCreated($model));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updateProgress(string $stage, string $operation, int $percentage): self
    {
        if ($percentage < 0 || $percentage > 100) {
            throw new \InvalidArgumentException('Progress percentage must be between 0 and 100');
        }

        // Update the model
        $this->update([
            'current_stage' => $stage,
            'current_operation' => $operation,
            'progress_percentage' => $percentage,
        ]);

        // Directly broadcast the event
        broadcast(new ImportSessionUpdated($this));

        return $this;
    }

    public function incrementProcessedRows(int $successful = 0, int $failed = 0, int $skipped = 0): self
    {
        $this->increment('processed_rows', $successful + $failed + $skipped);
        $this->increment('successful_rows', $successful);
        $this->increment('failed_rows', $failed);
        $this->increment('skipped_rows', $skipped);

        // Update processing speed
        if ($this->started_at && $this->processed_rows > 0) {
            $seconds = now()->diffInSeconds($this->started_at);
            if ($seconds > 0) {
                $this->update(['rows_per_second' => $this->processed_rows / $seconds]);
            }
        }

        return $this;
    }

    public function addError(string $error): self
    {
        $errors = $this->errors ?? [];
        $errors[] = [
            'message' => $error,
            'timestamp' => now()->toISOString(),
        ];
        
        $this->update(['errors' => $errors]);
        
        return $this;
    }

    public function addWarning(string $warning): self
    {
        $warnings = $this->warnings ?? [];
        $warnings[] = [
            'message' => $warning,
            'timestamp' => now()->toISOString(),
        ];
        
        $this->update(['warnings' => $warnings]);
        
        return $this;
    }

    public function markAsStarted(): self
    {
        if ($this->status === 'completed') {
            throw new \InvalidArgumentException('Cannot start completed import');
        }

        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        return $this;
    }

    public function markAsCompleted(): self
    {
        $completedAt = now();
        $processingTime = $this->started_at ? $this->started_at->diffInSeconds($completedAt) : 0;

        $this->update([
            'status' => 'completed',
            'completed_at' => $completedAt,
            'processing_time_seconds' => $processingTime,
            'progress_percentage' => 100,
        ]);

        return $this;
    }

    public function markAsFailed(string $reason): self
    {
        $this->addError($reason);
        
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'completed_at' => now(),
        ]);

        return $this;
    }

    public function getEstimatedCompletionAttribute(): ?string
    {
        if (!$this->rows_per_second || !$this->total_rows || $this->processed_rows <= 0) {
            return null;
        }

        $remainingRows = $this->total_rows - $this->processed_rows;
        $secondsRemaining = $remainingRows / $this->rows_per_second;
        
        return now()->addSeconds($secondsRemaining)->format('H:i:s');
    }

    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_rows <= 0) {
            return 0;
        }

        return min(100, (int) round(($this->processed_rows / $this->total_rows) * 100));
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['analyzing_file', 'dry_run', 'processing']);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled']);
    }

    public function canCancel(): bool
    {
        return in_array($this->status, ['initializing', 'analyzing_file', 'awaiting_mapping', 'dry_run']);
    }

    // Query Scopes
    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // Additional methods for test compatibility
    public function getProcessingTimeSecondsAttribute()
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInSeconds($this->completed_at);
        }

        return null;
    }

}
