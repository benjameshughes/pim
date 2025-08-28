<?php

namespace App\Events\Products;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductImportProgress implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $importId,
        public int $processed,
        public int $total,
        public string $status = 'processing',
        public ?string $currentAction = null,
        public ?string $currentItem = null,
        public ?array $stats = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('product-import.' . $this->importId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ProductImportProgress';
    }

    public function broadcastWith(): array
    {
        return [
            'importId' => $this->importId,
            'processed' => $this->processed,
            'total' => $this->total,
            'status' => $this->status,
            'currentAction' => $this->currentAction,
            'currentItem' => $this->currentItem,
            'percentage' => $this->total > 0 ? round(($this->processed / $this->total) * 100, 1) : 0,
            'stats' => $this->stats ?? [
                'products_created' => 0,
                'products_updated' => 0,
                'variants_created' => 0,
                'variants_updated' => 0,
                'errors' => 0
            ]
        ];
    }
}