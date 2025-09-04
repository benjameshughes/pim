<?php

namespace App\Services;

use App\Actions\Images\Manager\AttachImageAction;
use App\Actions\Images\Manager\BulkDeleteAction;
use App\Actions\Images\Manager\BulkMoveAction;
use App\Actions\Images\Manager\BulkTagAction;
use App\Actions\Images\Manager\CreateRecordAction;
use App\Actions\Images\Manager\FindFamilyAction;
use App\Actions\Images\Manager\RetrieveWithVariantsAction;
use App\Actions\Images\Manager\DeleteAction;
use App\Actions\Images\Manager\DetachImageAction;
use App\Actions\Images\Manager\ExtractMetadataAction;
use App\Actions\Images\Manager\ProcessVariantsAction;
use App\Actions\Images\Manager\ReprocessAction;
use App\Actions\Images\Manager\SmartResolverAction;
use App\Actions\Images\Manager\UpdateAction;
use App\Actions\Images\Manager\UploadMultipleAction;
use App\Actions\Images\Manager\UploadToStorageAction;
use App\Actions\Images\Manager\ValidateFileAction;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * 🎨✨ IMAGE MANAGER SERVICE - ORCHESTRATING ACTIONS ✨🎨
 *
 * Central service that orchestrates image actions for a beautiful fluent API:
 * - Coordinates different action classes
 * - Provides fluent interface for chaining
 * - Handles context switching (product/variant/color)
 *
 * This is the backbone of the Images facade!
 */
class ImageManager
{
    /**
     * 🎯 FLUENT API ENTRY POINTS
     */

    /**
     * 📦 Work with product-level images
     */
    public function product(Product $product): ProductImageContext
    {
        return new ProductImageContext($product);
    }

    /**
     * 💎 Work with variant-specific images  
     */
    public function variant(ProductVariant $variant): VariantImageContext
    {
        return new VariantImageContext($variant);
    }

    /**
     * 🎨 Work with color group images
     */
    public function color(Product $product, string $color): ColorImageContext
    {
        return new ColorImageContext($product, $color);
    }

    /**
     * 🧠 Smart image resolution
     */
    public function smart(ProductVariant $variant): SmartImageContext
    {
        return new SmartImageContext($variant);
    }

    /**
     * 🎨 Image processing (variants, thumbnails, etc.)
     */
    public function process(Image $image): ProcessingImageContext
    {
        return new ProcessingImageContext($image);
    }

    /**
     * ☁️ Storage operations (upload, manage files)
     */
    public function storage(): StorageImageContext
    {
        return new StorageImageContext();
    }

    /**
     * 📤 Upload multiple images with metadata
     */
    public function upload(array $files): UploadImageContext
    {
        return new UploadImageContext($files);
    }

    /**
     * 💾 Create image records from storage data
     */
    public function create(): CreateImageContext
    {
        return new CreateImageContext();
    }

    /**
     * ✅ Validate image files
     */
    public function validate($file): ValidateImageContext
    {
        return new ValidateImageContext($file);
    }

    /**
     * 🗑️ Delete images with options
     */
    public function delete(Image $image): DeleteImageContext
    {
        return new DeleteImageContext($image);
    }

    /**
     * 🔄 Update image metadata
     */
    public function update(Image $image): UpdateImageContext
    {
        return new UpdateImageContext($image);
    }

    /**
     * 📦 Bulk operations on multiple images
     */
    public function bulk(): BulkImageContext
    {
        return new BulkImageContext();
    }

    /**
     * 🔍 Intelligent retrieval operations
     */
    public function retrieve(Image $image): RetrieveImageContext
    {
        return new RetrieveImageContext($image);
    }

    /**
     * 🔎 Find image families and related images
     */
    public function find(Image $image): FindImageContext
    {
        return new FindImageContext($image);
    }

    /**
     * 🎯 DIRECT ACTION ACCESS (for advanced usage)
     */

    /**
     * 🔗 Get attach action instance
     */
    public function attachAction(): AttachImageAction
    {
        return new AttachImageAction();
    }

    /**
     * 🔌 Get detach action instance
     */
    public function detachAction(): DetachImageAction
    {
        return new DetachImageAction();
    }

    /**
     * 🧠 Get smart resolver action instance
     */
    public function smartAction(): SmartResolverAction
    {
        return new SmartResolverAction();
    }

    /**
     * 🎨 Get process variants action instance
     */
    public function processAction(): ProcessVariantsAction
    {
        return new ProcessVariantsAction();
    }
}

/**
 * 📦 Product Image Context - Fluent API for product images
 */
class ProductImageContext
{
    protected Product $product;
    protected $pendingImage = null;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * 🔗 Attach images to product (CHAINABLE)
     */
    public function attach($images, array $options = []): self
    {
        $action = new AttachImageAction();
        $action->fluent()->execute($images, $this->product, null, $options);
        $this->pendingImage = is_array($images) ? $images[0] ?? null : $images;
        return $this;  // ✅ Return $this for chaining!
    }

    /**
     * 🔌 Detach images from product (CHAINABLE)
     */
    public function detach($images): self
    {
        $action = new DetachImageAction();
        $action->fluent()->execute($images, $this->product);
        return $this;  // ✅ Return $this for chaining!
    }

    /**
     * ⭐ Set attached image as primary (CHAINABLE - requires attach first)
     */
    public function asPrimary(): self
    {
        if ($this->pendingImage) {
            $action = new AttachImageAction();
            $action->fluent()->execute([$this->pendingImage], $this->product, null, ['is_primary' => true]);
        }
        return $this;  // ✅ Return $this for chaining!
    }

    /**
     * ⭐ Set specific image as primary (DIRECT - no attach needed)
     */
    public function setPrimary(int $imageId): self
    {
        $image = \App\Models\Image::find($imageId);
        if ($image) {
            // Use the Image model's exclusive primary logic
            $image->setPrimaryFor($this->product);
        }
        return $this;  // ✅ Return $this for chaining!
    }

    /**
     * 🔍 Get product images (TERMINATES CHAIN)
     */
    public function get()
    {
        return $this->product->images;
    }

    /**
     * ⭐ Get primary image (TERMINATES CHAIN)
     */
    public function primary()
    {
        return $this->product->primaryImage();
    }

    /**
     * 📊 Get image count (TERMINATES CHAIN)
     */
    public function count(): int
    {
        return $this->product->images()->count();
    }

    /**
     * 🎨 Switch to color group context (CONTEXT SWITCH)
     */
    public function color(string $color): ColorImageContext
    {
        return new ColorImageContext($this->product, $color);
    }

    /**
     * 💎 Switch to variant context (CONTEXT SWITCH)
     */
    public function variant(ProductVariant $variant): VariantImageContext
    {
        return new VariantImageContext($variant);
    }
}

/**
 * 💎 Variant Image Context - Fluent API for variant images
 */
class VariantImageContext
{
    protected ProductVariant $variant;

    public function __construct(ProductVariant $variant)
    {
        $this->variant = $variant;
    }

    /**
     * 🔗 Attach images to variant
     */
    public function attach($images, array $options = []): AttachImageAction
    {
        $action = new AttachImageAction();
        return $action->fluent()->execute($images, $this->variant, null, $options);
    }

    /**
     * 🔌 Detach images from variant
     */
    public function detach($images): DetachImageAction
    {
        $action = new DetachImageAction();
        return $action->fluent()->execute($images, $this->variant);
    }

    /**
     * 🔍 Get variant images
     */
    public function get()
    {
        return $this->variant->images;
    }

    /**
     * ⭐ Get primary image
     */
    public function primary()
    {
        return $this->variant->primaryImage();
    }

    /**
     * 📊 Get image count
     */
    public function count(): int
    {
        return $this->variant->images()->count();
    }

    /**
     * ⭐ Set specific image as primary for variant
     */
    public function setPrimary(int $imageId): self
    {
        $image = \App\Models\Image::find($imageId);
        if ($image) {
            // Use the Image model's variant-specific primary logic
            $image->setPrimaryFor($this->variant);
        }
        return $this;  // ✅ Return $this for chaining!
    }
}

/**
 * 🎨 Color Image Context - Fluent API for color group images
 */
class ColorImageContext
{
    protected Product $product;
    protected string $color;

    public function __construct(Product $product, string $color)
    {
        $this->product = $product;
        $this->color = $color;
    }

    /**
     * 🔗 Attach images to color group
     */
    public function attach($images, array $options = []): AttachImageAction
    {
        $action = new AttachImageAction();
        return $action->fluent()->execute($images, $this->product, $this->color, $options);
    }

    /**
     * 🔌 Detach images from color group
     */
    public function detach($images): DetachImageAction
    {
        $action = new DetachImageAction();
        return $action->fluent()->execute($images, $this->product, $this->color);
    }

    /**
     * 🔍 Get color group images
     */
    public function get()
    {
        return $this->product->getImagesForColor($this->color)->get();
    }

    /**
     * ⭐ Get primary image for color
     */
    public function primary()
    {
        return $this->product->getPrimaryImageForColor($this->color);
    }

    /**
     * 📊 Get image count for color
     */
    public function count(): int
    {
        return $this->product->getImagesForColor($this->color)->count();
    }

    /**
     * ⭐ Set specific image as primary for color group
     */
    public function setPrimary(int $imageId): self
    {
        $image = \App\Models\Image::find($imageId);
        if ($image) {
            // Use the Image model's color-specific primary logic
            $image->setPrimaryForColor($this->product, $this->color);
        }
        return $this;  // ✅ Return $this for chaining!
    }
}

/**
 * 🧠 Smart Image Context - Fluent API for smart resolution
 */
class SmartImageContext
{
    protected ProductVariant $variant;

    public function __construct(ProductVariant $variant)
    {
        $this->variant = $variant;
    }

    /**
     * 🎯 Get display image with smart fallback
     */
    public function display()
    {
        $action = new SmartResolverAction();
        return $action->execute($this->variant, 'display');
    }

    /**
     * 📋 Get all images with source tracking
     */
    public function images()
    {
        $action = new SmartResolverAction();
        return $action->execute($this->variant, 'all');
    }

    /**
     * 📊 Get comprehensive stats
     */
    public function stats()
    {
        $action = new SmartResolverAction();
        return $action->execute($this->variant, 'stats');
    }

    /**
     * 📈 Get availability info
     */
    public function availability()
    {
        return $this->variant->getImageAvailability();
    }

    /**
     * 🎯 Get image source type
     */
    public function source()
    {
        return $this->variant->getDisplayImageSource();
    }

    /**
     * ✅ Check if has any images
     */
    public function hasImages(): bool
    {
        return $this->variant->hasAnyImages();
    }

    /**
     * 🧠 Get fluent resolver for advanced operations
     */
    public function resolver(): SmartResolverAction
    {
        $action = new SmartResolverAction();
        return $action->fluent()->execute($this->variant);
    }
}

/**
 * 🎨 Processing Image Context - Fluent API for image processing
 */
class ProcessingImageContext
{
    protected Image $image;

    public function __construct(Image $image)
    {
        $this->image = $image;
    }

    /**
     * 📷 Generate thumbnails only
     */
    public function thumbnails()
    {
        $action = new ProcessVariantsAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->thumbnails();
    }

    /**
     * 📐 Extract metadata from image
     */
    public function metadata()
    {
        $action = new ExtractMetadataAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction;
    }

    /**
     * 🔄 Reprocess image metadata
     */
    public function reprocess()
    {
        $action = new ReprocessAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction;
    }

    /**
     * 🎨 Generate specific variants
     */
    public function variants(array $types = ['thumb', 'small', 'medium'])
    {
        $action = new ProcessVariantsAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->variants($types);
    }

    /**
     * 🌟 Generate all available variants
     */
    public function all()
    {
        $action = new ProcessVariantsAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->all();
    }

    /**
     * 🔍 Get existing variants
     */
    public function existing()
    {
        $action = new ProcessVariantsAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->existing();
    }

    /**
     * 🎯 Get specific variant type
     */
    public function variant(string $type)
    {
        $action = new ProcessVariantsAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->variant($type);
    }

    /**
     * 🗑️ Delete all variants
     */
    public function deleteVariants(): bool
    {
        $action = new ProcessVariantsAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->deleteAll();
    }
}

/**
 * ☁️ Storage Image Context - Fluent API for storage operations
 */
class StorageImageContext
{
    /**
     * ☁️ Upload file to storage
     */
    public function upload($file, ?string $customFilename = null)
    {
        $action = new UploadToStorageAction();
        $fluentAction = $action->fluent()->execute($file, $customFilename);
        return $fluentAction;
    }
}

/**
 * 📤 Upload Image Context - FLUENT API with AUTO-EXECUTION MAGIC! ✨
 */
class UploadImageContext implements \JsonSerializable
{
    protected array $files;
    protected UploadMultipleAction $action;
    protected array $metadata = [];

    public function __construct(array $files)
    {
        $this->files = $files;
        $this->action = new UploadMultipleAction();
        $this->action = $this->action->fluent();
    }

    /**
     * 📝 Add metadata to uploads (CHAINABLE)
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        $this->action->withMetadata($metadata);
        return $this; // ✅ Return $this for chaining!
    }

    /**
     * 🏷️ Add title to all uploads (CHAINABLE)
     */
    public function withTitle(string $title): self
    {
        $this->metadata['title'] = $title;
        $this->action->withTitle($title);
        return $this; // ✅ Return $this for chaining!
    }

    /**
     * 📁 Set folder for uploads (CHAINABLE)
     */
    public function inFolder(string $folder): self
    {
        $this->metadata['folder'] = $folder;
        $this->action->inFolder($folder);
        return $this; // ✅ Return $this for chaining!
    }

    /**
     * 🏷️ Add tags to uploads (CHAINABLE)
     */
    public function withTags(array $tags): self
    {
        $this->metadata['tags'] = $tags;
        $this->action->withTags($tags);
        return $this; // ✅ Return $this for chaining!
    }

    /**
     * 🎨 Generate variants after upload (CHAINABLE)
     */
    public function generateVariants(bool $generate = true): self
    {
        $this->action->generateVariants($generate);
        return $this; // ✅ Return $this for chaining!
    }

    /**
     * ⚡ Execute upload and return results (TERMINATES CHAIN)
     */
    public function execute(): array
    {
        return $this->action->execute($this->files, $this->metadata);
    }

    // 🪄 MAGIC METHODS FOR AUTO-EXECUTION! ✨
    // =====================================
    
    /**
     * 🎯 Auto-execute when converted to string
     * Usage: echo Images::upload($files)->inFolder('products'); // "uploaded" or "failed"
     */
    public function __toString(): string
    {
        try {
            $result = $this->execute();
            return isset($result['success']) && $result['success'] ? 'uploaded' : 'failed';
        } catch (\Exception $e) {
            return 'error';
        }
    }
    
    /**
     * 🎯 Auto-execute when called as function
     * Usage: $result = Images::upload($files)->generateVariants()(); // Full result array
     */
    public function __invoke(): array
    {
        return $this->execute();
    }
    
    /**
     * 🎯 Auto-execute when JSON encoded
     * Usage: json_encode(Images::upload($files)->inFolder('products')); // Auto-executes
     */
    public function jsonSerialize(): array
    {
        return $this->execute();
    }

    /**
     * ⚡ Enable async processing (default)
     */
    public function async()
    {
        $action = new UploadMultipleAction();
        $fluentAction = $action->fluent()->execute($this->files);
        return $fluentAction->async();
    }

    /**
     * ⏱️ Enable sync processing
     */
    public function sync()
    {
        $action = new UploadMultipleAction();
        $fluentAction = $action->fluent()->execute($this->files);
        return $fluentAction->sync();
    }

    /**
     * 🔗 Attach uploads to a model after upload
     */
    public function attachTo($model)
    {
        $action = new UploadMultipleAction();
        $fluentAction = $action->fluent()->execute($this->files);
        return $fluentAction->attachTo($model);
    }
}

/**
 * 💾 Create Image Context - Fluent API for creating image records
 */
class CreateImageContext
{
    /**
     * 📁 Create from storage data
     */
    public function fromStorage(array $storageData, string $originalFilename, string $mimeType, array $metadata = [])
    {
        $action = new CreateRecordAction();
        return $action->fluent()->fromStorage($storageData, $originalFilename, $mimeType, $metadata);
    }
}

/**
 * ✅ Validate Image Context - FLUENT API with AUTO-EXECUTION MAGIC! ✨
 */
class ValidateImageContext implements \JsonSerializable
{
    protected ValidateFileAction $action;
    protected $file;

    public function __construct($file)
    {
        $this->file = $file;
        $this->action = new ValidateFileAction();
        $this->action = $this->action->fluent();  // Get the fluent action and store it
    }

    /**
     * 📏 Set maximum file size
     */
    public function size(string $maxSize): self
    {
        $this->action->size($maxSize);
        return $this;  // ✅ Return $this for chaining!
    }

    /**
     * 📏 Set maximum file size (alias)
     */
    public function maxFileSize(string $maxSize): self
    {
        $this->action->maxFileSize($maxSize);
        return $this;  // ✅ Return $this for chaining!
    }

    /**
     * 🔢 Set maximum number of files
     */
    public function maxFiles(int $maxFiles): self
    {
        $this->action->maxFiles($maxFiles);
        return $this;  // ✅ Return $this for chaining!
    }

    /**
     * 🖼️ Validate images only
     */
    public function imageOnly(): self
    {
        $this->action->imageOnly();
        return $this;  // ✅ Return $this for chaining!
    }

    /**
     * 🎯 Start allowed file types configuration
     */
    public function allowed(): self
    {
        $this->action->allowed();
        return $this;  // ✅ Return $this for chaining!
    }

    /**
     * 🎨 Set allowed file types
     */
    public function types(array $types): self
    {
        $this->action->types($types);
        return $this;  // ✅ Return $this for chaining!
    }

    /**
     * 🔒 Enable strict validation mode
     */
    public function strict(): self
    {
        $this->action->strict();
        return $this;  // ✅ Return $this for chaining!
    }

    /**
     * 📄 Allow documents alongside images
     */
    public function documents(): self
    {
        $this->action->documents();
        return $this;  // ✅ Return $this for chaining!
    }

    /**
     * 📋 Allow specific file types
     */
    public function images(): self
    {
        $this->action->images();
        return $this;  // ✅ Return $this for chaining!
    }

    /**
     * ⚡ Execute validation and return results (TERMINATES CHAIN)
     */
    public function execute(): array
    {
        // The action is already configured via fluent chaining
        // Now execute with the stored file
        $result = $this->action->execute($this->file);
        
        // Ensure we return an array result
        if (is_array($result)) {
            return $result;
        }
        
        // Fallback if result is not array (shouldn't happen in normal flow)
        return ['valid' => false, 'errors' => ['Validation failed to return proper result']];
    }

    /**
     * ✅ Perform validation check (TERMINATES CHAIN)
     */
    public function check(): bool
    {
        $result = $this->execute();
        return is_array($result) ? ($result['valid'] ?? false) : false;
    }

    /**
     * 📊 Get detailed validation results (TERMINATES CHAIN) - alias for execute()
     */
    public function results(): array
    {
        return $this->execute();
    }

    /**
     * ❌ Get validation errors (TERMINATES CHAIN)
     */
    public function getErrors(): array
    {
        $result = $this->execute();
        return is_array($result) ? ($result['errors'] ?? []) : [];
    }

    /**
     * ✅ Check if validation passes (TERMINATES CHAIN)
     */
    public function passes(): bool
    {
        return $this->check();
    }

    // 🪄 MAGIC METHODS FOR AUTO-EXECUTION! ✨
    // =====================================
    
    /**
     * 🎯 Auto-execute when converted to string
     * Usage: echo Images::validate($files)->imageOnly(); // "true" or "false"
     */
    public function __toString(): string
    {
        try {
            $result = $this->execute();
            return $result['valid'] ? 'true' : 'false';
        } catch (\Exception $e) {
            return 'error';
        }
    }
    
    /**
     * 🎯 Auto-execute when called as function
     * Usage: $result = Images::validate($files)->imageOnly()(); // Full array result
     */
    public function __invoke(): array
    {
        return $this->execute();
    }
    
    /**
     * 🎯 Auto-execute when JSON encoded
     * Usage: json_encode(Images::validate($files)->imageOnly()); // Auto-executes
     */
    public function jsonSerialize(): array
    {
        return $this->execute();
    }

    /**
     * 🎯 Auto-execute when cast to boolean
     * Usage: if (Images::validate($files)->imageOnly()) { ... } // Auto-validates!
     */
    public function __debugInfo(): array
    {
        $result = $this->execute();
        return [
            'valid' => $result['valid'],
            'errors' => $result['errors'],
            'file_count' => $result['file_count'] ?? 0,
            'auto_executed' => true,
        ];
    }
}

/**
 * 🗑️ Delete Image Context - Fluent API for image deletion
 */
class DeleteImageContext
{
    protected Image $image;

    public function __construct(Image $image)
    {
        $this->image = $image;
    }

    /**
     * 🗑️ Include variants in deletion
     */
    public function withVariants()
    {
        $action = new DeleteAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->withVariants();
    }

    /**
     * 📄 Delete only the main image (skip variants)
     */
    public function imageOnly()
    {
        $action = new DeleteAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->imageOnly();
    }

    /**
     * ⚡ Permanent deletion (cannot be recovered)
     */
    public function permanently(): bool
    {
        $action = new DeleteAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->permanently();
    }

    /**
     * ✅ Confirm deletion intent
     */
    public function confirm()
    {
        $action = new DeleteAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->confirm();
    }

    /**
     * 🔢 Get deletion preview
     */
    public function preview(): array
    {
        $action = new DeleteAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->preview();
    }
}

/**
 * 🔄 Update Image Context - Fluent API for image updates
 */
class UpdateImageContext
{
    protected Image $image;

    public function __construct(Image $image)
    {
        $this->image = $image;
    }

    /**
     * 📝 Update metadata (bulk update)
     */
    public function metadata(array $metadata)
    {
        $action = new UpdateAction();
        $fluentAction = $action->fluent()->execute($this->image, []);
        return $fluentAction->metadata($metadata);
    }

    /**
     * 🏷️ Update title
     */
    public function title(string $title)
    {
        $action = new UpdateAction();
        $fluentAction = $action->fluent()->execute($this->image, []);
        return $fluentAction->title($title);
    }

    /**
     * 📝 Update alt text
     */
    public function alt(string $altText)
    {
        $action = new UpdateAction();
        $fluentAction = $action->fluent()->execute($this->image, []);
        return $fluentAction->alt($altText);
    }

    /**
     * 📁 Move to folder
     */
    public function moveToFolder(string $folder)
    {
        $action = new UpdateAction();
        $fluentAction = $action->fluent()->execute($this->image, []);
        return $fluentAction->moveToFolder($folder);
    }

    /**
     * 🏷️ Set tags (replaces existing)
     */
    public function setTags(array $tags)
    {
        $action = new UpdateAction();
        $fluentAction = $action->fluent()->execute($this->image, []);
        return $fluentAction->setTags($tags);
    }
}

/**
 * 📦 Bulk Image Context - Fluent API for bulk operations
 */
class BulkImageContext
{
    /**
     * 🗑️ Bulk delete multiple images
     */
    public function delete(array $imageIds): BulkDeleteAction
    {
        $action = new BulkDeleteAction();
        return $action->fluent()->execute($imageIds);
    }

    /**
     * 📁 Bulk move multiple images to folder
     */
    public function move(array $imageIds, ?string $targetFolder = null): BulkMoveAction
    {
        $action = new BulkMoveAction();
        $fluentAction = $action->fluent()->execute($imageIds, $targetFolder);
        
        if ($targetFolder) {
            return $fluentAction;
        }
        
        return $fluentAction;
    }

    /**
     * 🏷️ Bulk tag multiple images
     */
    public function tag(array $imageIds, array $tags = []): BulkTagAction
    {
        $action = new BulkTagAction();
        return $action->fluent()->execute($imageIds, $tags);
    }
}

/**
 * 🔍 Retrieve Image Context - Fluent API for intelligent image retrieval
 */
class RetrieveImageContext
{
    protected Image $image;

    public function __construct(Image $image)
    {
        $this->image = $image;
    }

    /**
     * 🖼️ Retrieve image with all its variants
     */
    public function withVariants(): RetrieveWithVariantsAction
    {
        $action = new RetrieveWithVariantsAction();
        return $action->fluent()->execute($this->image);
    }

    /**
     * 🎨 Get gallery-ready image data
     */
    public function gallery(): RetrieveWithVariantsAction
    {
        $action = new RetrieveWithVariantsAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->gallery();
    }

    /**
     * ⭐ Get original image if this is a variant
     */
    public function original(): ?Image
    {
        $action = new RetrieveWithVariantsAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->original();
    }

    /**
     * 🎯 Get current viewing image context
     */
    public function current(): ?Image
    {
        $action = new RetrieveWithVariantsAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->current();
    }

    /**
     * 📸 Get all variants (without original)
     */
    public function variants()
    {
        $action = new RetrieveWithVariantsAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->variants();
    }

    /**
     * 👪 Get complete family (original + variants)
     */
    public function family()
    {
        $action = new RetrieveWithVariantsAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->family();
    }

    /**
     * 📊 Get retrieval statistics
     */
    public function stats(): array
    {
        $action = new RetrieveWithVariantsAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->stats();
    }
}

/**
 * 🔎 Find Image Context - Fluent API for finding image families
 */
class FindImageContext
{
    protected Image $image;

    public function __construct(Image $image)
    {
        $this->image = $image;
    }

    /**
     * 👪 Find image family (original + all variants)
     */
    public function family(): FindFamilyAction
    {
        $action = new FindFamilyAction();
        return $action->fluent()->execute($this->image);
    }

    /**
     * 👥 Find related images (alias for family)
     */
    public function relatives(): FindFamilyAction
    {
        $action = new FindFamilyAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->relatives();
    }

    /**
     * ⭐ Get original parent image
     */
    public function original(): ?Image
    {
        $action = new FindFamilyAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->original();
    }

    /**
     * 👶 Get child variants only
     */
    public function variants()
    {
        $action = new FindFamilyAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->variants();
    }

    /**
     * 📏 Get family ordered by size
     */
    public function ordered()
    {
        $action = new FindFamilyAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->ordered();
    }

    /**
     * 📋 Get specific variant by type
     */
    public function variant(string $type): ?Image
    {
        $action = new FindFamilyAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->variant($type);
    }

    /**
     * 🔢 Get family size count
     */
    public function count(): int
    {
        $action = new FindFamilyAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->count();
    }

    /**
     * 📊 Get comprehensive family stats
     */
    public function stats(): array
    {
        $action = new FindFamilyAction();
        $fluentAction = $action->fluent()->execute($this->image);
        return $fluentAction->stats();
    }
}