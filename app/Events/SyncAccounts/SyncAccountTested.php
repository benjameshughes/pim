<?php

namespace App\Events\SyncAccounts;

use App\Models\SyncAccount;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncAccountTested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SyncAccount $account, public array $result) {}

    public function broadcastOn(): array
    {
        return [new Channel('sync-accounts')];
    }

    public function broadcastAs(): string
    {
        return 'SyncAccountTested';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->account->id,
            'success' => $this->result['success'] ?? false,
            'message' => $this->result['message'] ?? null,
        ];
    }
}

