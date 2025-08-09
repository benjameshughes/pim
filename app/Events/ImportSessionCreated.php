<?php

namespace App\Events;

use App\Models\ImportSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportSessionCreated implements ShouldBroadcastNow
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
        return 'ImportSessionCreated';
    }

    public function broadcastWith(): array
    {
        return $this->session->toArray();
    }
}