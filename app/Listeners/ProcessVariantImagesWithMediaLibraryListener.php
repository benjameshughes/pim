<?php

namespace App\Listeners;

use App\Events\ProductVariantImported;
use App\Jobs\ProcessVariantImagesWithMediaLibrary;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessVariantImagesWithMediaLibraryListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public $queue = 'image-processing';

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ProductVariantImported $event): void
    {
        try {
            // Refresh the variant model to ensure it exists in the database
            $variant = $event->variant->fresh();
            
            if (!$variant) {
                Log::error("ProductVariant not found in database", [
                    'variant_id' => $event->variant->id
                ]);
                return;
            }

            Log::info("Processing ProductVariantImported event with Media Library", [
                'variant_id' => $variant->id,
                'variant_sku' => $variant->sku
            ]);

            // Extract image URLs from imported data
            $imageUrls = $this->extractImageUrls($event->importedData);
            
            if (empty($imageUrls)) {
                Log::info("No image URLs found for variant: {$variant->sku}");
                return;
            }

            Log::info("Found image URLs for Media Library processing", [
                'variant_id' => $variant->id,
                'urls' => $imageUrls
            ]);

            // Dispatch the enhanced image processing job with Media Library
            ProcessVariantImagesWithMediaLibrary::dispatch(
                $variant,
                $imageUrls,
                $event->importedData
            )->delay(now()->addSeconds(5)); // Small delay to ensure variant is fully saved

            Log::info("Dispatched Media Library image processing job for variant: {$variant->sku}", [
                'variant_id' => $variant->id,
                'image_count' => count($imageUrls)
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to handle ProductVariantImported event with Media Library", [
                'variant_id' => $event->variant->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Extract image URLs from imported data
     */
    private function extractImageUrls(array $importedData): array
    {
        $imageUrls = [];
        
        // Look for image URL fields in imported data
        $imageFields = [
            'image_url', 'image_urls', 'image_1', 'image_2', 'image_3', 'image_4', 'image_5',
            'main_image', 'product_image', 'photo_url', 'picture_url', 'images'
        ];
        
        foreach ($imageFields as $field) {
            if (!empty($importedData[$field])) {
                $value = $importedData[$field];
                
                // Handle comma-separated URLs
                if (is_string($value) && str_contains($value, ',')) {
                    $urls = array_map('trim', explode(',', $value));
                    foreach ($urls as $url) {
                        if (filter_var($url, FILTER_VALIDATE_URL)) {
                            $imageUrls[] = $url;
                        }
                    }
                } elseif (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                    $imageUrls[] = $value;
                } elseif (is_array($value)) {
                    foreach ($value as $url) {
                        if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                            $imageUrls[] = $url;
                        }
                    }
                }
            }
        }
        
        return array_unique($imageUrls);
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(ProductVariantImported $event): bool
    {
        // Only queue if there are potential image URLs to process
        $imageUrls = $this->extractImageUrls($event->importedData);
        return !empty($imageUrls);
    }
}