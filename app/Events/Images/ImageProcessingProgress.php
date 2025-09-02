<?php

namespace App\Events\Images;

use App\Enums\ImageProcessingStatus;
use App\Models\Image;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 📻 IMAGE PROCESSING PROGRESS EVENT
 * 
 * Real-time progress updates for image processing jobs
 * Uses ShouldBroadcastNow for instant broadcasting like import system
 */
class ImageProcessingProgress implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $imageId,
        public string $imageUuid,
        public ImageProcessingStatus $status,
        public string $currentAction,
        public int $percentage = 0
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("image-processing.{$this->imageId}"), // Individual channel per image
        ];
    }

    public function broadcastAs(): string
    {
        return 'ImageProcessingProgress';
    }

    public function broadcastWith(): array
    {
        return [
            'imageId' => $this->imageId,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'statusColor' => $this->status->color(),
            'statusIcon' => $this->status->icon(),
            'currentAction' => $this->currentAction,
            'percentage' => $this->percentage,
        ];
    }
}