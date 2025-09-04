<?php

namespace App\Actions\Images\Manager;

use App\Actions\Images\ExtractImageMetadataAction;
use App\Models\Image;

/**
 * 📐 EXTRACT METADATA ACTION - FACADE INTEGRATION
 *
 * Facade-friendly wrapper for ExtractImageMetadataAction
 * Maintains full backwards compatibility while adding fluent API
 * 
 * Usage:
 * Images::process($image)->metadata()->extract()
 * Images::process($image)->metadata()->getDimensions()
 */
class ExtractMetadataAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected ExtractImageMetadataAction $legacyAction;
    protected ?Image $image = null;
    protected array $extractedData = [];

    public function __construct()
    {
        $this->legacyAction = new ExtractImageMetadataAction();
    }

    /**
     * 📐 Execute metadata extraction
     *
     * @param mixed ...$parameters - [image]
     */
    public function execute(...$parameters): mixed
    {
        [$image] = $parameters + [null];

        if (!$this->canExecute($image)) {
            return $this->handleReturn([]);
        }

        // Store image for fluent API
        $this->image = $image;

        // Use the existing action for actual metadata extraction
        $result = $this->legacyAction->execute($image);

        $this->logAction('extract_metadata', [
            'success' => $result['success'] ?? false,
            'image_id' => $image->id,
            'width' => $result['data']['width'] ?? null,
            'height' => $result['data']['height'] ?? null,
            'size' => $result['data']['size'] ?? null,
        ]);

        // Store extracted data for fluent API
        $this->extractedData = [
            'width' => $result['data']['width'] ?? 0,
            'height' => $result['data']['height'] ?? 0,
            'size' => $result['data']['size'] ?? 0,
            'mime_type' => $result['data']['mime_type'] ?? null,
            'image' => $result['data']['image'] ?? $image,
        ];
        
        return $this->handleReturn($this->extractedData);
    }

    /**
     * ✅ Validate metadata extraction parameters
     */
    public function canExecute(...$parameters): bool
    {
        [$image] = $parameters + [null];

        if (!($image instanceof Image)) {
            $this->errors[] = "First parameter must be an Image instance";
            return false;
        }

        if (!$image->filename || !$image->url) {
            $this->errors[] = "Image must have filename and url";
            return false;
        }

        return true;
    }

    /**
     * 🎯 FLUENT API METHODS (when in fluent mode)
     */

    /**
     * 📐 Extract metadata (main operation)
     */
    public function extract(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("extract() requires fluent mode");
        }
        
        if (!$this->image) {
            throw new \BadMethodCallException("No image set for metadata extraction");
        }
        
        $this->execute($this->image);
        return $this->extractedData;
    }

    /**
     * 📏 Get image dimensions
     */
    public function getDimensions(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getDimensions() requires fluent mode");
        }
        
        if (empty($this->extractedData) && $this->image) {
            $this->execute($this->image);
        }
        
        return [
            'width' => $this->extractedData['width'] ?? 0,
            'height' => $this->extractedData['height'] ?? 0,
        ];
    }

    /**
     * ⚖️ Get file size
     */
    public function getSize(): int
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getSize() requires fluent mode");
        }
        
        if (empty($this->extractedData) && $this->image) {
            $this->execute($this->image);
        }
        
        return $this->extractedData['size'] ?? 0;
    }

    /**
     * 🎨 Get MIME type
     */
    public function getMimeType(): ?string
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getMimeType() requires fluent mode");
        }
        
        if (empty($this->extractedData) && $this->image) {
            $this->execute($this->image);
        }
        
        return $this->extractedData['mime_type'] ?? null;
    }

    /**
     * 📊 Get complete metadata
     */
    public function getAllMetadata(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getAllMetadata() requires fluent mode");
        }
        
        if (empty($this->extractedData) && $this->image) {
            $this->execute($this->image);
        }
        
        return $this->extractedData;
    }

    /**
     * 🖼️ Get updated image model
     */
    public function getImage(): ?Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getImage() requires fluent mode");
        }
        
        if (empty($this->extractedData) && $this->image) {
            $this->execute($this->image);
        }
        
        return $this->extractedData['image'] ?? $this->image;
    }

    /**
     * 📐 Get aspect ratio
     */
    public function getAspectRatio(): float
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getAspectRatio() requires fluent mode");
        }
        
        $dimensions = $this->getDimensions();
        
        if ($dimensions['height'] == 0) {
            return 0;
        }
        
        return $dimensions['width'] / $dimensions['height'];
    }

    /**
     * ✅ Check if image is landscape
     */
    public function isLandscape(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("isLandscape() requires fluent mode");
        }
        
        $dimensions = $this->getDimensions();
        return $dimensions['width'] > $dimensions['height'];
    }

    /**
     * ✅ Check if image is portrait
     */
    public function isPortrait(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("isPortrait() requires fluent mode");
        }
        
        $dimensions = $this->getDimensions();
        return $dimensions['height'] > $dimensions['width'];
    }

    /**
     * ✅ Check if image is square
     */
    public function isSquare(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("isSquare() requires fluent mode");
        }
        
        $dimensions = $this->getDimensions();
        return $dimensions['width'] === $dimensions['height'];
    }
}