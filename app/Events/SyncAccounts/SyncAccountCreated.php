<?php

namespace App\Events\SyncAccounts;

use App\Models\SyncAccount;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncAccountCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SyncAccount $account) {}

    public function broadcastOn(): array
    {
        return [new Channel('sync-accounts')];
    }

    public function broadcastAs(): string
    {
        return 'SyncAccountCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->account->id,
            'channel' => $this->account->channel,
            'name' => $this->account->name,
            'display_name' => $this->account->display_name,
            'is_active' => $this->account->is_active,
        ];
    }
}

