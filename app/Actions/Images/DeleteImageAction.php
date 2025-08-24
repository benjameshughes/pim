<?php

namespace App\Actions\Images;

use App\Models\Image;
use App\Services\ImageUploadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🗑️ DELETE IMAGE ACTION
 *
 * Handles complete image deletion with proper cleanup, transaction safety,
 * and comprehensive error handling following Laravel best practices
 */
class DeleteImageAction
{
    public function __construct(
        protected ImageUploadService $imageUploadService
    ) {}

    /**
     * Execute image deletion with transaction safety
     *
     * @throws \Exception When deletion fails
     */
    public function execute(Image $image): void
    {
        DB::beginTransaction();

        try {
            // Log the deletion attempt
            Log::info('Attempting to delete image', [
                'image_id' => $image->id,
                'filename' => $image->filename,
                'user_id' => auth()->id(),
            ]);

            // Detach from all relationships first
            $this->detachFromAllRelationships($image);

            // Use the existing ImageUploadService for file and database cleanup
            $this->imageUploadService->deleteImage($image);

            DB::commit();

            // Log successful deletion
            Log::info('Image deleted successfully', [
                'image_id' => $image->id,
                'filename' => $image->filename,
                'user_id' => auth()->id(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            // Log the error
            Log::error('Failed to delete image', [
                'image_id' => $image->id,
                'filename' => $image->filename,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            throw new \Exception(
                "Failed to delete image '{$image->display_title}': {$e->getMessage()}"
            );
        }
    }

    /**
     * Detach image from all relationships before deletion
     */
    protected function detachFromAllRelationships(Image $image): void
    {
        // Detach from products
        if ($image->products()->exists()) {
            $productCount = $image->products()->count();
            $image->products()->detach();
            Log::debug("Detached image from {$productCount} products", [
                'image_id' => $image->id
            ]);
        }

        // Detach from variants
        if ($image->variants()->exists()) {
            $variantCount = $image->variants()->count();
            $image->variants()->detach();
            Log::debug("Detached image from {$variantCount} variants", [
                'image_id' => $image->id
            ]);
        }
    }
}