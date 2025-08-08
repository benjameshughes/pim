<?php

namespace App\Livewire\Media;

use App\Media\ImageManager;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Simple, focused Livewire component for image galleries
 * Uses ImageManager builder for all logic
 */
class ImageGallery extends Component
{
    use WithPagination;

    // Configuration
    public ?string $modelType = null;
    public ?int $modelId = null;
    public ?string $imageType = null;
    public string $layout = 'grid'; // 'grid' or 'list'
    public int $perPage = 24;
    public bool $allowReorder = true;
    public bool $allowDelete = true;
    public bool $showUploader = false;

    // State
    public array $selectedImages = [];
    public bool $bulkMode = false;
    public string $search = '';
    public array $filters = [];

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

    #[Computed]
    public function images(): Collection
    {
        $query = ProductImage::query()->with(['product', 'variant']);

        // Filter by model
        if ($this->modelType === 'product' && $this->modelId) {
            $query->where('product_id', $this->modelId)->whereNull('variant_id');
        } elseif ($this->modelType === 'variant' && $this->modelId) {
            $query->where('variant_id', $this->modelId)->whereNull('product_id');
        } elseif (!$this->modelType) {
            // Show unassigned images
            $query->whereNull('product_id')->whereNull('variant_id');
        }

        // Filter by image type
        if ($this->imageType) {
            $query->where('image_type', $this->imageType);
        }

        // Search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('original_filename', 'like', '%' . $this->search . '%')
                  ->orWhere('alt_text', 'like', '%' . $this->search . '%');
            });
        }

        // Status filters
        foreach ($this->filters as $filter) {
            match ($filter) {
                'pending' => $query->where('processing_status', ProductImage::PROCESSING_PENDING),
                'failed' => $query->where('processing_status', ProductImage::PROCESSING_FAILED),
                'completed' => $query->where('processing_status', ProductImage::PROCESSING_COMPLETED),
                default => null
            };
        }

        return $query->orderBy('sort_order')->orderBy('created_at', 'desc')->get();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function deleteImage(int $imageId)
    {
        $image = ProductImage::find($imageId);
        if ($image) {
            $image->delete();
            $this->dispatch('image-deleted', ['image_id' => $imageId]);
        }
    }

    public function toggleBulkMode()
    {
        $this->bulkMode = !$this->bulkMode;
        $this->selectedImages = [];
    }

    public function selectAll()
    {
        $this->selectedImages = $this->images->pluck('id')->toArray();
    }

    public function deselectAll()
    {
        $this->selectedImages = [];
    }

    public function bulkDelete()
    {
        if (empty($this->selectedImages)) {
            return;
        }

        ProductImage::whereIn('id', $this->selectedImages)->delete();
        
        $this->selectedImages = [];
        $this->dispatch('images-deleted', ['count' => count($this->selectedImages)]);
    }

    public function bulkAssign(string $modelType, int $modelId)
    {
        if (empty($this->selectedImages)) {
            return;
        }

        try {
            // Use ImageManager for bulk assignment
            $result = ImageManager::bulk()
                ->filter(['selected' => $this->selectedImages])
                ->assignTo($modelType, $modelId)
                ->execute();

            $this->selectedImages = [];
            $this->dispatch('images-assigned', [
                'message' => $result->getMessage(),
                'success' => !$result->hasFailures()
            ]);

        } catch (\Exception $e) {
            $this->dispatch('bulk-operation-failed', ['error' => $e->getMessage()]);
        }
    }

    public function updateSortOrder(array $orderedIds)
    {
        if (!$this->allowReorder) {
            return;
        }

        foreach ($orderedIds as $index => $imageId) {
            ProductImage::where('id', $imageId)->update(['sort_order' => $index + 1]);
        }

        $this->dispatch('images-reordered');
    }

    public function toggleLayout()
    {
        $this->layout = $this->layout === 'grid' ? 'list' : 'grid';
    }

    public function toggleUploader()
    {
        $this->showUploader = !$this->showUploader;
    }

    public function addFilter(string $filter)
    {
        if (!in_array($filter, $this->filters)) {
            $this->filters[] = $filter;
        }
    }

    public function removeFilter(string $filter)
    {
        $this->filters = array_values(array_diff($this->filters, [$filter]));
    }

    #[On('images-uploaded')]
    public function onImagesUploaded($data)
    {
        // Refresh gallery when new images are uploaded
        $this->resetPage();
        unset($this->images); // Clear computed property cache
    }

    #[On('image-processed')]
    public function onImageProcessed($data)
    {
        // Refresh gallery when image processing completes
        unset($this->images);
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
        return view('livewire.media.image-gallery', [
            'images' => $this->images,
            'stats' => [
                'total' => $this->images->count(),
                'selected' => count($this->selectedImages),
                'pending' => $this->images->where('processing_status', ProductImage::PROCESSING_PENDING)->count(),
                'failed' => $this->images->where('processing_status', ProductImage::PROCESSING_FAILED)->count(),
            ]
        ]);
    }
}