<?php

namespace App\Listeners;

use App\Events\ProductImported;
use App\Jobs\ProcessProductImages;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessProductImagesListener implements ShouldQueue
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
    public function handle(ProductImported $event): void
    {
        try {
            // Refresh the product model to ensure it exists in the database
            $product = $event->product->fresh();

            if (! $product) {
                Log::error('Product not found in database', [
                    'product_id' => $event->product->id,
                ]);

                return;
            }

            Log::info('Processing ProductImported event', [
                'product_id' => $product->id,
                'product_name' => $product->name,
            ]);

            // Extract image URLs from imported data
            $imageUrls = $this->extractImageUrls($event->importedData);

            if (empty($imageUrls)) {
                Log::info("No image URLs found for product: {$product->name}");

                return;
            }

            Log::info('Found image URLs for processing', [
                'product_id' => $product->id,
                'urls' => $imageUrls,
            ]);

            // Dispatch the image processing job with fresh product instance
            ProcessProductImages::dispatch(
                $product,
                $imageUrls,
                $event->importedData
            )->delay(now()->addSeconds(5)); // Small delay to ensure product is fully saved

            Log::info("Dispatched image processing job for product: {$product->name}", [
                'product_id' => $product->id,
                'image_count' => count($imageUrls),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to handle ProductImported event', [
                'product_id' => $event->product->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
            'main_image', 'product_image', 'photo_url', 'picture_url', 'images',
        ];

        foreach ($imageFields as $field) {
            if (! empty($importedData[$field])) {
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
    public function shouldQueue(ProductImported $event): bool
    {
        // Only queue if there are potential image URLs to process
        $imageUrls = $this->extractImageUrls($event->importedData);

        return ! empty($imageUrls);
    }
}
