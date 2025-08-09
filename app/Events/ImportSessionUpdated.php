<?php

namespace App\Events;

use App\Models\ImportSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportSessionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ImportSession $session;

    public function __construct(ImportSession $session)
    {
        $this->session = $session;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('import.' . $this->session->session_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ImportSessionUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->session_id,
            'status' => $this->session->status,
            'progress_percentage' => $this->session->progress_percentage,
            'processed_rows' => $this->session->processed_rows,
            'current_operation' => $this->session->current_operation,
            'current_stage' => $this->session->current_stage,
        ];
    }
}
