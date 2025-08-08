<?php

namespace App\Events\Import;

use App\Models\ImportSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ImportSession $session
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('import-progress.' . $this->session->session_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->session_id,
            'status' => $this->session->status,
            'current_stage' => $this->session->current_stage,
            'current_operation' => $this->session->current_operation,
            'progress_percentage' => $this->session->progress_percentage,
            'processed_rows' => $this->session->processed_rows,
            'total_rows' => $this->session->total_rows,
            'successful_rows' => $this->session->successful_rows,
            'failed_rows' => $this->session->failed_rows,
            'skipped_rows' => $this->session->skipped_rows,
            'rows_per_second' => $this->session->rows_per_second,
            'estimated_completion' => $this->session->estimated_completion,
            'errors' => $this->session->errors ?? [],
            'warnings' => $this->session->warnings ?? [],
            'is_running' => $this->session->isRunning(),
            'is_completed' => $this->session->isCompleted(),
            'can_cancel' => $this->session->canCancel(),
            'updated_at' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ImportProgressUpdated';
    }
}