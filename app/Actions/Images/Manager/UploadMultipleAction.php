<?php

namespace App\Actions\Images\Manager;

use App\Actions\Images\UploadImagesAction;
use Illuminate\Support\Collection;

/**
 * 📤 UPLOAD MULTIPLE ACTION - FACADE INTEGRATION
 *
 * Facade-friendly wrapper for UploadImagesAction
 * Maintains full backwards compatibility while adding fluent API
 * 
 * Usage:
 * Images::upload($files)->withMetadata(['folder' => 'products'])->async()
 * Images::upload($files)->sync()->attach($product)
 */
class UploadMultipleAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected UploadImagesAction $legacyAction;
    protected array $files = [];
    protected array $metadata = [];
    protected bool $asyncMode = true;
    protected $attachTo = null;

    public function __construct()
    {
        $this->legacyAction = new UploadImagesAction();
    }

    /**
     * 📤 Execute multiple file upload
     *
     * @param mixed ...$parameters - [files, metadata]
     */
    public function execute(...$parameters): mixed
    {
        [$files, $metadata] = $parameters + [[], []];

        if (!$this->canExecute($files, $metadata)) {
            return $this->handleReturn(collect());
        }

        // Store for fluent API
        $this->files = $files;
        $this->metadata = array_merge($this->metadata, $metadata);

        // Use the existing action for actual upload
        $result = $this->legacyAction->execute($files, $this->metadata);

        $this->logAction('upload_multiple', [
            'success' => $result['success'] ?? false,
            'upload_count' => $result['data']['upload_count'] ?? 0,
            'async_mode' => $this->asyncMode,
            'has_metadata' => !empty($this->metadata),
        ]);

        // Return images collection for fluent API
        $images = collect($result['data']['uploaded_images'] ?? []);
        
        // Handle attachment if specified
        if ($this->attachTo && !$images->isEmpty()) {
            $this->handleAttachment($images);
        }
        
        return $this->handleReturn($images);
    }

    /**
     * ✅ Validate upload parameters
     */
    public function canExecute(...$parameters): bool
    {
        [$files] = $parameters + [[]];

        if (!is_array($files) || empty($files)) {
            $this->errors[] = "Files array is required and cannot be empty";
            return false;
        }

        foreach ($files as $file) {
            if (!$file instanceof \Illuminate\Http\UploadedFile) {
                $this->errors[] = "All files must be UploadedFile instances";
                return false;
            }
        }

        return true;
    }

    /**
     * 🎯 FLUENT API METHODS (when in fluent mode)
     */

    /**
     * 📝 Add metadata to uploads
     */
    public function withMetadata(array $metadata): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("withMetadata() requires fluent mode");
        }
        
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    /**
     * 🏷️ Add title to all uploads
     */
    public function withTitle(string $title): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("withTitle() requires fluent mode");
        }
        
        $this->metadata['title'] = $title;
        return $this;
    }

    /**
     * 📝 Add alt text to all uploads
     */
    public function withAlt(string $altText): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("withAlt() requires fluent mode");
        }
        
        $this->metadata['alt_text'] = $altText;
        return $this;
    }

    /**
     * 📁 Set folder for uploads
     */
    public function inFolder(string $folder): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("inFolder() requires fluent mode");
        }
        
        $this->metadata['folder'] = $folder;
        return $this;
    }

    /**
     * 🏷️ Add tags to uploads
     */
    public function withTags(array $tags): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("withTags() requires fluent mode");
        }
        
        $this->metadata['tags'] = $tags;
        return $this;
    }

    /**
     * ⚡ Enable async processing (default)
     */
    public function async(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("async() requires fluent mode");
        }
        
        $this->asyncMode = true;
        return $this;
    }

    /**
     * ⏱️ Enable sync processing
     */
    public function sync(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("sync() requires fluent mode");
        }
        
        $this->asyncMode = false;
        return $this;
    }

    /**
     * 🔗 Attach uploads to a model after upload
     */
    public function attachTo($model): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("attachTo() requires fluent mode");
        }
        
        $this->attachTo = $model;
        return $this;
    }

    /**
     * 📊 Get upload results
     */
    public function getResults(): Collection
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getResults() requires fluent mode");
        }
        
        return $this->result ?? collect();
    }

    /**
     * 📈 Get upload count
     */
    public function count(): int
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("count() requires fluent mode");
        }
        
        return $this->result ? $this->result->count() : 0;
    }

    /**
     * 🎯 Get first uploaded image
     */
    public function first(): ?\App\Models\Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("first() requires fluent mode");
        }
        
        return $this->result ? $this->result->first() : null;
    }

    /**
     * 🔗 Handle model attachment
     */
    protected function handleAttachment(Collection $images): void
    {
        if (!$this->attachTo) {
            return;
        }

        try {
            foreach ($images as $image) {
                $image->attachTo($this->attachTo);
            }
            
            $this->logAction('images_attached', [
                'model_type' => get_class($this->attachTo),
                'model_id' => $this->attachTo->id ?? null,
                'images_count' => $images->count(),
            ]);
        } catch (\Exception $e) {
            $this->logAction('attachment_failed', [
                'error' => $e->getMessage(),
                'model_type' => get_class($this->attachTo),
                'images_count' => $images->count(),
            ]);
        }
    }
}