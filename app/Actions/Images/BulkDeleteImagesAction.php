<?php

namespace App\Actions\Images;

use App\Actions\Base\BaseAction;
use App\Facades\Activity;
use App\Models\Image;
use Illuminate\Support\Facades\DB;

/**
 * 🗑️ BULK DELETE IMAGES ACTION
 *
 * Handles bulk deletion of multiple images with proper cleanup,
 * transaction safety, and comprehensive error handling
 */
class BulkDeleteImagesAction extends BaseAction
{
    public function __construct(
        protected DeleteImageAction $deleteImageAction
    ) {
        parent::__construct();
    }

    /**
     * Execute bulk image deletion with transaction safety
     *
     * @param array $imageIds Array of image IDs to delete
     * @throws \Exception When deletion fails
     */
    protected function performAction(...$params): array
    {
        $imageIds = $params[0] ?? [];

        if (empty($imageIds) || !is_array($imageIds)) {
            throw new \InvalidArgumentException('Image IDs array is required');
        }

        // Fetch images that exist
        $images = Image::whereIn('id', $imageIds)->get();
        
        if ($images->isEmpty()) {
            throw new \InvalidArgumentException('No valid images found for the provided IDs');
        }

        $deletedCount = 0;
        $errors = [];
        $deletedImages = [];

        DB::beginTransaction();

        try {
            foreach ($images as $image) {
                try {
                    $this->deleteImageAction->execute($image);
                    $deletedCount++;
                    $deletedImages[] = [
                        'id' => $image->id,
                        'title' => $image->display_title,
                        'filename' => $image->filename,
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'image_id' => $image->id,
                        'title' => $image->display_title,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // If there were any errors, rollback everything
            if (!empty($errors)) {
                DB::rollBack();
                throw new \Exception('Some images could not be deleted: ' . json_encode($errors));
            }

            // Log bulk deletion activity
            // TODO: Re-enable after fixing description() method issue
            // Activity::log()
            //     ->by(auth()->id())
            //     ->bulkDeleted($deletedImages)
            //     ->with([
            //         'deleted_count' => $deletedCount,
            //         'total_requested' => count($imageIds),
            //         'success_rate' => '100%',
            //     ])
            //     ->description("Bulk deleted {$deletedCount} images successfully");

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->success('Bulk image deletion completed successfully', [
            'deleted_count' => $deletedCount,
            'total_requested' => count($imageIds),
            'deleted_images' => $deletedImages,
            'errors' => $errors,
        ]);
    }
}