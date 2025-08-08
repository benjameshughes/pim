<?php

namespace App\Media\Actions;

use App\Jobs\ProcessImageToR2;
use App\Models\ProductImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager as InterventionManager;
use Intervention\Image\Drivers\Gd\Driver;

/**
 * Single Responsibility: Process images (resize, optimize, create thumbnails)
 */
class ImageProcessingAction
{
    protected InterventionManager $manager;

    public function __construct()
    {
        $this->manager = new InterventionManager(new Driver);
    }

    public function execute(?Collection $images, array $config): ImageProcessingResult
    {
        if (!$images || $images->isEmpty()) {
            return new ImageProcessingResult(collect(), collect());
        }

        $processed = collect();
        $failed = collect();

        foreach ($images as $image) {
            try {
                if ($config['processImmediately']) {
                    $this->processImageSync($image, $config);
                    $processed->push($image);
                } else {
                    ProcessImageToR2::dispatch($image);
                    $processed->push($image);
                }
            } catch (\Exception $e) {
                $failed->push([
                    'image' => $image,
                    'error' => $e->getMessage()
                ]);
                
                Log::error('Image processing failed', [
                    'image_id' => $image->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return new ImageProcessingResult($processed, $failed);
    }

    protected function processImageSync(ProductImage $productImage, array $config): void
    {
        $productImage->markAsProcessing();

        $tempPath = $this->getTemporaryPath($productImage);
        if (!$tempPath) {
            throw new \Exception('Could not access original image file');
        }

        $variants = $this->createThumbnails($tempPath, $config);
        
        // Update image record with processing results
        $productImage->update([
            'processing_status' => ProductImage::PROCESSING_COMPLETED,
            'metadata' => array_merge($productImage->metadata ?? [], [
                'thumbnails_created' => array_keys($variants),
                'processed_at' => now()->toISOString(),
            ]),
        ]);

        $this->cleanupTempFiles($tempPath, $variants);
    }

    protected function createThumbnails(string $originalPath, array $config): array
    {
        if (!$config['createThumbnails']) {
            return [];
        }

        $thumbnails = [];

        foreach (ProductImage::SIZES as $sizeName => $sizeConfig) {
            try {
                $image = $this->manager->read($originalPath);
                
                $image = $image->scaleDown(
                    width: $sizeConfig['width'],
                    height: $sizeConfig['height']
                );

                $thumbnailPath = $this->getThumbnailPath($originalPath, $sizeName);
                $image->toJpeg(quality: $sizeConfig['quality'])->save($thumbnailPath);

                $thumbnails[$sizeName] = [
                    'path' => $thumbnailPath,
                    'dimensions' => [
                        'width' => $image->width(),
                        'height' => $image->height()
                    ]
                ];

            } catch (\Exception $e) {
                Log::warning('Failed to create thumbnail', [
                    'size' => $sizeName,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $thumbnails;
    }

    protected function getTemporaryPath(ProductImage $productImage): ?string
    {
        $disk = $productImage->storage_disk ?: 'public';
        
        if (!\Storage::disk($disk)->exists($productImage->image_path)) {
            return null;
        }

        $tempDir = storage_path('app/temp/images');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPath = $tempDir . '/' . uniqid() . '_' . basename($productImage->image_path);
        $content = \Storage::disk($disk)->get($productImage->image_path);
        file_put_contents($tempPath, $content);

        return $tempPath;
    }

    protected function getThumbnailPath(string $originalPath, string $sizeName): string
    {
        $pathInfo = pathinfo($originalPath);
        $tempDir = dirname($originalPath);
        
        return $tempDir . '/' . $pathInfo['filename'] . '_' . $sizeName . '.jpg';
    }

    protected function cleanupTempFiles(string $originalPath, array $thumbnails): void
    {
        if (file_exists($originalPath)) {
            unlink($originalPath);
        }

        foreach ($thumbnails as $thumbnail) {
            if (isset($thumbnail['path']) && file_exists($thumbnail['path'])) {
                unlink($thumbnail['path']);
            }
        }
    }
}

/**
 * Value Object for processing results
 */
class ImageProcessingResult
{
    public function __construct(
        public readonly Collection $processed,
        public readonly Collection $failed
    ) {}

    public function hasFailures(): bool
    {
        return $this->failed->isNotEmpty();
    }

    public function getProcessedCount(): int
    {
        return $this->processed->count();
    }

    public function getFailedCount(): int
    {
        return $this->failed->count();
    }

    public function getMessage(): string
    {
        $processed = $this->getProcessedCount();
        $failed = $this->getFailedCount();

        if ($failed === 0) {
            return "Successfully processed {$processed} images";
        }

        return "Processed {$processed} images, {$failed} failed";
    }
}