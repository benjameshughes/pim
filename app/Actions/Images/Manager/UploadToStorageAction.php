<?php

namespace App\Actions\Images\Manager;

use App\Actions\Images\UploadImageToStorageAction;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;

/**
 * ☁️ UPLOAD TO STORAGE ACTION - FACADE INTEGRATION
 *
 * Facade-friendly wrapper for UploadImageToStorageAction
 * Maintains full backwards compatibility while adding fluent API
 * 
 * Usage:
 * Images::storage()->upload($file)->to('products')
 * Images::storage()->upload($file)->withName('custom.jpg')
 */
class UploadToStorageAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected UploadImageToStorageAction $legacyAction;
    protected $file = null;
    protected ?string $targetFolder = null;
    protected ?string $customName = null;
    protected string $disk = 'images';

    public function __construct()
    {
        $this->legacyAction = new UploadImageToStorageAction();
    }

    /**
     * ☁️ Execute storage upload
     *
     * @param mixed ...$parameters - [file, customFilename]
     */
    public function execute(...$parameters): mixed
    {
        [$file, $customFilename] = $parameters + [null, null];

        if (!$this->canExecute($file, $customFilename)) {
            return $this->handleReturn(null);
        }

        // Store file for fluent API
        $this->file = $file;
        $this->customName = $customFilename;

        // Use the existing action for actual upload
        $result = $this->legacyAction->execute($file, $customFilename);

        $this->logAction('upload_to_storage', [
            'success' => $result['success'] ?? false,
            'filename' => $result['data']['filename'] ?? null,
            'size' => $result['data']['size'] ?? null,
            'disk' => $result['data']['disk'] ?? null,
        ]);

        // Return structured data for fluent API
        $uploadData = [
            'filename' => $result['data']['filename'] ?? null,
            'path' => $result['data']['path'] ?? null,
            'url' => $result['data']['url'] ?? null,
            'size' => $result['data']['size'] ?? null,
            'disk' => $result['data']['disk'] ?? $this->disk,
        ];
        
        return $this->handleReturn($uploadData);
    }

    /**
     * ✅ Validate upload parameters
     */
    public function canExecute(...$parameters): bool
    {
        [$file] = $parameters + [null];

        if (!($file instanceof UploadedFile || $file instanceof File)) {
            $this->errors[] = "First parameter must be an UploadedFile or File instance";
            return false;
        }

        return true;
    }

    /**
     * 🎯 FLUENT API METHODS (when in fluent mode)
     */

    /**
     * 📁 Specify target folder (future enhancement)
     */
    public function to(string $folder): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("to() requires fluent mode");
        }
        
        $this->targetFolder = $folder;
        // Note: Current legacy action doesn't support folders, 
        // but we're preparing the API for future enhancement
        
        return $this;
    }

    /**
     * 🏷️ Specify custom filename
     */
    public function withName(string $name): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("withName() requires fluent mode");
        }
        
        $this->customName = $name;
        
        // Re-execute with new name
        if ($this->file) {
            $result = $this->legacyAction->execute($this->file, $this->customName);
            $this->result = [
                'filename' => $result['data']['filename'] ?? null,
                'path' => $result['data']['path'] ?? null,
                'url' => $result['data']['url'] ?? null,
                'size' => $result['data']['size'] ?? null,
                'disk' => $result['data']['disk'] ?? $this->disk,
            ];
        }
        
        return $this;
    }

    /**
     * 💾 Specify different disk
     */
    public function disk(string $diskName): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("disk() requires fluent mode");
        }
        
        $this->disk = $diskName;
        // Note: Would need to enhance legacy action to support different disks
        
        return $this;
    }

    /**
     * 📊 Get upload result data
     */
    public function getData(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getData() requires fluent mode");
        }
        
        return $this->result ?? [];
    }

    /**
     * 🔗 Get storage URL
     */
    public function url(): ?string
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("url() requires fluent mode");
        }
        
        return $this->result['url'] ?? null;
    }

    /**
     * 📁 Get storage path
     */
    public function path(): ?string
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("path() requires fluent mode");
        }
        
        return $this->result['path'] ?? null;
    }

    /**
     * 🏷️ Get filename
     */
    public function filename(): ?string
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("filename() requires fluent mode");
        }
        
        return $this->result['filename'] ?? null;
    }

    /**
     * 📏 Get file size
     */
    public function size(): ?int
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("size() requires fluent mode");
        }
        
        return $this->result['size'] ?? null;
    }
}