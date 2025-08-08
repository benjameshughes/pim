<?php

namespace App\Livewire\Components;

use App\Jobs\ProcessImageToR2;
use App\Models\ProductImage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class SimpleImageUploader extends Component
{
    use WithFileUploads;

    // Basic properties
    public $files = [];

    public $imageType = 'general';

    public $maxFiles = 10;

    public $maxSize = 5120; // KB

    public $acceptTypes = ['jpg', 'jpeg', 'png', 'webp'];

    public $multiple = true;

    public $showUploadArea = true;

    public $showExistingImages = true;

    public $processImmediately = true;

    public $showPreview = true;

    public $allowReorder = false;

    public $uploadText = 'Upload files';

    // Model binding
    public $modelType = null;

    public $modelId = null;

    // State properties
    public $uploading = false;

    public $acceptTypesString = '';

    public $sessionId;

    public function mount()
    {
        $this->acceptTypesString = implode(',', array_map(fn ($type) => ".{$type}", $this->acceptTypes));
        $this->sessionId = (string) Str::uuid();

        // Validate image type
        if (! in_array($this->imageType, ['main', 'detail', 'swatch', 'lifestyle', 'installation', 'general'])) {
            $this->imageType = 'general';
        }
    }

    // Computed property for upload capability
    public function getCanUploadProperty()
    {
        return ! empty($this->files) && count($this->files) <= $this->maxFiles && ! $this->uploading;
    }

    public function removeFile($index)
    {
        if (isset($this->files[$index])) {
            unset($this->files[$index]);
            $this->files = array_values($this->files); // Reindex array
        }
    }

    public function uploadFiles()
    {
        if (! $this->canUpload) {
            return;
        }

        $this->uploading = true;

        try {
            // Enhanced validation including image dimensions
            $this->validate([
                'files.*' => [
                    'required',
                    'file',
                    'image',
                    'mimes:'.implode(',', $this->acceptTypes),
                    'max:'.$this->maxSize,
                    'dimensions:min_width=300,min_height=300',
                ],
            ]);

            $uploadedCount = 0;
            $processedCount = 0;

            foreach ($this->files as $file) {
                // Store file to temporary location
                $path = $file->store('product-images/temp', 'public');

                // Get image dimensions for metadata
                $dimensions = null;
                try {
                    $imageSize = getimagesize($file->getRealPath());
                    if ($imageSize) {
                        $dimensions = ['width' => $imageSize[0], 'height' => $imageSize[1]];
                    }
                } catch (\Exception $e) {
                    // Continue without dimensions if we can't get them
                }

                // Create ProductImage record
                $productImage = ProductImage::create([
                    'product_id' => $this->modelType === 'product' ? $this->modelId : null,
                    'variant_id' => $this->modelType === 'variant' ? $this->modelId : null,
                    'image_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'image_type' => $this->imageType,
                    'sort_order' => $this->getNextSortOrder(),
                    'processing_status' => ProductImage::PROCESSING_PENDING,
                    'storage_disk' => 'public',
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'dimensions' => $dimensions,
                    'metadata' => [
                        'uploader_session_id' => $this->sessionId,
                        'uploaded_at' => now()->toISOString(),
                        'uploader_component' => 'SimpleImageUploader',
                    ],
                ]);

                // Dispatch processing job if enabled
                if ($this->processImmediately) {
                    ProcessImageToR2::dispatch($productImage);
                    $processedCount++;
                }

                $uploadedCount++;
            }

            // Clear files after successful upload
            $this->files = [];

            // Create success message with job information
            $message = "Uploaded {$uploadedCount} images successfully!";
            if ($processedCount > 0) {
                $message .= " {$processedCount} images queued for background processing.";
            }

            session()->flash('message', $message);

            // Dispatch events for parent components
            $this->dispatch('images-uploaded', [
                'count' => $uploadedCount,
                'processed' => $processedCount,
                'model_type' => $this->modelType,
                'model_id' => $this->modelId,
                'session_id' => $this->sessionId,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('error', 'Validation failed: '.implode(', ', $e->validator->errors()->all()));
        } catch (\Exception $e) {
            session()->flash('error', 'Upload failed: '.$e->getMessage());
        } finally {
            $this->uploading = false;
        }
    }

    private function getNextSortOrder(): int
    {
        $maxSort = 0;

        if ($this->modelType === 'product' && $this->modelId) {
            $maxSort = ProductImage::where('product_id', $this->modelId)
                ->where('image_type', $this->imageType)
                ->max('sort_order') ?? 0;
        } elseif ($this->modelType === 'variant' && $this->modelId) {
            $maxSort = ProductImage::where('variant_id', $this->modelId)
                ->where('image_type', $this->imageType)
                ->max('sort_order') ?? 0;
        }

        return $maxSort + 1;
    }

    public function getExistingImagesProperty()
    {
        $query = ProductImage::query();

        if ($this->modelType === 'product' && $this->modelId) {
            $query->forProduct($this->modelId);
        } elseif ($this->modelType === 'variant' && $this->modelId) {
            $query->forVariant($this->modelId);
        } elseif (! $this->modelType) {
            // Show unassigned images
            $query->whereNull('product_id')->whereNull('variant_id');
        }

        return $query->byType($this->imageType)->ordered()->get();
    }

    public function render()
    {
        return view('livewire.components.simple-image-uploader');
    }
}
