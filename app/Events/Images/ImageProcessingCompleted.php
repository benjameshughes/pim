<?php

namespace App\Events\Images;

use App\Models\Image;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 🖼️ IMAGE PROCESSING COMPLETED EVENT
 * 
 * Fired when an image has been fully processed (dimensions extracted)
 * Triggers UI refresh to show the processed image
 */
class ImageProcessingCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Image $image
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('images'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'image_id' => $this->image->id,
            'status' => 'processed',
            'message' => "Image '{$this->image->display_title}' processed successfully",
            'image' => [
                'id' => $this->image->id,
                'title' => $this->image->display_title,
                'width' => $this->image->width,
                'height' => $this->image->height,
                'size' => $this->image->formatted_size,
            ],
        ];
    }
}