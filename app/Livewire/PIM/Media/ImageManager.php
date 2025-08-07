<?php

namespace App\Livewire\Pim\Media;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use App\Jobs\ProcessImageToR2;
use App\Services\ImageProcessingService;
use App\Livewire\Concerns\HasImageUpload;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class ImageManager extends Component
{
    use WithFileUploads, WithPagination, HasImageUpload;

    public $search = '';
    public $filterType = ''; // 'product', 'variant', 'unassigned', 'processing', 'failed'
    public $selectedImages = [];
    public $bulkEditMode = false;
    
    // Upload properties for the integrated uploader
    public $defaultImageType = 'main'; // 'main', 'detail', 'swatch', 'lifestyle', 'installation'
    public $showImageUploader = true;
    
    // Assignment properties
    public $assignmentMode = false;
    public $selectedProductId = '';
    public $selectedVariantId = '';
    
    // Processing properties
    public $processingStats = [];
    public $showProcessingStats = false;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterType()
    {
        $this->resetPage();
    }

    public function toggleBulkEdit()
    {
        $this->bulkEditMode = !$this->bulkEditMode;
        $this->selectedImages = [];
    }

    public function toggleAssignmentMode()
    {
        $this->assignmentMode = !$this->assignmentMode;
        $this->selectedProductId = '';
        $this->selectedVariantId = '';
    }

    public function selectImageForAssignment($imageId)
    {
        $this->selectedImages = [$imageId];
        $this->assignmentMode = true;
        $this->selectedProductId = '';
        $this->selectedVariantId = '';
    }

    public function updatedSelectedProductId()
    {
        $this->selectedVariantId = '';
    }

    public function toggleImageUploader()
    {
        $this->showImageUploader = !$this->showImageUploader;
    }

    /**
     * Custom handler for image uploads - refresh stats and filters
     */
    public function handleImagesUploaded($data)
    {
        // Reset pagination to show newly uploaded images
        $this->resetPage();
        
        // Update filter if it makes sense
        if ($this->filterType === 'unassigned' && !empty($data['model_id'])) {
            // Clear filter to show newly assigned images
            $this->filterType = '';
        }
        
        $count = $data['count'] ?? 0;
        $processed = $data['processed'] ?? 0;
        
        $message = "Uploaded {$count} images successfully!";
        if ($processed > 0) {
            $message .= " {$processed} images queued for processing.";
        }
        
        session()->flash('success', $message);
    }

    /**
     * Custom handler for image deletion - refresh stats
     */
    public function handleImageDeleted($data)
    {
        session()->flash('success', 'Image deleted successfully.');
    }

    public function assignImagesToProduct()
    {
        if (empty($this->selectedImages) || !$this->selectedProductId) {
            session()->flash('error', 'Please select images and a product.');
            return;
        }

        $assignedCount = 0;
        foreach ($this->selectedImages as $imageId) {
            $image = ProductImage::find($imageId);
            if ($image && !$image->product_id && !$image->variant_id) {
                $image->update([
                    'product_id' => $this->selectedProductId,
                    'sort_order' => ProductImage::where('product_id', $this->selectedProductId)->max('sort_order') + 1,
                ]);
                $assignedCount++;
            }
        }
        
        $this->selectedImages = [];
        session()->flash('success', "Assigned {$assignedCount} images to product successfully!");
    }

    public function assignImagesToVariant()
    {
        if (empty($this->selectedImages) || !$this->selectedVariantId) {
            session()->flash('error', 'Please select images and a variant.');
            return;
        }

        $assignedCount = 0;
        foreach ($this->selectedImages as $imageId) {
            $image = ProductImage::find($imageId);
            if ($image && !$image->product_id && !$image->variant_id) {
                $image->update([
                    'variant_id' => $this->selectedVariantId,
                    'sort_order' => ProductImage::where('variant_id', $this->selectedVariantId)->max('sort_order') + 1,
                ]);
                $assignedCount++;
            }
        }
        
        $this->selectedImages = [];
        session()->flash('success', "Assigned {$assignedCount} images to variant successfully!");
    }

    public function removeImageFromProduct($imageId)
    {
        $image = ProductImage::findOrFail($imageId);
        $image->update([
            'product_id' => null,
            'sort_order' => 0,
        ]);
        
        session()->flash('success', 'Image removed from product.');
    }

    public function removeImageFromVariant($imageId)
    {
        $image = ProductImage::findOrFail($imageId);
        $image->update([
            'variant_id' => null,
            'sort_order' => 0,
        ]);
        
        session()->flash('success', 'Image removed from variant.');
    }

    public function deleteImages()
    {
        if (empty($this->selectedImages)) {
            session()->flash('error', 'Please select images to delete.');
            return;
        }

        $deleteCount = 0;
        foreach ($this->selectedImages as $imageId) {
            $image = ProductImage::find($imageId);
            if ($image) {
                $image->delete(); // This will also delete the file thanks to our model override
                $deleteCount++;
            }
        }

        $this->selectedImages = [];
        session()->flash('success', "Deleted {$deleteCount} images successfully!");
    }

    public function selectAllImages()
    {
        $images = $this->getImagesQuery()->get();
        $this->selectedImages = $images->pluck('id')->toArray();
    }

    public function deselectAllImages()
    {
        $this->selectedImages = [];
    }

    public function processSelectedImages()
    {
        if (empty($this->selectedImages)) {
            session()->flash('error', 'Please select images to process.');
            return;
        }

        $processedCount = 0;
        foreach ($this->selectedImages as $imageId) {
            $image = ProductImage::find($imageId);
            if ($image && !$image->isProcessed()) {
                ProcessImageToR2::dispatch($image);
                $processedCount++;
            }
        }

        $this->selectedImages = [];
        session()->flash('success', "Queued {$processedCount} images for processing!");
    }

    public function reprocessFailedImages()
    {
        $failedImages = ProductImage::where('processing_status', ProductImage::PROCESSING_FAILED)->get();
        
        $processedCount = 0;
        foreach ($failedImages as $image) {
            // Reset status and requeue
            $image->update(['processing_status' => ProductImage::PROCESSING_PENDING]);
            ProcessImageToR2::dispatch($image);
            $processedCount++;
        }

        session()->flash('success', "Requeued {$processedCount} failed images for processing!");
    }

    public function toggleProcessingStats()
    {
        $this->showProcessingStats = !$this->showProcessingStats;
        
        if ($this->showProcessingStats) {
            $this->loadProcessingStats();
        }
    }

    public function loadProcessingStats()
    {
        $service = new ImageProcessingService();
        $this->processingStats = $service->getProcessingStats();
    }

    /**
     * Custom handler for image processing completion
     */
    public function handleImageProcessed($imageData)
    {
        // Update processing stats if they're visible
        if ($this->showProcessingStats) {
            $this->loadProcessingStats();
        }
    }

    /**
     * Custom handler for image processing failure
     */
    public function handleImageProcessingFailed($imageData)
    {
        // Update processing stats if they're visible
        if ($this->showProcessingStats) {
            $this->loadProcessingStats();
        }
    }

    /**
     * Get image uploader configuration for unassigned uploads
     */
    protected function getImageUploaderConfig(): array
    {
        return [
            'modelType' => null, // Unassigned images
            'modelId' => null,
            'imageType' => $this->defaultImageType,
            'multiple' => true,
            'maxFiles' => 20,
            'maxSize' => 10240, // 10MB
            'acceptTypes' => ['jpg', 'jpeg', 'png', 'webp'],
            'processImmediately' => true,
            'showPreview' => true,
            'allowReorder' => false, // Not applicable for unassigned
            'showExistingImages' => false, // We show these separately
            'uploadText' => 'Drag & drop images here or click to browse'
        ];
    }

    private function getImagesQuery()
    {
        $query = ProductImage::query()->with(['product', 'variant.product']);

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('image_path', 'like', '%' . $this->search . '%')
                  ->orWhere('alt_text', 'like', '%' . $this->search . '%')
                  ->orWhereHas('product', function ($productQuery) {
                      $productQuery->where('name', 'like', '%' . $this->search . '%');
                  })
                  ->orWhereHas('variant.product', function ($productQuery) {
                      $productQuery->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        // Apply type filter
        if ($this->filterType === 'product') {
            $query->whereNotNull('product_id')->whereNull('variant_id');
        } elseif ($this->filterType === 'variant') {
            $query->whereNotNull('variant_id')->whereNull('product_id');
        } elseif ($this->filterType === 'unassigned') {
            $query->whereNull('product_id')->whereNull('variant_id');
        } elseif ($this->filterType === 'processing') {
            $query->whereIn('processing_status', [ProductImage::PROCESSING_PENDING, ProductImage::PROCESSING_IN_PROGRESS]);
        } elseif ($this->filterType === 'failed') {
            $query->where('processing_status', ProductImage::PROCESSING_FAILED);
        }

        return $query->ordered();
    }

    public function render()
    {
        $images = $this->getImagesQuery()->paginate(24);

        // Calculate statistics
        $stats = [
            'total_images' => ProductImage::count(),
            'assigned_to_products' => ProductImage::whereNotNull('product_id')->whereNull('variant_id')->count(),
            'assigned_to_variants' => ProductImage::whereNotNull('variant_id')->whereNull('product_id')->count(),
            'unassigned' => ProductImage::whereNull('product_id')->whereNull('variant_id')->count(),
            'pending_processing' => ProductImage::where('processing_status', ProductImage::PROCESSING_PENDING)->count(),
            'currently_processing' => ProductImage::where('processing_status', ProductImage::PROCESSING_IN_PROGRESS)->count(),
            'processing_completed' => ProductImage::where('processing_status', ProductImage::PROCESSING_COMPLETED)->count(),
            'processing_failed' => ProductImage::where('processing_status', ProductImage::PROCESSING_FAILED)->count(),
            'selected_count' => count($this->selectedImages),
        ];

        return view('livewire.pim.media.image-manager', [
            'images' => $images,
            'stats' => $stats,
            'products' => Product::orderBy('name')->get(),
            'variants' => $this->selectedProductId ? 
                ProductVariant::where('product_id', $this->selectedProductId)->with('product')->get() : 
                collect(),
        ]);
    }
}
