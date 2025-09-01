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
 * ✨ IMAGE VARIANTS GENERATED EVENT
 * 
 * Fired when image variants have been generated successfully
 * Triggers UI refresh to show variant count and preview
 */
class ImageVariantsGenerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Image $originalImage,
        public array $generatedVariants
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
            'original_image_id' => $this->originalImage->id,
            'status' => 'variants_generated',
            'message' => count($this->generatedVariants) . " variants generated for '{$this->originalImage->display_title}'",
            'variant_count' => count($this->generatedVariants),
            'variants' => collect($this->generatedVariants)->map(fn($variant) => [
                'id' => $variant->id,
                'type' => $variant->getVariantType(),
                'size' => $variant->formatted_size,
                'url' => $variant->url,
            ])->toArray(),
        ];
    }
}