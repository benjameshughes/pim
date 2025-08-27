<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $data = []) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('test-channel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TestEvent';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}