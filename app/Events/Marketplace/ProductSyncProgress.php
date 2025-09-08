<?php

namespace App\Events\Marketplace;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 📻 PRODUCT SYNC PROGRESS EVENT
 *
 * Broadcasts real-time sync status updates for marketplace operations
 * so Livewire/JS can reflect job progress without polling.
 */
class ProductSyncProgress implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $productId,
        public int $syncAccountId,
        public string $channel,
        public string $operation,
        public string $status,        // queued|processing|success|failed
        public string $message = '',
        public int $percentage = 0
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("product-sync.{$this->productId}"),
            new Channel("product-sync.account.{$this->syncAccountId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ProductSyncProgress';
    }

    public function broadcastWith(): array
    {
        return [
            'productId' => $this->productId,
            'accountId' => $this->syncAccountId,
            'channel' => $this->channel,
            'operation' => $this->operation,
            'status' => $this->status,
            'message' => $this->message,
            'percentage' => $this->percentage,
        ];
    }
}

