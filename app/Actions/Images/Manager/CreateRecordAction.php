<?php

namespace App\Actions\Images\Manager;

use App\Actions\Images\CreateImageRecordAction;
use App\Models\Image;

/**
 * 💾 CREATE RECORD ACTION - FACADE INTEGRATION
 *
 * Facade-friendly wrapper for CreateImageRecordAction
 * Maintains full backwards compatibility while adding fluent API
 * 
 * Usage:
 * Images::create()->fromStorage($storageData, $originalName, $mimeType, $metadata)
 * Images::create()->withData($data)->save()
 */
class CreateRecordAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected CreateImageRecordAction $legacyAction;
    protected array $storageData = [];
    protected ?string $originalFilename = null;
    protected ?string $mimeType = null;
    protected array $metadata = [];

    public function __construct()
    {
        $this->legacyAction = new CreateImageRecordAction();
    }

    /**
     * 💾 Execute image record creation
     *
     * @param mixed ...$parameters - [storageData, originalFilename, mimeType, metadata]
     */
    public function execute(...$parameters): mixed
    {
        [$storageData, $originalFilename, $mimeType, $metadata] = $parameters + [[], null, null, []];

        if (!$this->canExecute($storageData, $originalFilename, $mimeType, $metadata)) {
            return $this->handleReturn(null);
        }

        // Store for fluent API
        $this->storageData = $storageData;
        $this->originalFilename = $originalFilename;
        $this->mimeType = $mimeType;
        $this->metadata = $metadata;

        // Use the existing action for actual record creation
        $result = $this->legacyAction->execute($storageData, $originalFilename, $mimeType, $metadata);

        $this->logAction('create_image_record', [
            'success' => $result['success'] ?? false,
            'image_id' => $result['data']['image']->id ?? null,
            'filename' => $result['data']['image']->filename ?? null,
            'has_metadata' => !empty($metadata),
        ]);

        // Return Image model for fluent API
        $image = $result['data']['image'] ?? null;
        
        return $this->handleReturn($image);
    }

    /**
     * ✅ Validate record creation parameters
     */
    public function canExecute(...$parameters): bool
    {
        [$storageData, $originalFilename, $mimeType] = $parameters + [[], null, null];

        if (empty($storageData['filename']) || empty($storageData['url'])) {
            $this->errors[] = "Storage data must include filename and url";
            return false;
        }

        if (!$originalFilename) {
            $this->errors[] = "Original filename is required";
            return false;
        }

        if (!$mimeType) {
            $this->errors[] = "MIME type is required";
            return false;
        }

        return true;
    }

    /**
     * 🎯 FLUENT API METHODS (when in fluent mode)
     */

    /**
     * 📁 Create from storage data (main entry point)
     */
    public function fromStorage(array $storageData, string $originalFilename, string $mimeType, array $metadata = []): Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("fromStorage() requires fluent mode");
        }
        
        $this->execute($storageData, $originalFilename, $mimeType, $metadata);
        
        return $this->result;
    }

    /**
     * 📝 Add metadata
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
     * 🏷️ Add title
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
     * 📝 Add alt text
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
     * 📁 Set folder
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
     * 🏷️ Add tags
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
     * 💾 Save the image record
     */
    public function save(): Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("save() requires fluent mode");
        }
        
        if (empty($this->storageData) || !$this->originalFilename || !$this->mimeType) {
            throw new \BadMethodCallException("Storage data, original filename, and mime type must be set before calling save()");
        }
        
        return $this->execute($this->storageData, $this->originalFilename, $this->mimeType, $this->metadata);
    }

    /**
     * 📊 Get created image
     */
    public function getImage(): ?Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getImage() requires fluent mode");
        }
        
        return $this->result;
    }
}