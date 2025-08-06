<?php

namespace App\Jobs;

use App\Exceptions\ImageProcessingException;
use App\Exceptions\MediaLibraryException;
use App\Models\ProductVariant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessVariantImagesWithMediaLibrary implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

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
        Log::info("Processing images with Media Library for variant: {$this->variant->sku}", [
            'variant_id' => $this->variant->id,
            'image_count' => count($this->imageUrls)
        ]);

        $processedCount = 0;

        foreach ($this->imageUrls as $index => $imageUrl) {
            $success = $this->downloadAndStoreImageWithMediaLibrary($imageUrl, $index);
            if ($success) {
                $processedCount++;
            }
        }

        if ($processedCount > 0) {
            Log::info("Successfully processed images with Media Library for variant: {$this->variant->sku}", [
                'variant_id' => $this->variant->id,
                'processed_count' => $processedCount,
                'total_media_items' => $this->variant->getMedia('images')->count()
            ]);
        }
    }

    /**
     * Download and store an image using Media Library
     */
    private function downloadAndStoreImageWithMediaLibrary(string $imageUrl, int $index): bool
    {
        // Validate URL format
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            throw ImageProcessingException::downloadFailed($imageUrl, 'Invalid URL format');
        }

        // Download image with timeout and browser user agent
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ])
            ->get($imageUrl);
        
        if (!$response->successful()) {
            throw ImageProcessingException::downloadFailed($imageUrl, "HTTP {$response->status()} error");
        }

        // Validate content type
        $contentType = $response->header('Content-Type');
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!in_array($contentType, $allowedTypes)) {
            throw ImageProcessingException::invalidImageFormat($imageUrl, $contentType);
        }

        // Generate filename
        $extension = match($contentType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg'
        };

        // Create temporary file
        $tempPath = storage_path('app/temp/') . Str::uuid() . '.' . $extension;
        
        // Ensure temp directory exists
        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        
        // Save to temp file
        file_put_contents($tempPath, $response->body());

        // Add to Media Library with automatic thumbnail generation
        $mediaItem = $this->variant
            ->addMedia($tempPath)
            ->usingName(basename(parse_url($imageUrl, PHP_URL_PATH)) ?: "imported_image_{$index}.{$extension}")
            ->usingFileName("variant_{$this->variant->id}_image_{$index}_" . time() . ".{$extension}")
            ->withCustomProperties([
                'source' => 'import',
                'imported_from' => $imageUrl,
                'imported_at' => now()->toISOString(),
                'import_index' => $index,
                'variant_sku' => $this->variant->sku,
            ])
            ->toMediaCollection('images') ?: throw MediaLibraryException::migrationFailed($tempPath, 'Failed to add media to collection');

        // Clean up temp file
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        Log::info("Successfully stored image with Media Library", [
            'variant_id' => $this->variant->id,
            'media_id' => $mediaItem->id,
            'original_url' => $imageUrl,
            'conversions' => ['thumb', 'medium', 'large', 'webp']
        ]);

        return true;
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("ProcessVariantImagesWithMediaLibrary job failed for variant {$this->variant->id}", [
            'variant_sku' => $this->variant->sku,
            'image_urls' => $this->imageUrls,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}