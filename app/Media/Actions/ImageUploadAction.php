<?php

namespace App\Media\Actions;

use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Single Responsibility: Handle file upload validation and initial storage
 */
class ImageUploadAction
{
    public function execute(array $files, array $config): ImageUploadResult
    {
        try {
            DB::beginTransaction();

            $results = collect();
            $errors = collect();

            foreach ($files as $file) {
                try {
                    $this->validateFile($file, $config);
                    $productImage = $this->storeFile($file, $config);
                    $results->push($productImage);
                } catch (\Exception $e) {
                    $errors->push([
                        'file' => $file->getClientOriginalName(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            Log::info('Image upload completed', [
                'uploaded' => $results->count(),
                'errors' => $errors->count(),
            ]);

            return new ImageUploadResult(
                images: $results,
                errors: $errors,
                config: $config
            );

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function validateFile(UploadedFile $file, array $config): void
    {
        // File size validation
        if ($file->getSize() > ($config['maxSize'] * 1024)) {
            throw new \InvalidArgumentException("File too large (max {$config['maxSize']}KB)");
        }

        // File type validation
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $config['acceptTypes'])) {
            throw new \InvalidArgumentException('Invalid file type (allowed: ' . implode(', ', $config['acceptTypes']) . ')');
        }

        // Image validation
        $imageInfo = getimagesize($file->getRealPath());
        if (!$imageInfo) {
            throw new \InvalidArgumentException('Invalid image file');
        }

        // Minimum dimensions
        if ($imageInfo[0] < 300 || $imageInfo[1] < 300) {
            throw new \InvalidArgumentException('Image too small (minimum 300x300px)');
        }
    }

    protected function storeFile(UploadedFile $file, array $config): ProductImage
    {
        // Store to temporary location
        $path = $file->store('product-images/temp', 'public');

        // Get image dimensions
        $imageInfo = getimagesize($file->getRealPath());

        // Create ProductImage record
        return ProductImage::create([
            'product_id' => $config['modelType'] === 'product' ? $config['modelId'] : null,
            'variant_id' => $config['modelType'] === 'variant' ? $config['modelId'] : null,
            'image_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'image_type' => $config['imageType'],
            'sort_order' => $this->getNextSortOrder($config),
            'processing_status' => ProductImage::PROCESSING_PENDING,
            'storage_disk' => 'public',
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'dimensions' => [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1]
            ],
            'metadata' => [
                'uploader_session_id' => Str::uuid(),
                'uploaded_at' => now()->toISOString(),
                'user_id' => auth()->id(),
            ],
        ]);
    }

    protected function getNextSortOrder(array $config): int
    {
        if (!$config['modelId'] || !$config['modelType']) {
            return 1;
        }

        $query = ProductImage::where('image_type', $config['imageType']);

        if ($config['modelType'] === 'product') {
            $query->where('product_id', $config['modelId']);
        } elseif ($config['modelType'] === 'variant') {
            $query->where('variant_id', $config['modelId']);
        }

        return ($query->max('sort_order') ?? 0) + 1;
    }
}

/**
 * Value Object for upload results
 */
class ImageUploadResult
{
    public function __construct(
        public readonly Collection $images,
        public readonly Collection $errors,
        public readonly array $config
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors->isNotEmpty();
    }

    public function getSuccessCount(): int
    {
        return $this->images->count();
    }

    public function getErrorCount(): int
    {
        return $this->errors->count();
    }

    public function getMessage(): string
    {
        $success = $this->getSuccessCount();
        $errors = $this->getErrorCount();

        if ($errors === 0) {
            return "Successfully uploaded {$success} images";
        }

        return "Uploaded {$success} images with {$errors} errors";
    }
}