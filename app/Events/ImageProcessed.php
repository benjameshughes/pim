<?php

namespace App\Events;

use App\Models\ProductImage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImageProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ProductImage $productImage
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
            'type' => 'processed',
            'image_type' => $this->productImage->image_type,
            'url' => $this->productImage->url,
            'variants' => $this->productImage->variants,
            'processing_status' => $this->productImage->processing_status,
        ];
    }
}
