<?php

namespace App\Events;

use App\Models\ImportSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportSessionCreated implements ShouldBroadcast
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
            new Channel('dashboard-updates'),
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