<?php

namespace App\Actions\Images;

use App\Actions\Base\BaseAction;
use App\Facades\Activity;
use App\Models\Image;
use Illuminate\Support\Facades\DB;

/**
 * 🏷️ BULK TAG IMAGES ACTION
 *
 * Handles bulk tagging of multiple images with proper validation,
 * transaction safety, and activity logging
 */
class BulkTagImagesAction extends BaseAction
{
    /**
     * Execute bulk image tagging with transaction safety
     *
     * @param array ...$params First parameter is array of image IDs, second is tags, third is operation
     * @throws \Exception When tagging operation fails
     */
    protected function performAction(...$params): array
    {
        $imageIds = $params[0] ?? [];
        $tags = $params[1] ?? [];
        $operation = $params[2] ?? 'add'; // 'add', 'replace', 'remove'

        if (empty($imageIds) || !is_array($imageIds)) {
            throw new \InvalidArgumentException('Image IDs array is required');
        }

        // Process tags to ensure array format
        $processedTags = $this->processTags($tags);

        if (empty($processedTags)) {
            throw new \InvalidArgumentException('At least one tag is required');
        }

        if (!in_array($operation, ['add', 'replace', 'remove'])) {
            throw new \InvalidArgumentException('Operation must be add, replace, or remove');
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

        $updatedCount = 0;
        $updatedImages = [];
        $tagChanges = [];

        DB::beginTransaction();

        try {
            foreach ($images as $image) {
                $originalTags = $image->tags ?: [];
                $newTags = $this->performTagOperation($originalTags, $processedTags, $operation);
                
                // Skip if no changes
                if ($this->tagsAreEqual($originalTags, $newTags)) {
                    continue;
                }

                $image->update(['tags' => $newTags]);
                $updatedCount++;
                
                $updatedImages[] = [
                    'id' => $image->id,
                    'title' => $image->display_title,
                    'original_tags' => $originalTags,
                    'new_tags' => $newTags,
                ];

                $tagChanges[] = [
                    'operation' => $operation,
                    'tags_modified' => $processedTags,
                    'result_tags' => $newTags,
                ];
            }

            // Log bulk tagging activity
            if ($updatedCount > 0) {
                Activity::log()
                    ->by(auth()->id())
                    ->bulkTagged($updatedImages)
                    ->with([
                        'updated_count' => $updatedCount,
                        'operation' => $operation,
                        'tags_processed' => $processedTags,
                        'total_requested' => count($imageIds),
                        'tag_changes' => $tagChanges,
                    ])
                    ->description("Bulk {$operation} tags on {$updatedCount} images: " . implode(', ', $processedTags));
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Failed to update image tags: ' . $e->getMessage());
        }

        return $this->success('Bulk image tagging completed successfully', [
            'updated_count' => $updatedCount,
            'operation' => $operation,
            'tags_processed' => $processedTags,
            'total_requested' => count($imageIds),
            'updated_images' => $updatedImages,
            'skipped_count' => count($imageIds) - $updatedCount,
        ]);
    }

    /**
     * Process tags into proper array format
     */
    protected function processTags(mixed $tags): array
    {
        if (is_string($tags)) {
            return array_filter(array_map('trim', explode(',', $tags)));
        }

        if (is_array($tags)) {
            return array_filter(array_map('trim', $tags));
        }

        return [];
    }

    /**
     * Perform the specified tag operation
     */
    protected function performTagOperation(array $originalTags, array $newTags, string $operation): array
    {
        return match ($operation) {
            'add' => array_values(array_unique(array_merge($originalTags, $newTags))),
            'replace' => $newTags,
            'remove' => array_values(array_diff($originalTags, $newTags)),
            default => $originalTags,
        };
    }

    /**
     * Check if two tag arrays are equal (ignoring order)
     */
    protected function tagsAreEqual(array $tags1, array $tags2): bool
    {
        sort($tags1);
        sort($tags2);
        return $tags1 === $tags2;
    }
}