<?php

namespace App\Events;

use App\Models\ProductImage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImageProcessingFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ProductImage $productImage,
        public string $errorMessage
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('image-processing'),
        ];
    }

    /**
     * Data to broadcast with the event
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->productImage->id,
            'type' => 'failed',
            'image_type' => $this->productImage->image_type,
            'error' => $this->errorMessage,
            'processing_status' => $this->productImage->processing_status,
        ];
    }
}
