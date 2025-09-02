<?php

namespace App\Actions\Images;

use App\Actions\Base\BaseAction;
use App\Facades\Activity;
use App\Models\Image;
use Illuminate\Support\Facades\DB;

/**
 * 📁 BULK MOVE IMAGES ACTION
 *
 * Handles bulk moving of multiple images to a different folder
 * with proper validation, transaction safety, and activity logging
 */
class BulkMoveImagesAction extends BaseAction
{
    /**
     * Execute bulk image folder move with transaction safety
     *
     * @param array ...$params First parameter is array of image IDs, second is target folder name
     * @throws \Exception When move operation fails
     */
    protected function performAction(...$params): array
    {
        $imageIds = $params[0] ?? [];
        $targetFolder = $params[1] ?? null;

        if (empty($imageIds) || !is_array($imageIds)) {
            throw new \InvalidArgumentException('Image IDs array is required');
        }

        if ($targetFolder === null || trim($targetFolder) === '') {
            throw new \InvalidArgumentException('Target folder is required');
        }

        $targetFolder = trim($targetFolder);

        // Validate folder name (basic validation)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $targetFolder)) {
            throw new \InvalidArgumentException('Folder name can only contain letters, numbers, hyphens, and underscores');
        }

        // Fetch images that exist and are not variants
        $images = Image::whereIn('id', $imageIds)
            ->where(function ($query) {
                $query->where('folder', '!=', 'variants')
                    ->orWhereNull('folder');
            })
            ->get();
        
        if ($images->isEmpty()) {
            throw new \InvalidArgumentException('No valid images found for the provided IDs');
        }

        $movedCount = 0;
        $movedImages = [];
        $folderChanges = [];

        DB::beginTransaction();

        try {
            foreach ($images as $image) {
                $originalFolder = $image->folder;
                
                // Skip if already in target folder
                if ($image->folder === $targetFolder) {
                    continue;
                }

                $image->update(['folder' => $targetFolder]);
                $movedCount++;
                
                $movedImages[] = [
                    'id' => $image->id,
                    'title' => $image->display_title,
                    'original_folder' => $originalFolder,
                    'new_folder' => $targetFolder,
                ];

                $folderChanges[] = [
                    'from' => $originalFolder ?: 'uncategorized',
                    'to' => $targetFolder,
                ];
            }

            // Log bulk move activity
            if ($movedCount > 0) {
                Activity::log()
                    ->by(auth()->id())
                    ->bulkMoved($movedImages)
                    ->with([
                        'moved_count' => $movedCount,
                        'target_folder' => $targetFolder,
                        'total_requested' => count($imageIds),
                        'folder_changes' => $folderChanges,
                    ])
                    ->description("Bulk moved {$movedCount} images to '{$targetFolder}' folder");
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Failed to move images: ' . $e->getMessage());
        }

        return $this->success('Bulk image move completed successfully', [
            'moved_count' => $movedCount,
            'target_folder' => $targetFolder,
            'total_requested' => count($imageIds),
            'moved_images' => $movedImages,
            'skipped_count' => count($imageIds) - $movedCount,
        ]);
    }
}