<?php

namespace App\Services;

use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageProcessingService
{
    private ImageManager $manager;
    private string $tempPath;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
        $this->tempPath = storage_path('app/temp/images');
        
        // Ensure temp directory exists
        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
    }

    /**
     * Process a ProductImage: create variants and upload to R2
     */
    public function processImage(ProductImage $productImage): bool
    {
        try {
            $productImage->markAsProcessing();

            // Download original image to temp location
            $originalDisk = $productImage->storage_disk ?: 'public';
            $originalPath = $this->downloadToTemp($originalDisk, $productImage->image_path);
            
            if (!$originalPath) {
                throw new \Exception('Failed to download original image');
            }

            // Load and analyze original image
            $image = $this->manager->read($originalPath);
            $dimensions = ['width' => $image->width(), 'height' => $image->height()];
            $mimeType = $image->origin()->mediaType();
            $fileSize = filesize($originalPath);

            // Generate and upload variants
            $basePath = $this->generateBasePath($productImage);
            $variants = $this->createVariants($originalPath, $basePath);

            // Upload original to R2
            $originalR2Path = $basePath . '/' . basename($productImage->image_path);
            $this->uploadToR2($originalPath, $originalR2Path);

            // Update database with processing results
            $productImage->update([
                'processing_status' => ProductImage::PROCESSING_COMPLETED,
                'storage_disk' => 'images',
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'dimensions' => $dimensions,
                'image_path' => $originalR2Path,
                'metadata' => array_merge($productImage->metadata ?? [], [
                    'variants_created' => array_keys($variants),
                    'processed_at' => now()->toISOString(),
                    'original_dimensions' => $dimensions,
                ])
            ]);

            // Clean up temp files
            $this->cleanupTempFiles($originalPath, $variants);

            Log::info("Successfully processed image", [
                'image_id' => $productImage->id,
                'variants_created' => count($variants),
                'storage_disk' => 'images'
            ]);

            return true;

        } catch (\Exception $e) {
            $productImage->markAsFailed($e->getMessage());
            
            Log::error("Image processing failed", [
                'image_id' => $productImage->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Create multiple size variants of an image
     */
    private function createVariants(string $originalPath, string $basePath): array
    {
        $variants = [];
        
        foreach (ProductImage::SIZES as $sizeName => $config) {
            try {
                $image = $this->manager->read($originalPath);
                
                // Resize image maintaining aspect ratio
                $image = $image->scaleDown(
                    width: $config['width'],
                    height: $config['height']
                );

                // Apply quality settings
                $tempVariantPath = $this->tempPath . '/' . uniqid() . '_' . $sizeName . '.jpg';
                $image->toJpeg(quality: $config['quality'])->save($tempVariantPath);

                // Upload to R2
                $pathInfo = pathinfo(basename($originalPath));
                $variantR2Path = "{$basePath}/{$pathInfo['filename']}_{$sizeName}.jpg";
                $this->uploadToR2($tempVariantPath, $variantR2Path);

                $variants[$sizeName] = [
                    'temp_path' => $tempVariantPath,
                    'r2_path' => $variantR2Path,
                    'dimensions' => ['width' => $image->width(), 'height' => $image->height()]
                ];

            } catch (\Exception $e) {
                Log::warning("Failed to create variant", [
                    'size' => $sizeName,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $variants;
    }

    /**
     * Download image from storage to temp location
     */
    private function downloadToTemp(string $disk, string $path): ?string
    {
        try {
            if (!Storage::disk($disk)->exists($path)) {
                return null;
            }

            $tempPath = $this->tempPath . '/' . uniqid() . '_' . basename($path);
            $content = Storage::disk($disk)->get($path);
            file_put_contents($tempPath, $content);

            return $tempPath;
        } catch (\Exception $e) {
            Log::error("Failed to download image to temp", [
                'disk' => $disk,
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Upload file to R2 storage
     */
    private function uploadToR2(string $localPath, string $r2Path): bool
    {
        try {
            $content = file_get_contents($localPath);
            return Storage::disk('images')->put($r2Path, $content);
        } catch (\Exception $e) {
            Log::error("Failed to upload to R2", [
                'local_path' => $localPath,
                'r2_path' => $r2Path,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Generate organized base path for image storage
     */
    private function generateBasePath(ProductImage $productImage): string
    {
        $year = now()->year;
        $month = now()->format('m');
        
        if ($productImage->product_id) {
            return "products/{$year}/{$month}/product-{$productImage->product_id}";
        }
        
        if ($productImage->variant_id) {
            return "products/{$year}/{$month}/variant-{$productImage->variant_id}";
        }
        
        return "products/{$year}/{$month}/unassigned";
    }

    /**
     * Clean up temporary files
     */
    private function cleanupTempFiles(string $originalPath, array $variants): void
    {
        // Remove original temp file
        if (file_exists($originalPath)) {
            unlink($originalPath);
        }

        // Remove variant temp files
        foreach ($variants as $variant) {
            if (isset($variant['temp_path']) && file_exists($variant['temp_path'])) {
                unlink($variant['temp_path']);
            }
        }
    }

    /**
     * Batch process multiple images
     */
    public function processBatch(array $imageIds): array
    {
        $results = [
            'processed' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($imageIds as $imageId) {
            $image = ProductImage::find($imageId);
            if (!$image) {
                $results['errors'][] = "Image {$imageId} not found";
                continue;
            }

            if ($this->processImage($image)) {
                $results['processed']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Reprocess failed images
     */
    public function reprocessFailed(): array
    {
        $failedImages = ProductImage::where('processing_status', ProductImage::PROCESSING_FAILED)->get();
        
        return $this->processBatch($failedImages->pluck('id')->toArray());
    }

    /**
     * Get processing statistics
     */
    public function getProcessingStats(): array
    {
        return [
            'total' => ProductImage::count(),
            'pending' => ProductImage::where('processing_status', ProductImage::PROCESSING_PENDING)->count(),
            'processing' => ProductImage::where('processing_status', ProductImage::PROCESSING_IN_PROGRESS)->count(),
            'completed' => ProductImage::where('processing_status', ProductImage::PROCESSING_COMPLETED)->count(),
            'failed' => ProductImage::where('processing_status', ProductImage::PROCESSING_FAILED)->count(),
        ];
    }
}