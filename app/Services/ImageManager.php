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

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * 🔗 Attach images to product
     */
    public function attach($images, array $options = []): AttachImageAction
    {
        $action = new AttachImageAction();
        return $action->fluent()->execute($images, $this->product, null, $options);
    }

    /**
     * 🔌 Detach images from product
     */
    public function detach($images): DetachImageAction
    {
        $action = new DetachImageAction();
        return $action->fluent()->execute($images, $this->product);
    }

    /**
     * 🔍 Get product images
     */
    public function get()
    {
        return $this->product->images;
    }

    /**
     * ⭐ Get primary image
     */
    public function primary()
    {
        return $this->product->primaryImage();
    }

    /**
     * 📊 Get image count
     */
    public function count(): int
    {
        return $this->product->images()->count();
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
 * 📤 Upload Image Context - Fluent API for multiple file uploads
 */
class UploadImageContext
{
    protected array $files;

    public function __construct(array $files)
    {
        $this->files = $files;
    }

    /**
     * 📝 Add metadata to uploads
     */
    public function withMetadata(array $metadata)
    {
        $action = new UploadMultipleAction();
        $fluentAction = $action->fluent()->execute($this->files, $metadata);
        return $fluentAction->withMetadata($metadata);
    }

    /**
     * 🏷️ Add title to all uploads
     */
    public function withTitle(string $title)
    {
        $action = new UploadMultipleAction();
        $fluentAction = $action->fluent()->execute($this->files);
        return $fluentAction->withTitle($title);
    }

    /**
     * 📁 Set folder for uploads
     */
    public function inFolder(string $folder)
    {
        $action = new UploadMultipleAction();
        $fluentAction = $action->fluent()->execute($this->files);
        return $fluentAction->inFolder($folder);
    }

    /**
     * 🏷️ Add tags to uploads
     */
    public function withTags(array $tags)
    {
        $action = new UploadMultipleAction();
        $fluentAction = $action->fluent()->execute($this->files);
        return $fluentAction->withTags($tags);
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
 * ✅ Validate Image Context - Fluent API for file validation
 */
class ValidateImageContext
{
    protected $file;

    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * 📏 Set maximum file size
     */
    public function size(string $maxSize)
    {
        $action = new ValidateFileAction();
        $fluentAction = $action->fluent()->execute($this->file);
        return $fluentAction->size($maxSize);
    }

    /**
     * 📏 Set maximum file size (alias)
     */
    public function maxFileSize(string $maxSize)
    {
        $action = new ValidateFileAction();
        $fluentAction = $action->fluent()->execute($this->file);
        return $fluentAction->maxFileSize($maxSize);
    }

    /**
     * 🔢 Set maximum number of files
     */
    public function maxFiles(int $maxFiles)
    {
        $action = new ValidateFileAction();
        $fluentAction = $action->fluent()->execute($this->file);
        return $fluentAction->maxFiles($maxFiles);
    }

    /**
     * 🖼️ Validate images only
     */
    public function imageOnly()
    {
        $action = new ValidateFileAction();
        $fluentAction = $action->fluent()->execute($this->file);
        return $fluentAction->imageOnly();
    }

    /**
     * 🎯 Start allowed file types configuration
     */
    public function allowed()
    {
        $action = new ValidateFileAction();
        $fluentAction = $action->fluent()->execute($this->file);
        return $fluentAction->allowed();
    }

    /**
     * 🎨 Set allowed file types
     */
    public function types(array $types)
    {
        $action = new ValidateFileAction();
        $fluentAction = $action->fluent()->execute($this->file);
        return $fluentAction->types($types);
    }

    /**
     * 🔒 Enable strict validation mode
     */
    public function strict()
    {
        $action = new ValidateFileAction();
        $fluentAction = $action->fluent()->execute($this->file);
        return $fluentAction->strict();
    }

    /**
     * ✅ Perform validation check
     */
    public function check(): bool
    {
        $action = new ValidateFileAction();
        $result = $action->execute($this->file);
        return is_array($result) ? ($result['valid'] ?? false) : false;
    }

    /**
     * 📊 Get detailed validation results
     */
    public function results(): array
    {
        $action = new ValidateFileAction();
        $result = $action->execute($this->file);
        return is_array($result) ? $result : [];
    }

    /**
     * ❌ Get validation errors
     */
    public function errors(): array
    {
        $action = new ValidateFileAction();
        $fluentAction = $action->fluent()->execute($this->file);
        return $fluentAction->getValidationErrors();
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