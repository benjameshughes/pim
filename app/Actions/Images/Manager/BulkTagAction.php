<?php

namespace App\Actions\Images\Manager;

use App\Actions\Images\BulkTagImagesAction;
use App\Models\Image;
use Illuminate\Support\Collection;

/**
 * 🏷️ BULK TAG ACTION - FACADE INTEGRATION
 *
 * Facade-friendly wrapper for BulkTagImagesAction
 * Maintains full backwards compatibility while adding fluent API
 * 
 * Usage:
 * Images::bulk()->tag([1,2,3], ['sale', 'featured'])->add()
 * Images::bulk()->tag($imageIds)->replace(['new-tag'])->confirm()
 */
class BulkTagAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected BulkTagImagesAction $legacyAction;
    protected array $imageIds = [];
    protected array $tags = [];
    protected string $operation = 'add';
    protected bool $confirmed = false;
    protected array $tagStats = [];

    public function __construct()
    {
        $this->legacyAction = app()->make(BulkTagImagesAction::class);
    }

    /**
     * 🏷️ Execute bulk image tagging
     *
     * @param mixed ...$parameters - [imageIds, tags, operation]
     */
    public function execute(...$parameters): mixed
    {
        [$imageIds, $tags, $operation] = $parameters + [[], [], $this->operation];

        if (!$this->canExecute($imageIds, $tags, $operation)) {
            return $this->handleReturn([]);
        }

        // Store for fluent API
        $this->imageIds = $this->normalizeImageIds($imageIds);
        $this->tags = $this->processTags($tags);
        $this->operation = $operation;

        // Collect stats before tagging
        $this->collectTagStats($this->imageIds, $this->tags, $this->operation);

        try {
            // Use the existing action for actual bulk tagging
            $result = $this->legacyAction->execute($this->imageIds, $this->tags, $this->operation);

            $this->logAction('bulk_tag_images', [
                'success' => $result['success'] ?? false,
                'requested_count' => count($this->imageIds),
                'updated_count' => $result['data']['updated_count'] ?? 0,
                'operation' => $this->operation,
                'tags' => $this->tags,
            ]);

            return $this->handleReturn($result['data'] ?? []);

        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            
            $this->logAction('bulk_tag_failed', [
                'requested_count' => count($this->imageIds),
                'operation' => $this->operation,
                'tags' => $this->tags,
                'error' => $e->getMessage(),
            ]);

            return $this->handleReturn([]);
        }
    }

    /**
     * ✅ Validate bulk tagging parameters
     */
    public function canExecute(...$parameters): bool
    {
        [$imageIds, $tags, $operation] = $parameters + [[], [], 'add'];

        if (empty($imageIds)) {
            $this->errors[] = "Image IDs array is required and cannot be empty";
            return false;
        }

        $processedTags = $this->processTags($tags);
        if (empty($processedTags)) {
            $this->errors[] = "At least one tag is required";
            return false;
        }

        if (!in_array($operation, ['add', 'replace', 'remove'])) {
            $this->errors[] = "Operation must be 'add', 'replace', or 'remove'";
            return false;
        }

        if (!$this->confirmed) {
            $this->errors[] = "Bulk tagging must be confirmed before execution";
            return false;
        }

        return true;
    }

    /**
     * 🎯 FLUENT API METHODS (when in fluent mode)
     */

    /**
     * ➕ Add tags to images (merge with existing)
     */
    public function add(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("add() requires fluent mode");
        }
        
        $this->operation = 'add';
        $this->confirmed = true; // Auto-confirm for add operation
        return $this;
    }

    /**
     * 🔄 Replace all tags on images
     */
    public function replace(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("replace() requires fluent mode");
        }
        
        $this->operation = 'replace';
        return $this;
    }

    /**
     * ➖ Remove tags from images
     */
    public function remove(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("remove() requires fluent mode");
        }
        
        $this->operation = 'remove';
        return $this;
    }

    /**
     * 🏷️ Set tags for operation
     */
    public function withTags(array $tags): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("withTags() requires fluent mode");
        }
        
        $this->tags = $this->processTags($tags);
        return $this;
    }

    /**
     * ✅ Confirm bulk tagging intent
     */
    public function confirm(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("confirm() requires fluent mode");
        }
        
        $this->confirmed = true;
        return $this;
    }

    /**
     * 🚀 Execute confirmed bulk tagging
     */
    public function now(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("now() requires fluent mode");
        }
        
        if (empty($this->imageIds) || empty($this->tags)) {
            throw new \BadMethodCallException("Images and tags must be set for bulk tagging");
        }
        
        return $this->execute($this->imageIds, $this->tags, $this->operation);
    }

    /**
     * 🔢 Preview bulk tagging (what will change)
     */
    public function preview(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("preview() requires fluent mode");
        }
        
        if (empty($this->imageIds) || empty($this->tags)) {
            throw new \BadMethodCallException("Images and tags must be set for preview");
        }
        
        $this->collectTagStats($this->imageIds, $this->tags, $this->operation);
        
        return [
            'total_requested' => count($this->imageIds),
            'valid_images' => $this->tagStats['valid_images'] ?? 0,
            'invalid_ids' => $this->tagStats['invalid_ids'] ?? [],
            'will_change' => $this->tagStats['will_change'] ?? 0,
            'no_change' => $this->tagStats['no_change'] ?? 0,
            'operation' => $this->operation,
            'tags' => $this->tags,
            'tag_preview' => $this->tagStats['tag_preview'] ?? [],
            'warnings' => $this->getTagWarnings(),
        ];
    }

    /**
     * 📊 Get bulk tagging statistics
     */
    public function getStats(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getStats() requires fluent mode");
        }
        
        return $this->tagStats;
    }

    /**
     * 🔢 Get count of images that will be affected
     */
    public function count(): int
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("count() requires fluent mode");
        }
        
        if (empty($this->imageIds)) {
            return 0;
        }
        
        return Image::whereIn('id', $this->imageIds)
            ->where(function ($query) {
                $query->where('folder', '!=', 'variants')
                    ->orWhereNull('folder');
            })
            ->count();
    }

    /**
     * 📋 Get list of images to be tagged
     */
    public function getImages(): Collection
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getImages() requires fluent mode");
        }
        
        if (empty($this->imageIds)) {
            return collect();
        }
        
        return Image::whereIn('id', $this->imageIds)
            ->where(function ($query) {
                $query->where('folder', '!=', 'variants')
                    ->orWhereNull('folder');
            })
            ->get();
    }

    /**
     * 🏷️ Get unique tags that will be affected
     */
    public function getAffectedTags(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getAffectedTags() requires fluent mode");
        }
        
        if (empty($this->imageIds)) {
            return [];
        }
        
        $images = $this->getImages();
        $allTags = [];
        
        foreach ($images as $image) {
            $allTags = array_merge($allTags, $image->tags ?: []);
        }
        
        return array_unique($allTags);
    }

    /**
     * 🔧 Helper Methods
     */

    /**
     * 🔢 Normalize image IDs from various inputs
     */
    protected function normalizeImageIds($imageIds): array
    {
        if ($imageIds instanceof Collection) {
            return $imageIds->pluck('id')->toArray();
        }
        
        if (is_array($imageIds)) {
            return array_map('intval', array_filter($imageIds, 'is_numeric'));
        }
        
        if (is_numeric($imageIds)) {
            return [(int)$imageIds];
        }
        
        return [];
    }

    /**
     * 🏷️ Process tags into proper array format
     */
    protected function processTags($tags): array
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
     * 📊 Collect bulk tagging statistics
     */
    protected function collectTagStats(array $imageIds, array $tags, string $operation): void
    {
        $images = Image::whereIn('id', $imageIds)
            ->where(function ($query) {
                $query->where('folder', '!=', 'variants')
                    ->orWhereNull('folder');
            })
            ->get();
            
        $validIds = $images->pluck('id')->toArray();
        $invalidIds = array_diff($imageIds, $validIds);
        
        $willChange = 0;
        $noChange = 0;
        $tagPreview = [];
        
        foreach ($images as $image) {
            $originalTags = $image->tags ?: [];
            $newTags = $this->performTagOperation($originalTags, $tags, $operation);
            
            if ($this->tagsAreEqual($originalTags, $newTags)) {
                $noChange++;
            } else {
                $willChange++;
                $tagPreview[] = [
                    'image_id' => $image->id,
                    'title' => $image->display_title,
                    'from' => $originalTags,
                    'to' => $newTags,
                ];
            }
        }
        
        $this->tagStats = [
            'requested_count' => count($imageIds),
            'valid_images' => count($validIds),
            'invalid_ids' => $invalidIds,
            'will_change' => $willChange,
            'no_change' => $noChange,
            'tag_preview' => $tagPreview,
        ];
    }

    /**
     * 🏷️ Perform tag operation (copied from legacy action)
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
     * 🏷️ Check if tags are equal (copied from legacy action)
     */
    protected function tagsAreEqual(array $tags1, array $tags2): bool
    {
        sort($tags1);
        sort($tags2);
        return $tags1 === $tags2;
    }

    /**
     * ⚠️ Get bulk tagging warnings
     */
    protected function getTagWarnings(): array
    {
        $warnings = [];
        
        if (!empty($this->tagStats['invalid_ids'])) {
            $count = count($this->tagStats['invalid_ids']);
            $warnings[] = "{$count} image ID(s) not found: " . implode(', ', $this->tagStats['invalid_ids']);
        }
        
        if ($this->tagStats['no_change'] > 0) {
            $warnings[] = "{$this->tagStats['no_change']} image(s) will not change";
        }
        
        if ($this->tagStats['will_change'] === 0) {
            $warnings[] = "No images will be affected by this operation";
        }
        
        if ($this->operation === 'remove') {
            $warnings[] = "Remove operation: tags will be permanently deleted from images";
        }
        
        if ($this->operation === 'replace') {
            $warnings[] = "Replace operation: all existing tags will be replaced";
        }
        
        return $warnings;
    }
}