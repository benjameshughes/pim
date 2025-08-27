<?php

namespace App\Events\Barcodes;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BarcodeImportProgress implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $importId,
        public int $processed,
        public string $status = 'processing'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('barcode-import.' . $this->importId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'BarcodeImportProgress';
    }

    public function broadcastWith(): array
    {
        return [
            'importId' => $this->importId,
            'processed' => $this->processed,
            'status' => $this->status,
        ];
    }

}
