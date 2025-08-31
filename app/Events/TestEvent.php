<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('test');
    }

    public function broadcastAs(): string
    {
        return 'TestEvent';
    }

    public function broadcastWith(): array
    {
        return [
            'user' => $this->user->toArray(),
        ];
    }
}
