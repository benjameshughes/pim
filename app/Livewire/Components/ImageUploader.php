<?php

namespace App\Livewire\Components;

use App\Jobs\ProcessImageToR2;
use App\Models\ProductImage;
use App\Services\ImageProcessingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ImageUploader extends Component
{
    use WithFileUploads;

    // Configuration Props
    public ?string $modelType = null;

    public ?int $modelId = null;

    public string $imageType = 'main';

    public bool $multiple = true;

    public int $maxFiles = 10;

    public int $maxSize = 10240; // KB (10MB)

    public array $acceptTypes = ['jpg', 'jpeg', 'png', 'webp'];

    public bool $processImmediately = true;

    public bool $showPreview = true;

    public bool $allowReorder = true;

    public string $uploadText = 'Drag & drop images here or click to browse';

    // State Properties
    public array $files = [];

    public bool $isUploading = false;

    public bool $isDragOver = false;

    public array $uploadProgress = [];

    public array $validationErrors = [];

    public Collection $existingImages;

    public string $sessionId;

    // UI State
    public bool $showUploadArea = true;

    public bool $showExistingImages = true;

    public string $viewMode = 'grid'; // 'grid' or 'list'

    public function mount(): void
    {
        $this->sessionId = Str::uuid();
        $this->loadExistingImages();
        $this->validateConfiguration();
    }

    private function validateConfiguration(): void
    {
        if (! in_array($this->modelType, ['product', 'variant', null])) {
            throw new \InvalidArgumentException('modelType must be "product", "variant", or null');
        }

        if (! in_array($this->imageType, ['main', 'detail', 'swatch', 'lifestyle', 'installation'])) {
            throw new \InvalidArgumentException('Invalid image type provided');
        }
    }

    private function loadExistingImages(): void
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

        $this->existingImages = $query->byType($this->imageType)->ordered()->get();
    }

    public function updatedFiles(): void
    {
        $this->validateFiles();
    }

    private function validateFiles(): void
    {
        $this->validationErrors = [];

        if (count($this->files) > $this->maxFiles) {
            $this->validationErrors[] = "Maximum {$this->maxFiles} files allowed.";
            $this->files = array_slice($this->files, 0, $this->maxFiles);
        }

        foreach ($this->files as $index => $file) {
            $errors = [];

            // File size validation
            if ($file->getSize() > ($this->maxSize * 1024)) {
                $errors[] = "File too large (max {$this->maxSize}KB)";
            }

            // File type validation
            $extension = strtolower($file->getClientOriginalExtension());
            if (! in_array($extension, $this->acceptTypes)) {
                $errors[] = 'Invalid file type (allowed: '.implode(', ', $this->acceptTypes).')';
            }

            // Image validation
            try {
                $imageSize = getimagesize($file->getRealPath());
                if (! $imageSize) {
                    $errors[] = 'Invalid image file';
                } else {
                    // Minimum dimensions check
                    if ($imageSize[0] < 300 || $imageSize[1] < 300) {
                        $errors[] = 'Image too small (minimum 300x300px)';
                    }
                }
            } catch (\Exception $e) {
                $errors[] = 'Could not process image';
            }

            if (! empty($errors)) {
                $this->validationErrors["file_{$index}"] = $errors;
            }
        }
    }

    public function upload(): void
    {
        if (empty($this->files) || ! empty($this->validationErrors)) {
            session()->flash('error', 'Please fix validation errors before uploading.');

            return;
        }

        $this->isUploading = true;
        $this->uploadProgress = [];

        try {
            $uploadedCount = 0;
            $processedCount = 0;
            $imageProcessingService = app(ImageProcessingService::class);

            foreach ($this->files as $index => $file) {
                $this->uploadProgress["file_{$index}"] = [
                    'status' => 'uploading',
                    'progress' => 25,
                    'filename' => $file->getClientOriginalName(),
                ];

                // Store to temporary disk
                $path = $file->store('product-images/temp', 'public');

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
                    'metadata' => [
                        'uploader_session_id' => $this->sessionId,
                        'uploaded_at' => now()->toISOString(),
                    ],
                ]);

                $this->uploadProgress["file_{$index}"] = [
                    'status' => 'processing',
                    'progress' => 50,
                    'filename' => $file->getClientOriginalName(),
                    'image_id' => $productImage->id,
                ];

                // Process immediately (synchronously) instead of queuing
                if ($this->processImmediately) {
                    $success = $imageProcessingService->processImage($productImage);

                    if ($success) {
                        $this->uploadProgress["file_{$index}"] = [
                            'status' => 'completed',
                            'progress' => 100,
                            'filename' => $file->getClientOriginalName(),
                            'image_id' => $productImage->id,
                        ];
                        $processedCount++;
                    } else {
                        $this->uploadProgress["file_{$index}"] = [
                            'status' => 'failed',
                            'progress' => 100,
                            'filename' => $file->getClientOriginalName(),
                            'image_id' => $productImage->id,
                        ];
                    }
                } else {
                    // Fall back to queue if processImmediately is false
                    ProcessImageToR2::dispatch($productImage);
                    $this->uploadProgress["file_{$index}"] = [
                        'status' => 'queued',
                        'progress' => 100,
                        'filename' => $file->getClientOriginalName(),
                        'image_id' => $productImage->id,
                    ];
                    $processedCount++;
                }

                $uploadedCount++;
            }

            // Clear files and refresh existing images
            $this->files = [];
            $this->loadExistingImages();

            $message = "Uploaded {$uploadedCount} images successfully!";
            if ($processedCount > 0 && $this->processImmediately) {
                $message .= ' All images processed and ready to use.';
            } elseif ($processedCount > 0) {
                $message .= " {$processedCount} images queued for processing.";
            }

            session()->flash('success', $message);

            // Dispatch event for parent components
            $this->dispatch('images-uploaded', [
                'count' => $uploadedCount,
                'processed' => $processedCount,
                'model_type' => $this->modelType,
                'model_id' => $this->modelId,
            ]);

            // Also dispatch a general refresh event for all components
            $this->dispatch('$refresh');

        } catch (\Exception $e) {
            session()->flash('error', 'Upload failed: '.$e->getMessage());
        } finally {
            $this->isUploading = false;
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

    public function removeFile(int $index): void
    {
        if (isset($this->files[$index])) {
            unset($this->files[$index]);
            unset($this->uploadProgress["file_{$index}"]);
            unset($this->validationErrors["file_{$index}"]);
            $this->files = array_values($this->files); // Re-index
        }
    }

    public function clearFiles(): void
    {
        $this->files = [];
        $this->uploadProgress = [];
        $this->validationErrors = [];
    }

    public function deleteExistingImage(int $imageId): void
    {
        $image = ProductImage::find($imageId);
        if ($image) {
            $image->delete();
            $this->loadExistingImages();
            session()->flash('success', 'Image deleted successfully.');

            $this->dispatch('image-deleted', ['image_id' => $imageId]);
        }
    }

    public function updateImageSortOrder(array $orderedIds): void
    {
        if (! $this->allowReorder) {
            return;
        }

        foreach ($orderedIds as $index => $imageId) {
            ProductImage::where('id', $imageId)->update(['sort_order' => $index + 1]);
        }

        $this->loadExistingImages();
        $this->dispatch('images-reordered', ['ordered_ids' => $orderedIds]);
    }

    public function toggleUploadArea(): void
    {
        $this->showUploadArea = ! $this->showUploadArea;
    }

    public function toggleViewMode(): void
    {
        $this->viewMode = $this->viewMode === 'grid' ? 'list' : 'grid';
    }

    #[On('image-processed')]
    public function onImageProcessed($imageData): void
    {
        if (isset($imageData['id'])) {
            // Update progress for this specific image
            $this->updateImageProgress($imageData['id'], 'processed');
            $this->loadExistingImages();

            // Update any progress indicators
            foreach ($this->uploadProgress as $key => $progress) {
                if (($progress['image_id'] ?? null) === $imageData['id']) {
                    $this->uploadProgress[$key]['status'] = 'processed';
                    break;
                }
            }
        }
    }

    #[On('image-processing-failed')]
    public function onImageProcessingFailed($imageData): void
    {
        if (isset($imageData['id'])) {
            $this->updateImageProgress($imageData['id'], 'failed');
            $this->loadExistingImages();

            // Update progress indicators
            foreach ($this->uploadProgress as $key => $progress) {
                if (($progress['image_id'] ?? null) === $imageData['id']) {
                    $this->uploadProgress[$key]['status'] = 'failed';
                    break;
                }
            }
        }
    }

    private function updateImageProgress(int $imageId, string $status): void
    {
        // Update any relevant UI state based on processing status
        $this->dispatch('$refresh');
    }

    public function getCanUploadProperty(): bool
    {
        return ! empty($this->files) && empty($this->validationErrors) && ! $this->isUploading;
    }

    public function getImageValidationRules(): array
    {
        $maxSizeKb = $this->maxSize;
        $acceptTypes = implode(',', $this->acceptTypes);

        return [
            'files.*' => [
                'image',
                'max:'.$maxSizeKb,
                'mimes:'.$acceptTypes,
                'dimensions:min_width=300,min_height=300',
            ],
        ];
    }

    public function render()
    {
        return view('livewire.components.image-uploader', [
            'hasErrors' => ! empty($this->validationErrors),
            'canUpload' => ! empty($this->files) && empty($this->validationErrors) && ! $this->isUploading,
            'acceptTypesString' => implode(',', array_map(fn ($type) => "image/{$type}", $this->acceptTypes)),
        ]);
    }
}
