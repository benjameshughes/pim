<?php

namespace App\Livewire\Media;

use App\Media\ImageManager;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Livewire\Component;

/**
 * Main media library interface combining uploader and gallery
 * Uses ImageManager builder for all operations
 */
class MediaLibrary extends Component
{
    // State
    public string $activeTab = 'library';
    public string $viewMode = 'grid';
    public bool $bulkMode = false;
    public array $selectedImages = [];
    
    // Filters
    public string $search = '';
    public string $typeFilter = '';
    public string $statusFilter = '';
    public string $assignmentFilter = 'all';
    
    // Assignment mode
    public bool $assignmentMode = false;
    public string $selectedProductId = '';
    public string $selectedVariantId = '';

    // Stats
    public array $stats = [];

    public function mount()
    {
        $this->loadStats();
    }

    public function updatedActiveTab()
    {
        // Reset filters when switching tabs
        $this->resetFilters();
    }

    public function updatedSelectedProductId()
    {
        $this->selectedVariantId = '';
    }

    public function toggleBulkMode()
    {
        $this->bulkMode = !$this->bulkMode;
        $this->selectedImages = [];
    }

    public function toggleAssignmentMode()
    {
        $this->assignmentMode = !$this->assignmentMode;
        $this->selectedProductId = '';
        $this->selectedVariantId = '';
    }

    public function bulkAssignToProduct()
    {
        if (empty($this->selectedImages) || !$this->selectedProductId) {
            session()->flash('error', 'Please select images and a product.');
            return;
        }

        try {
            $result = ImageManager::bulk()
                ->filter(['selected' => $this->selectedImages])
                ->assignTo('product', (int) $this->selectedProductId)
                ->execute();

            $this->selectedImages = [];
            $this->loadStats();
            
            session()->flash('success', $result->getMessage());

        } catch (\Exception $e) {
            session()->flash('error', 'Assignment failed: ' . $e->getMessage());
        }
    }

    public function bulkAssignToVariant()
    {
        if (empty($this->selectedImages) || !$this->selectedVariantId) {
            session()->flash('error', 'Please select images and a variant.');
            return;
        }

        try {
            $result = ImageManager::bulk()
                ->filter(['selected' => $this->selectedImages])
                ->assignTo('variant', (int) $this->selectedVariantId)
                ->execute();

            $this->selectedImages = [];
            $this->loadStats();
            
            session()->flash('success', $result->getMessage());

        } catch (\Exception $e) {
            session()->flash('error', 'Assignment failed: ' . $e->getMessage());
        }
    }

    public function bulkDelete()
    {
        if (empty($this->selectedImages)) {
            session()->flash('error', 'Please select images to delete.');
            return;
        }

        try {
            $deletedCount = 0;
            foreach ($this->selectedImages as $imageId) {
                $image = ProductImage::find($imageId);
                if ($image) {
                    $image->delete();
                    $deletedCount++;
                }
            }

            $this->selectedImages = [];
            $this->loadStats();
            
            session()->flash('success', "Deleted {$deletedCount} images successfully!");

        } catch (\Exception $e) {
            session()->flash('error', 'Deletion failed: ' . $e->getMessage());
        }
    }

    public function bulkProcess()
    {
        if (empty($this->selectedImages)) {
            session()->flash('error', 'Please select images to process.');
            return;
        }

        try {
            $images = ProductImage::whereIn('id', $this->selectedImages)
                ->where('processing_status', '!=', ProductImage::PROCESSING_COMPLETED)
                ->get();

            $result = ImageManager::make()
                ->processImmediately(false) // Use queue for bulk
                ->process($images);

            $this->selectedImages = [];
            
            session()->flash('success', $result->getMessage());

        } catch (\Exception $e) {
            session()->flash('error', 'Processing failed: ' . $e->getMessage());
        }
    }

    public function reprocessFailed()
    {
        try {
            $failedImages = ProductImage::where('processing_status', ProductImage::PROCESSING_FAILED)->get();

            if ($failedImages->isEmpty()) {
                session()->flash('info', 'No failed images to reprocess.');
                return;
            }

            $result = ImageManager::make()
                ->processImmediately(false)
                ->process($failedImages);
            
            $this->loadStats();
            
            session()->flash('success', "Queued {$failedImages->count()} failed images for reprocessing.");

        } catch (\Exception $e) {
            session()->flash('error', 'Reprocessing failed: ' . $e->getMessage());
        }
    }

    protected function resetFilters()
    {
        $this->search = '';
        $this->typeFilter = '';
        $this->statusFilter = '';
        $this->assignmentFilter = 'all';
        $this->selectedImages = [];
    }

    protected function loadStats()
    {
        $this->stats = [
            'total' => ProductImage::count(),
            'unassigned' => ProductImage::whereNull('product_id')->whereNull('variant_id')->count(),
            'products' => ProductImage::whereNotNull('product_id')->whereNull('variant_id')->count(),
            'variants' => ProductImage::whereNotNull('variant_id')->whereNull('product_id')->count(),
            'pending' => ProductImage::where('processing_status', ProductImage::PROCESSING_PENDING)->count(),
            'processing' => ProductImage::where('processing_status', ProductImage::PROCESSING_IN_PROGRESS)->count(),
            'completed' => ProductImage::where('processing_status', ProductImage::PROCESSING_COMPLETED)->count(),
            'failed' => ProductImage::where('processing_status', ProductImage::PROCESSING_FAILED)->count(),
        ];
    }

    public function render()
    {
        return view('livewire.media.media-library', [
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'variants' => $this->selectedProductId 
                ? ProductVariant::where('product_id', $this->selectedProductId)->with('product:id,name')->get(['id', 'sku', 'product_id'])
                : collect(),
        ]);
    }
}