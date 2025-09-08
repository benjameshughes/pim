<?php

namespace App\Events\SyncAccounts;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncAccountDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $accountId) {}

    public function broadcastOn(): array
    {
        return [new Channel('sync-accounts')];
    }

    public function broadcastAs(): string
    {
        return 'SyncAccountDeleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->accountId,
        ];
    }
}

