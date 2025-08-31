<?php

namespace App\Services;

use App\Enums\ImageProcessingStatus;
use App\Models\Image;
use Illuminate\Support\Facades\Cache;

/**
 * 📊 IMAGE PROCESSING TRACKER
 *
 * Cache-based status tracking for image processing jobs
 * No database pollution - uses Redis/cache for ephemeral status
 */
class ImageProcessingTracker
{
    protected int $ttl = 3600; // 1 hour cache TTL

    public function setStatus(Image $image, ImageProcessingStatus $status): void
    {
        Cache::put($this->getCacheKey($image), $status->value, $this->ttl);
    }

    public function getStatus(Image $image): ?ImageProcessingStatus
    {
        $status = Cache::get($this->getCacheKey($image));
        
        return $status ? ImageProcessingStatus::from($status) : null;
    }

    public function isProcessing(Image $image): bool
    {
        return $this->getStatus($image) === ImageProcessingStatus::PROCESSING;
    }

    public function isPending(Image $image): bool
    {
        return $this->getStatus($image) === ImageProcessingStatus::PENDING;
    }

    public function isCompleted(Image $image): bool
    {
        return $this->getStatus($image) === ImageProcessingStatus::COMPLETED;
    }

    public function isFailed(Image $image): bool
    {
        return $this->getStatus($image) === ImageProcessingStatus::FAILED;
    }

    public function clearStatus(Image $image): void
    {
        Cache::forget($this->getCacheKey($image));
    }

    public function getStatusWithMeta(Image $image): ?array
    {
        $status = $this->getStatus($image);
        
        if (!$status) {
            return null;
        }

        return [
            'status' => $status,
            'label' => $status->label(),
            'color' => $status->color(),
            'icon' => $status->icon(),
        ];
    }

    /**
     * Get processing status for multiple images efficiently
     *
     * @param Image[] $images
     * @return array<int, array|null>
     */
    public function getMultipleStatuses(array $images): array
    {
        $keys = array_map(fn($image) => $this->getCacheKey($image), $images);
        $statuses = Cache::many($keys);
        
        $result = [];
        foreach ($images as $index => $image) {
            $key = $this->getCacheKey($image);
            $status = $statuses[$key] ?? null;
            
            $result[$image->id] = $status ? [
                'status' => ImageProcessingStatus::from($status),
                'label' => ImageProcessingStatus::from($status)->label(),
                'color' => ImageProcessingStatus::from($status)->color(),
                'icon' => ImageProcessingStatus::from($status)->icon(),
            ] : null;
        }
        
        return $result;
    }

    protected function getCacheKey(Image $image): string
    {
        return "image_processing:{$image->id}";
    }
}