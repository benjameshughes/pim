<?php

namespace App\Livewire\Media;

use App\Media\ImageManager;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Simple, focused Livewire component for image uploads
 * Uses ImageManager builder for all logic
 */
class ImageUploader extends Component
{
    use WithFileUploads;

    // Configuration (passed from parent or set via wire:model)
    public ?string $modelType = null;
    public ?int $modelId = null; 
    public string $imageType = 'main';
    public bool $multiple = true;
    public string $maxSize = '10MB';
    public array $acceptTypes = ['jpg', 'jpeg', 'png', 'webp'];
    public bool $createThumbnails = true;
    public string $storageDisk = 'images';

    // State
    public array $files = [];
    public bool $isUploading = false;
    public array $uploadResults = [];
    public ?string $errorMessage = null;

    public function mount($model = null, ?string $modelType = null)
    {
        if ($model instanceof Model) {
            $this->modelType = $this->getModelType($model);
            $this->modelId = $model->id;
        } elseif (is_numeric($model) && $modelType) {
            $this->modelType = $modelType;
            $this->modelId = (int) $model;
        } elseif ($modelType) {
            $this->modelType = $modelType;
        }
    }

    public function updatedFiles()
    {
        $this->errorMessage = null;
    }

    public function upload()
    {
        if (empty($this->files)) {
            $this->errorMessage = 'Please select files to upload';
            return;
        }

        $this->isUploading = true;
        $this->errorMessage = null;
        $this->uploadResults = [];

        try {
            // Use ImageManager builder for upload
            $manager = ImageManager::make()
                ->type($this->imageType)
                ->maxSize($this->maxSize)
                ->acceptTypes($this->acceptTypes)
                ->createThumbnails($this->createThumbnails)
                ->uploadTo($this->storageDisk)
                ->processImmediately();

            if ($this->modelType && $this->modelId) {
                $manager = $manager->for($this->modelType, $this->modelId);
            }

            // Execute the upload
            $uploadResult = $manager->upload($this->files);
            
            // If upload successful, also process and store
            if (!$uploadResult->hasErrors()) {
                $processResult = $manager->process($uploadResult->images);
                $storeResult = $manager->store($processResult->processed);
                
                $this->uploadResults = [
                    'uploaded' => $uploadResult->getSuccessCount(),
                    'processed' => $processResult->getProcessedCount(),
                    'stored' => $storeResult->getStoredCount(),
                    'message' => 'Images uploaded successfully!'
                ];
            } else {
                $this->uploadResults = [
                    'uploaded' => $uploadResult->getSuccessCount(),
                    'errors' => $uploadResult->getErrorCount(),
                    'message' => $uploadResult->getMessage()
                ];
            }

            // Clear files and notify parent
            $this->files = [];
            $this->dispatch('images-uploaded', $this->uploadResults);

        } catch (\Exception $e) {
            $this->errorMessage = 'Upload failed: ' . $e->getMessage();
        } finally {
            $this->isUploading = false;
        }
    }

    public function removeFile(int $index)
    {
        if (isset($this->files[$index])) {
            unset($this->files[$index]);
            $this->files = array_values($this->files);
        }
    }

    public function clearFiles()
    {
        $this->files = [];
        $this->uploadResults = [];
        $this->errorMessage = null;
    }

    #[On('refresh-uploader')]
    public function refresh()
    {
        // Allow parent components to trigger refresh
        $this->clearFiles();
    }

    protected function getModelType(Model $model): string
    {
        return match (get_class($model)) {
            \App\Models\Product::class => 'product',
            \App\Models\ProductVariant::class => 'variant',
            default => throw new \InvalidArgumentException('Unsupported model type')
        };
    }

    public function render()
    {
        return view('livewire.media.image-uploader');
    }
}