<?php

namespace App\Actions\Images\Manager;

use App\Actions\Images\BulkMoveImagesAction;
use App\Models\Image;
use Illuminate\Support\Collection;

/**
 * 📁 BULK MOVE ACTION - FACADE INTEGRATION
 *
 * Facade-friendly wrapper for BulkMoveImagesAction
 * Maintains full backwards compatibility while adding fluent API
 * 
 * Usage:
 * Images::bulk()->move([1,2,3], 'archived')->confirm()
 * Images::bulk()->move($imageIds)->to('products')->preview()
 */
class BulkMoveAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected BulkMoveImagesAction $legacyAction;
    protected array $imageIds = [];
    protected ?string $targetFolder = null;
    protected bool $confirmed = false;
    protected array $moveStats = [];

    public function __construct()
    {
        $this->legacyAction = app()->make(BulkMoveImagesAction::class);
    }

    /**
     * 📁 Execute bulk image move
     *
     * @param mixed ...$parameters - [imageIds, targetFolder]
     */
    public function execute(...$parameters): mixed
    {
        [$imageIds, $targetFolder] = $parameters + [[], null];

        if (!$this->canExecute($imageIds, $targetFolder)) {
            return $this->handleReturn([]);
        }

        // Store for fluent API
        $this->imageIds = $this->normalizeImageIds($imageIds);
        $this->targetFolder = $targetFolder;

        // Collect stats before move
        $this->collectMoveStats($this->imageIds, $this->targetFolder);

        try {
            // Use the existing action for actual bulk move
            $result = $this->legacyAction->execute($this->imageIds, $this->targetFolder);

            $this->logAction('bulk_move_images', [
                'success' => $result['success'] ?? false,
                'requested_count' => count($this->imageIds),
                'moved_count' => $result['data']['moved_count'] ?? 0,
                'target_folder' => $this->targetFolder,
            ]);

            return $this->handleReturn($result['data'] ?? []);

        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            
            $this->logAction('bulk_move_failed', [
                'requested_count' => count($this->imageIds),
                'target_folder' => $this->targetFolder,
                'error' => $e->getMessage(),
            ]);

            return $this->handleReturn([]);
        }
    }

    /**
     * ✅ Validate bulk move parameters
     */
    public function canExecute(...$parameters): bool
    {
        [$imageIds, $targetFolder] = $parameters + [[], null];

        if (empty($imageIds)) {
            $this->errors[] = "Image IDs array is required and cannot be empty";
            return false;
        }

        if (empty($targetFolder)) {
            $this->errors[] = "Target folder is required";
            return false;
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $targetFolder)) {
            $this->errors[] = "Folder name can only contain letters, numbers, hyphens, and underscores";
            return false;
        }

        if (!$this->confirmed) {
            $this->errors[] = "Bulk move must be confirmed before execution";
            return false;
        }

        return true;
    }

    /**
     * 🎯 FLUENT API METHODS (when in fluent mode)
     */

    /**
     * 📁 Set target folder
     */
    public function to(string $folder): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("to() requires fluent mode");
        }
        
        $this->targetFolder = trim($folder);
        return $this;
    }

    /**
     * ✅ Confirm bulk move intent
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
     * 🚀 Execute confirmed bulk move
     */
    public function now(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("now() requires fluent mode");
        }
        
        if (empty($this->imageIds) || !$this->targetFolder) {
            throw new \BadMethodCallException("Images and target folder must be set for bulk move");
        }
        
        return $this->execute($this->imageIds, $this->targetFolder);
    }

    /**
     * 🔢 Preview bulk move (what will be moved)
     */
    public function preview(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("preview() requires fluent mode");
        }
        
        if (empty($this->imageIds) || !$this->targetFolder) {
            throw new \BadMethodCallException("Images and target folder must be set for preview");
        }
        
        $this->collectMoveStats($this->imageIds, $this->targetFolder);
        
        return [
            'total_requested' => count($this->imageIds),
            'valid_images' => $this->moveStats['valid_images'] ?? 0,
            'invalid_ids' => $this->moveStats['invalid_ids'] ?? [],
            'already_in_folder' => $this->moveStats['already_in_folder'] ?? 0,
            'will_move' => $this->moveStats['will_move'] ?? 0,
            'target_folder' => $this->targetFolder,
            'folder_changes' => $this->moveStats['folder_changes'] ?? [],
            'warnings' => $this->getMoveWarnings(),
        ];
    }

    /**
     * 📊 Get bulk move statistics
     */
    public function getStats(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getStats() requires fluent mode");
        }
        
        return $this->moveStats;
    }

    /**
     * 🔢 Get count of images that will be moved
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
     * 📋 Get list of images to be moved
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
     * 📁 Get list of unique source folders
     */
    public function getSourceFolders(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getSourceFolders() requires fluent mode");
        }
        
        if (empty($this->imageIds)) {
            return [];
        }
        
        return Image::whereIn('id', $this->imageIds)
            ->select('folder')
            ->distinct()
            ->pluck('folder')
            ->map(fn($folder) => $folder ?: 'uncategorized')
            ->toArray();
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
     * 📊 Collect bulk move statistics
     */
    protected function collectMoveStats(array $imageIds, string $targetFolder): void
    {
        $images = Image::whereIn('id', $imageIds)
            ->where(function ($query) {
                $query->where('folder', '!=', 'variants')
                    ->orWhereNull('folder');
            })
            ->get();
            
        $validIds = $images->pluck('id')->toArray();
        $invalidIds = array_diff($imageIds, $validIds);
        
        $alreadyInFolder = $images->where('folder', $targetFolder)->count();
        $willMove = $images->where('folder', '!=', $targetFolder)->count();
        
        $folderChanges = [];
        foreach ($images->where('folder', '!=', $targetFolder) as $image) {
            $from = $image->folder ?: 'uncategorized';
            if (!isset($folderChanges[$from])) {
                $folderChanges[$from] = 0;
            }
            $folderChanges[$from]++;
        }
        
        $this->moveStats = [
            'requested_count' => count($imageIds),
            'valid_images' => count($validIds),
            'invalid_ids' => $invalidIds,
            'already_in_folder' => $alreadyInFolder,
            'will_move' => $willMove,
            'folder_changes' => $folderChanges,
        ];
    }

    /**
     * ⚠️ Get bulk move warnings
     */
    protected function getMoveWarnings(): array
    {
        $warnings = [];
        
        if (!empty($this->moveStats['invalid_ids'])) {
            $count = count($this->moveStats['invalid_ids']);
            $warnings[] = "{$count} image ID(s) not found: " . implode(', ', $this->moveStats['invalid_ids']);
        }
        
        if ($this->moveStats['already_in_folder'] > 0) {
            $warnings[] = "{$this->moveStats['already_in_folder']} image(s) already in target folder";
        }
        
        if ($this->moveStats['will_move'] === 0) {
            $warnings[] = "No images will be moved - all are already in target folder or invalid";
        }
        
        return $warnings;
    }
}