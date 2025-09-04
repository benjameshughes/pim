<?php

namespace App\Actions\Images\Manager;

use App\Actions\Images\UpdateImageAction;
use App\Models\Image;

/**
 * 🔄 UPDATE ACTION - FACADE INTEGRATION
 *
 * Facade-friendly wrapper for UpdateImageAction
 * Maintains full backwards compatibility while adding fluent API
 * 
 * Usage:
 * Images::update($image)->metadata(['title' => 'New Title'])->save()
 * Images::update($image)->title('New Title')->alt('New Alt')->save()
 */
class UpdateAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected UpdateImageAction $legacyAction;
    protected ?Image $image = null;
    protected array $updateData = [];
    protected array $validationRules = [
        'title' => 'nullable|string|max:255',
        'alt_text' => 'nullable|string|max:255',
        'description' => 'nullable|string|max:1000',
        'folder' => 'nullable|string|max:255',
        'tags' => 'nullable|array',
        'tags.*' => 'string|max:50',
    ];

    public function __construct()
    {
        $this->legacyAction = app()->make(UpdateImageAction::class);
    }

    /**
     * 🔄 Execute image update
     *
     * @param mixed ...$parameters - [image, data]
     */
    public function execute(...$parameters): mixed
    {
        [$image, $data] = $parameters + [null, []];

        if (!$this->canExecute($image, $data)) {
            return $this->handleReturn(null);
        }

        // Store for fluent API
        $this->image = $image;
        $this->updateData = array_merge($this->updateData, $data);

        try {
            // Use the existing action for actual update
            $updatedImage = $this->legacyAction->execute($image, $this->updateData);

            $this->logAction('update_image', [
                'success' => true,
                'image_id' => $image->id,
                'original_title' => $image->title,
                'updated_fields' => array_keys($this->updateData),
                'data_count' => count($this->updateData),
            ]);

            return $this->handleReturn($updatedImage);

        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            
            $this->logAction('update_image_failed', [
                'image_id' => $image->id,
                'error' => $e->getMessage(),
                'attempted_data' => $this->updateData,
            ]);

            return $this->handleReturn(null);
        }
    }

    /**
     * ✅ Validate update parameters
     */
    public function canExecute(...$parameters): bool
    {
        [$image, $data] = $parameters + [null, []];

        if (!($image instanceof Image)) {
            $this->errors[] = "First parameter must be an Image instance";
            return false;
        }

        if (!is_array($data)) {
            $this->errors[] = "Update data must be an array";
            return false;
        }

        return true;
    }

    /**
     * 🎯 FLUENT API METHODS (when in fluent mode)
     */

    /**
     * 📝 Update metadata (bulk update)
     */
    public function metadata(array $metadata): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("metadata() requires fluent mode");
        }
        
        $this->updateData = array_merge($this->updateData, $metadata);
        return $this;
    }

    /**
     * 🏷️ Update title
     */
    public function title(string $title): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("title() requires fluent mode");
        }
        
        $this->updateData['title'] = $title;
        return $this;
    }

    /**
     * 📝 Update alt text
     */
    public function alt(string $altText): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("alt() requires fluent mode");
        }
        
        $this->updateData['alt_text'] = $altText;
        return $this;
    }

    /**
     * 📄 Update description
     */
    public function description(string $description): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("description() requires fluent mode");
        }
        
        $this->updateData['description'] = $description;
        return $this;
    }

    /**
     * 📁 Move to folder
     */
    public function moveToFolder(string $folder): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("moveToFolder() requires fluent mode");
        }
        
        $this->updateData['folder'] = $folder;
        return $this;
    }

    /**
     * 🏷️ Set tags (replaces existing)
     */
    public function setTags(array $tags): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("setTags() requires fluent mode");
        }
        
        $this->updateData['tags'] = $tags;
        return $this;
    }

    /**
     * ➕ Add tags (merges with existing)
     */
    public function addTags(array $tags): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("addTags() requires fluent mode");
        }
        
        if (!$this->image) {
            throw new \BadMethodCallException("Image must be set before adding tags");
        }
        
        $existingTags = $this->image->tags ?? [];
        $newTags = array_unique(array_merge($existingTags, $tags));
        $this->updateData['tags'] = $newTags;
        
        return $this;
    }

    /**
     * ➖ Remove tags
     */
    public function removeTags(array $tagsToRemove): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("removeTags() requires fluent mode");
        }
        
        if (!$this->image) {
            throw new \BadMethodCallException("Image must be set before removing tags");
        }
        
        $existingTags = $this->image->tags ?? [];
        $newTags = array_values(array_diff($existingTags, $tagsToRemove));
        $this->updateData['tags'] = $newTags;
        
        return $this;
    }

    /**
     * 💾 Save all changes
     */
    public function save(): Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("save() requires fluent mode");
        }
        
        if (!$this->image) {
            throw new \BadMethodCallException("No image set for update");
        }
        
        if (empty($this->updateData)) {
            // No changes to save, return original image
            return $this->image;
        }
        
        $result = $this->execute($this->image, $this->updateData);
        return $result instanceof Image ? $result : $this->image;
    }

    /**
     * 🔄 Apply changes immediately (alias for save)
     */
    public function apply(): Image
    {
        return $this->save();
    }

    /**
     * 👁️ Preview changes without saving
     */
    public function preview(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("preview() requires fluent mode");
        }
        
        if (!$this->image) {
            throw new \BadMethodCallException("No image set for preview");
        }
        
        $changes = [];
        $originalData = $this->image->only(array_keys($this->updateData));
        
        foreach ($this->updateData as $key => $newValue) {
            $originalValue = $originalData[$key] ?? null;
            if ($originalValue !== $newValue) {
                $changes[$key] = [
                    'from' => $originalValue,
                    'to' => $newValue,
                ];
            }
        }
        
        return [
            'image_id' => $this->image->id,
            'changes' => $changes,
            'has_changes' => !empty($changes),
            'change_count' => count($changes),
        ];
    }

    /**
     * ✅ Check if there are pending changes
     */
    public function hasChanges(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("hasChanges() requires fluent mode");
        }
        
        if (!$this->image || empty($this->updateData)) {
            return false;
        }
        
        $preview = $this->preview();
        return $preview['has_changes'];
    }

    /**
     * 🔄 Reset all pending changes
     */
    public function reset(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("reset() requires fluent mode");
        }
        
        $this->updateData = [];
        return $this;
    }
}