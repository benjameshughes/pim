<?php

namespace App\Media\Actions;

use App\Models\ProductImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Single Responsibility: Handle final storage to R2/S3
 */
class ImageStorageAction
{
    public function execute(?Collection $images, array $config): ImageStorageResult
    {
        if (!$images || $images->isEmpty()) {
            return new ImageStorageResult(collect(), collect());
        }

        $stored = collect();
        $failed = collect();

        foreach ($images as $image) {
            try {
                $this->storeImageToCloud($image, $config);
                $stored->push($image);
            } catch (\Exception $e) {
                $failed->push([
                    'image' => $image,
                    'error' => $e->getMessage()
                ]);

                Log::error('Image storage failed', [
                    'image_id' => $image->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return new ImageStorageResult($stored, $failed);
    }

    protected function storeImageToCloud(ProductImage $productImage, array $config): void
    {
        $originalDisk = $productImage->storage_disk ?: 'public';
        $targetDisk = $config['storageDisk'] ?: 'images';

        // Skip if already on target disk
        if ($originalDisk === $targetDisk) {
            return;
        }

        // Generate organized storage path
        $storagePath = $this->generateStoragePath($productImage);
        
        // Copy file to target disk
        $content = Storage::disk($originalDisk)->get($productImage->image_path);
        Storage::disk($targetDisk)->put($storagePath, $content);

        // Update database record
        $productImage->update([
            'storage_disk' => $targetDisk,
            'image_path' => $storagePath,
            'metadata' => array_merge($productImage->metadata ?? [], [
                'stored_at' => now()->toISOString(),
                'original_disk' => $originalDisk,
                'storage_path' => $storagePath,
            ]),
        ]);

        // Clean up temporary file
        if ($originalDisk === 'public' && str_contains($productImage->image_path, '/temp/')) {
            Storage::disk($originalDisk)->delete($productImage->image_path);
        }

        Log::info('Image stored successfully', [
            'image_id' => $productImage->id,
            'from_disk' => $originalDisk,
            'to_disk' => $targetDisk,
            'path' => $storagePath,
        ]);
    }

    protected function generateStoragePath(ProductImage $productImage): string
    {
        $year = now()->year;
        $month = now()->format('m');
        $day = now()->format('d');

        // Organize by model type and date
        if ($productImage->product_id) {
            $basePath = "products/{$year}/{$month}/{$day}/product-{$productImage->product_id}";
        } elseif ($productImage->variant_id) {
            $basePath = "products/{$year}/{$month}/{$day}/variant-{$productImage->variant_id}";
        } else {
            $basePath = "products/{$year}/{$month}/{$day}/unassigned";
        }

        // Add image type subfolder
        $basePath .= "/{$productImage->image_type}";

        // Generate unique filename
        $extension = pathinfo($productImage->image_path, PATHINFO_EXTENSION);
        $filename = $productImage->id . '_' . uniqid() . '.' . $extension;

        return "{$basePath}/{$filename}";
    }
}

/**
 * Value Object for storage results
 */
class ImageStorageResult
{
    public function __construct(
        public readonly Collection $stored,
        public readonly Collection $failed
    ) {}

    public function hasFailures(): bool
    {
        return $this->failed->isNotEmpty();
    }

    public function getStoredCount(): int
    {
        return $this->stored->count();
    }

    public function getFailedCount(): int
    {
        return $this->failed->count();
    }

    public function getMessage(): string
    {
        $stored = $this->getStoredCount();
        $failed = $this->getFailedCount();

        if ($failed === 0) {
            return "Successfully stored {$stored} images";
        }

        return "Stored {$stored} images, {$failed} failed";
    }
}