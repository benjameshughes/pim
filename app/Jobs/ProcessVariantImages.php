<?php

namespace App\Jobs;

use App\Models\ProductVariant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessVariantImages implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ProductVariant $variant,
        public array $imageUrls = [],
        public array $importedData = []
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Processing images for variant: {$this->variant->sku}", [
            'variant_id' => $this->variant->id,
            'image_count' => count($this->imageUrls),
        ]);

        $processedImages = [];
        $existingImages = $this->variant->images ?? [];

        foreach ($this->imageUrls as $index => $imageUrl) {
            try {
                $processedImage = $this->downloadAndStoreImage($imageUrl, $index);
                if ($processedImage) {
                    $processedImages[] = $processedImage;
                }
            } catch (Throwable $e) {
                Log::error("Failed to process image for variant {$this->variant->id}", [
                    'image_url' => $imageUrl,
                    'error' => $e->getMessage(),
                ]);

                // Don't fail the entire job for individual image failures
                continue;
            }
        }

        // Merge with existing images and update variant
        if (! empty($processedImages)) {
            $allImages = array_merge($existingImages, $processedImages);
            $this->variant->update(['images' => $allImages]);

            Log::info("Successfully processed images for variant: {$this->variant->sku}", [
                'variant_id' => $this->variant->id,
                'processed_count' => count($processedImages),
                'total_images' => count($allImages),
            ]);
        }
    }

    /**
     * Download and store an image from URL
     */
    private function downloadAndStoreImage(string $imageUrl, int $index): ?array
    {
        // Validate URL format
        if (! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            Log::warning('Invalid image URL format', ['url' => $imageUrl]);

            return null;
        }

        // Download image with timeout
        $response = Http::timeout(30)->get($imageUrl);

        if (! $response->successful()) {
            Log::warning('Failed to download image', [
                'url' => $imageUrl,
                'status' => $response->status(),
            ]);

            return null;
        }

        // Validate content type
        $contentType = $response->header('Content-Type');
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

        if (! in_array($contentType, $allowedTypes)) {
            Log::warning('Unsupported image type', [
                'url' => $imageUrl,
                'content_type' => $contentType,
            ]);

            return null;
        }

        // Generate filename
        $extension = match ($contentType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg'
        };

        $filename = "variants/{$this->variant->id}/imported_".time()."_{$index}.{$extension}";

        // Store image
        $stored = Storage::disk('public')->put($filename, $response->body());

        if (! $stored) {
            Log::error('Failed to store image', ['filename' => $filename]);

            return null;
        }

        // Get file size and create image record
        $size = Storage::disk('public')->size($filename);

        return [
            'path' => $filename,
            'original_name' => basename(parse_url($imageUrl, PHP_URL_PATH)) ?: "imported_image_{$index}.{$extension}",
            'size' => $size,
            'mime_type' => $contentType,
            'url' => Storage::disk('public')->url($filename),
            'source' => 'import',
            'imported_from' => $imageUrl,
            'imported_at' => now()->toISOString(),
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("ProcessVariantImages job failed for variant {$this->variant->id}", [
            'variant_sku' => $this->variant->sku,
            'image_urls' => $this->imageUrls,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
