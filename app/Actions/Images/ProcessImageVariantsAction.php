<?php

namespace App\Actions\Images;

use App\Actions\Base\BaseAction;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProcessImageVariantsAction extends BaseAction
{
    protected array $defaultSizes = [
        'thumb' => 150,
        'small' => 300,
        'medium' => 600,
        'large' => 1200,
    ];

    protected int $quality = 85;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 🎨 GENERATE IMAGE VARIANTS
     *
     * Create thumbnail and resized versions using DAM system
     *
     * @param Image $originalImage
     * @param array $variantTypes
     * @return array
     */
    public function execute(Image $originalImage, array $variantTypes = ['thumb', 'small', 'medium']): array
    {
        return $this->executeWithBaseHandling(function () use ($originalImage, $variantTypes) {
            $generatedVariants = [];

            foreach ($variantTypes as $variantType) {
                $variant = $this->generateVariant($originalImage, $variantType);
                if ($variant) {
                    $generatedVariants[] = $variant;
                }
            }

            return $this->success('Image variants generated successfully', [
                'original_image_id' => $originalImage->id,
                'variants_generated' => count($generatedVariants),
                'variant_types' => $variantTypes,
                'variants' => $generatedVariants
            ]);

        }, ['image_id' => $originalImage->id, 'variant_types' => $variantTypes]);
    }

    protected function generateVariant(Image $originalImage, string $variantType): ?Image
    {
        if (!isset($this->defaultSizes[$variantType])) {
            throw new \InvalidArgumentException("Unknown variant type: {$variantType}");
        }

        $targetSize = $this->defaultSizes[$variantType];

        // Skip if original is already smaller than target
        if ($originalImage->width <= $targetSize && $originalImage->height <= $targetSize) {
            return null;
        }

        // Check if variant already exists using DAM system
        if ($this->variantExists($originalImage, $variantType)) {
            return $this->getVariant($originalImage, $variantType);
        }

        // Download original image
        $originalContent = Storage::disk('images')->get($originalImage->filename);
        if (!$originalContent) {
            throw new \Exception("Could not retrieve original image from storage");
        }

        // Process with Intervention Image
        $manager = new ImageManager(new Driver());
        $image = $manager->read($originalContent);

        // Resize maintaining aspect ratio
        $image->scale(width: $targetSize, height: $targetSize);

        // Generate variant filename
        $pathinfo = pathinfo($originalImage->filename);
        $variantFilename = $pathinfo['filename'] . "-{$variantType}." . $pathinfo['extension'];

        // Save to storage
        $encodedImage = $image->toJpeg($this->quality);
        $path = Storage::disk('images')->put($variantFilename, $encodedImage);
        $url = Storage::disk('images')->url($variantFilename);

        // Create variant image record using existing DAM system
        $variant = Image::create([
            'filename' => $variantFilename,
            'url' => $url,
            'size' => strlen($encodedImage),
            'width' => $image->width(),
            'height' => $image->height(),
            'mime_type' => 'image/jpeg',
            'is_primary' => false,
            'sort_order' => 0,
            // Use DAM system for variant tracking
            'title' => $originalImage->title ? "{$originalImage->title} ({$variantType})" : "Variant ({$variantType})",
            'alt_text' => $originalImage->alt_text,
            'description' => "Generated {$variantType} variant of: {$originalImage->display_title}",
            'folder' => 'variants', // Organize variants in their own folder
            'tags' => array_merge($originalImage->tags ?? [], [$variantType, 'variant', "original-{$originalImage->id}"]),
        ]);

        return $variant;
    }

    /**
     * Get all variants for an image using DAM system
     *
     * @param Image $image
     * @return \Illuminate\Database\Eloquent\Collection<Image>
     */
    public function getVariants(Image $image): \Illuminate\Database\Eloquent\Collection
    {
        return Image::where('folder', 'variants')
            ->whereJsonContains('tags', "original-{$image->id}")
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Get specific variant for an image using DAM system
     */
    public function getVariant(Image $image, string $variantType): ?Image
    {
        return Image::where('folder', 'variants')
            ->whereJsonContains('tags', "original-{$image->id}")
            ->whereJsonContains('tags', $variantType)
            ->first();
    }

    /**
     * Delete all variants for an image using DAM system
     */
    public function deleteVariants(Image $image): void
    {
        $variants = $this->getVariants($image);

        foreach ($variants as $variant) {
            // Delete from storage
            if ($variant->filename) {
                Storage::disk('images')->delete($variant->filename);
            }
            
            // Delete record
            $variant->delete();
        }
    }

    /**
     * Check if variant already exists using DAM system
     */
    protected function variantExists(Image $originalImage, string $variantType): bool
    {
        return $this->getVariant($originalImage, $variantType) !== null;
    }
}