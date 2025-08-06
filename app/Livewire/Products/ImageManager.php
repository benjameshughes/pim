<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class ImageManager extends Component
{
    use WithFileUploads, WithPagination;

    public $search = '';
    public $filterType = ''; // 'product', 'variant', 'unassigned'
    public $selectedImages = [];
    public $bulkEditMode = false;
    
    // Upload properties
    public $newImages = [];
    public $uploading = false;
    public $imageType = 'main'; // 'main', 'gallery', 'swatch'
    public $altText = '';
    
    // Assignment properties
    public $assignmentMode = false;
    public $selectedProductId = '';
    public $selectedVariantId = '';

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

    public function updatedSelectedProductId()
    {
        $this->selectedVariantId = '';
    }

    public function uploadImages()
    {
        $this->validate([
            'newImages.*' => 'image|max:5120', // 5MB max
            'imageType' => 'required|in:main,gallery,swatch',
        ]);

        $this->uploading = true;

        try {
            $uploadedCount = 0;
            foreach ($this->newImages as $image) {
                $path = $image->store('product-images', 'public');
                
                // Create ProductImage record (unassigned initially)
                ProductImage::create([
                    'image_path' => $path,
                    'image_type' => $this->imageType,
                    'alt_text' => $this->altText ?: null,
                    'sort_order' => 0,
                ]);
                
                $uploadedCount++;
            }

            $this->newImages = [];
            $this->altText = '';
            session()->flash('success', "Uploaded {$uploadedCount} images successfully!");
        } catch (\Exception $e) {
            session()->flash('error', 'Upload failed: ' . $e->getMessage());
        }

        $this->uploading = false;
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
            'selected_count' => count($this->selectedImages),
        ];

        return view('livewire.products.image-manager', [
            'images' => $images,
            'stats' => $stats,
            'products' => Product::orderBy('name')->get(),
            'variants' => $this->selectedProductId ? 
                ProductVariant::where('product_id', $this->selectedProductId)->with('product')->get() : 
                collect(),
        ]);
    }
}
