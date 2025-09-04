<?php

namespace App\Livewire\Products;

use App\Facades\Images;
use App\Models\Product;
use App\Services\Marketplace\Facades\Sync;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductOverview extends Component
{
    use WithFileUploads;
    public Product $product;

    public ?array $shopifyPushResult = null;

    // Inline editing states
    public bool $editingName = false;
    public bool $editingDescription = false;
    public string $tempName = '';
    public string $tempDescription = '';

    // Image modal states
    public bool $showImageModal = false;
    public array $availableImages = [];
    public string $searchTerm = '';
    public int $currentPage = 1;
    public int $perPage = 20;
    public int $totalImages = 0;
    public int $totalPages = 0;
    public array $selectedImages = [];
    
    // Color image modal states
    public bool $showColorImageModal = false;
    public string $currentColor = '';
    public array $colorImages = [];
    public array $selectedColorImages = [];
    public string $colorSearchTerm = '';
    public int $colorCurrentPage = 1;
    public int $colorPerPage = 20;
    public int $colorTotalImages = 0;
    public int $colorTotalPages = 0;
    
    // Upload functionality (imitating ImageLibrary)
    /** @var \Illuminate\Http\UploadedFile[] */
    public $newImages = [];
    public string $activeTab = 'select'; // 'select' or 'upload'
    
    // Upload metadata (same as ImageLibrary)
    public array $uploadMetadata = [
        'title' => '',
        'alt_text' => '',
        'description' => '',
        'folder' => '',
        'tags' => '',
    ];

    // 🌟 Enhanced image data using Images facade
    public array $imageStats = [];
    public array $enhancedImages = [];

    public function mount(Product $product)
    {
        $this->authorize('view-product-details');

        $this->product = $product;
        
        // 🌟 Load enhanced image data using Images facade
        $this->loadEnhancedImageData();
    }

    // Inline editing methods for Name
    public function startEditingName()
    {
        $this->authorize('edit-products');
        $this->editingName = true;
        $this->tempName = $this->product->name;
    }

    public function saveName()
    {
        $this->authorize('edit-products');
        
        $this->validate([
            'tempName' => 'required|string|max:255'
        ]);

        $this->product->update(['name' => $this->tempName]);
        $this->editingName = false;

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Product name updated successfully! ✨'
        ]);
    }

    public function cancelEditingName()
    {
        $this->editingName = false;
        $this->tempName = $this->product->name;
        $this->resetErrorBag('tempName');
    }

    // Inline editing methods for Description
    public function startEditingDescription()
    {
        $this->authorize('edit-products');
        $this->editingDescription = true;
        $this->tempDescription = $this->product->description ?? '';
    }

    public function saveDescription()
    {
        $this->authorize('edit-products');
        
        $this->validate([
            'tempDescription' => 'nullable|string|max:1000'
        ]);

        $this->product->update(['description' => $this->tempDescription ?: null]);
        $this->editingDescription = false;

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Product description updated successfully! ✨'
        ]);
    }

    public function cancelEditingDescription()
    {
        $this->editingDescription = false;
        $this->tempDescription = $this->product->description ?? '';
        $this->resetErrorBag('tempDescription');
    }

    // Image modal methods
    public function openImageModal()
    {
        $this->authorize('edit-products');
        $this->showImageModal = true;
        $this->loadAvailableImages();
    }

    public function closeImageModal()
    {
        $this->showImageModal = false;
        $this->selectedImages = [];
        $this->activeTab = 'select';
        $this->searchTerm = '';
        $this->currentPage = 1;
        $this->reset(['newImages', 'uploadMetadata']);
    }

    // 🔍 Search functionality
    public function updatedSearchTerm()
    {
        $this->currentPage = 1; // Reset to first page when searching
        $this->loadAvailableImages();
    }

    // 📄 Pagination methods
    public function nextPage()
    {
        if ($this->currentPage < $this->totalPages) {
            $this->currentPage++;
            $this->loadAvailableImages();
        }
    }

    public function previousPage()
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
            $this->loadAvailableImages();
        }
    }

    public function goToPage(int $page)
    {
        if ($page >= 1 && $page <= $this->totalPages) {
            $this->currentPage = $page;
            $this->loadAvailableImages();
        }
    }

    // 🎨 COLOR IMAGE MODAL METHODS
    public function openColorImageModal(string $color)
    {
        $this->authorize('edit-products');
        $this->currentColor = $color;
        $this->showColorImageModal = true;
        $this->loadColorImages();
    }

    public function closeColorImageModal()
    {
        $this->showColorImageModal = false;
        $this->currentColor = '';
        $this->selectedColorImages = [];
        $this->colorSearchTerm = '';
        $this->colorCurrentPage = 1;
        $this->colorImages = [];
    }

    // 🔍 Color search functionality
    public function updatedColorSearchTerm()
    {
        $this->colorCurrentPage = 1; // Reset to first page when searching
        $this->loadColorImages();
    }

    // 📄 Color pagination methods
    public function colorNextPage()
    {
        if ($this->colorCurrentPage < $this->colorTotalPages) {
            $this->colorCurrentPage++;
            $this->loadColorImages();
        }
    }

    public function colorPreviousPage()
    {
        if ($this->colorCurrentPage > 1) {
            $this->colorCurrentPage--;
            $this->loadColorImages();
        }
    }

    public function colorGoToPage(int $page)
    {
        if ($page >= 1 && $page <= $this->colorTotalPages) {
            $this->colorCurrentPage = $page;
            $this->loadColorImages();
        }
    }

    /**
     * 🎨 Load ALL images with pagination and search for color group context
     */
    protected function loadColorImages()
    {
        if (empty($this->currentColor)) return;
        
        // 🌟 Get current attached images for this color group
        $currentColorImageIds = Images::product($this->product)->color($this->currentColor)->get()->pluck('id')->toArray();
        
        // 🌟 Build query with search and pagination - SHOW ALL IMAGES
        $query = \App\Models\Image::query()
            ->originals() // Only show original images, not variants
            ->orderBy('created_at', 'desc');
        
        // Apply search if provided
        if (!empty($this->colorSearchTerm)) {
            $query->where(function ($q) {
                $q->where('filename', 'like', '%' . $this->colorSearchTerm . '%')
                  ->orWhere('display_title', 'like', '%' . $this->colorSearchTerm . '%')
                  ->orWhere('alt_text', 'like', '%' . $this->colorSearchTerm . '%');
            });
        }
        
        // Get total count for pagination
        $total = $query->count();
        $this->colorTotalImages = $total;
        $this->colorTotalPages = ceil($total / $this->colorPerPage);
        
        // Apply pagination
        $offset = ($this->colorCurrentPage - 1) * $this->colorPerPage;
        $availableImages = $query->skip($offset)->take($this->colorPerPage)->get();
            
        $this->colorImages = [];
        
        foreach ($availableImages as $image) {
            // 🌟 Use Images facade for family data and thumbnails
            $family = Images::find($image)->family()->all();
            $thumbnail = $family->firstWhere('folder', 'variants') ?? $image;

            $this->colorImages[] = [
                'id' => $image->id,
                'url' => $image->url,
                'thumb_url' => $thumbnail->url,
                'filename' => $image->filename,
                'display_title' => $image->display_title,
                'alt_text' => $image->alt_text,
                'family_size' => $family->count(),
                'is_attached' => in_array($image->id, $currentColorImageIds), // Show attachment status
            ];
        }
    }

    // 🎨 Color image selection and management
    public function toggleColorImageSelection(int $imageId)
    {
        if (in_array($imageId, $this->selectedColorImages)) {
            $this->selectedColorImages = array_diff($this->selectedColorImages, [$imageId]);
        } else {
            $this->selectedColorImages[] = $imageId;
        }
    }

    public function attachSelectedColorImages()
    {
        $this->authorize('edit-products');

        if (empty($this->selectedColorImages)) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Please select at least one image to attach.'
            ]);
            return;
        }

        // 🌟 Use Images facade for color attachment
        try {
            $images = \App\Models\Image::whereIn('id', $this->selectedColorImages)->get();
            foreach ($images as $image) {
                Images::product($this->product)->color($this->currentColor)->attach($image);
            }
            
            $count = count($this->selectedColorImages);
            $this->dispatch('toast', [
                'type' => 'success',
                'message' => "Successfully attached {$count} image" . ($count > 1 ? 's' : '') . " to {$this->currentColor} color group! 🎨✨"
            ]);

            // Refresh data and reset selection
            $this->product->refresh();
            $this->loadEnhancedImageData();
            $this->selectedColorImages = []; // Clear selection but keep modal open
            $this->loadColorImages(); // Refresh color images
            
        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Failed to attach images: ' . $e->getMessage()
            ]);
        }
    }


    public function detachColorImage(int $imageId)
    {
        $this->authorize('edit-products');
        
        // 🌟 Use Images facade for detachment
        try {
            $image = \App\Models\Image::find($imageId);
            if ($image) {
                Images::product($this->product)->color($this->currentColor)->detach($image);
                
                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => 'Image detached from ' . $this->currentColor . ' color group! 🎨'
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Failed to detach image: ' . $e->getMessage()
            ]);
        }

        // Refresh data
        $this->product->refresh();
        $this->loadEnhancedImageData();
        $this->loadColorImages();
    }

    public function attachColorImage(int $imageId)
    {
        $this->authorize('edit-products');
        
        // 🌟 Use Images facade for attachment
        try {
            $image = \App\Models\Image::find($imageId);
            if ($image) {
                Images::product($this->product)->color($this->currentColor)->attach($image);
                
                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => 'Image attached to ' . $this->currentColor . ' color group! 🎨✨'
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Failed to attach image: ' . $e->getMessage()
            ]);
        }

        // Refresh data
        $this->product->refresh();
        $this->loadEnhancedImageData();
        $this->loadColorImages();
    }
    
    /**
     * 📑 Switch active tab in modal
     */
    public function setActiveTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    /**
     * 🌟 Load enhanced image data using Images facade
     */
    public function loadEnhancedImageData()
    {
        // Get enhanced image statistics using Images facade
        $this->imageStats = [
            'total_images' => Images::product($this->product)->count(),
            'has_primary' => Images::product($this->product)->primary() !== null,
            'primary_image' => Images::product($this->product)->primary(),
        ];

        // Get enhanced image data with thumbnails using Images facade
        $productImages = Images::product($this->product)->get();
        $this->enhancedImages = [];

        foreach ($productImages as $image) {
            // Use Images facade to get image family data (includes thumbnails)
            $family = Images::find($image)->family()->all();
            $thumbnail = $family->firstWhere('folder', 'variants') ?? $image;

            $this->enhancedImages[] = [
                'id' => $image->id,
                'url' => $image->url,
                'thumb_url' => $thumbnail->url,
                'filename' => $image->filename,
                'display_title' => $image->display_title,
                'alt_text' => $image->alt_text,
                'is_primary' => $image->id === $this->imageStats['primary_image']?->id,
                'family_stats' => Images::find($image)->family()->stats(),
            ];
        }
    }

    public function loadAvailableImages()
    {
        // 🌟 Get current attached images for visual indication
        $currentImageIds = Images::product($this->product)->get()->pluck('id')->toArray();
        
        // 🌟 Build query with search and pagination - SHOW ALL IMAGES
        $query = \App\Models\Image::query()
            ->originals() // Only show original images, not variants
            ->orderBy('created_at', 'desc');
        
        // Apply search if provided
        if (!empty($this->searchTerm)) {
            $query->where(function ($q) {
                $q->where('filename', 'like', '%' . $this->searchTerm . '%')
                  ->orWhere('display_title', 'like', '%' . $this->searchTerm . '%')
                  ->orWhere('alt_text', 'like', '%' . $this->searchTerm . '%');
            });
        }
        
        // Get total count for pagination
        $total = $query->count();
        $this->totalImages = $total;
        $this->totalPages = ceil($total / $this->perPage);
        
        // Apply pagination
        $offset = ($this->currentPage - 1) * $this->perPage;
        $availableImages = $query->skip($offset)->take($this->perPage)->get();
            
        $this->availableImages = [];
        
        foreach ($availableImages as $image) {
            // 🌟 Use Images facade for family data and thumbnails
            $family = Images::find($image)->family()->all();
            $thumbnail = $family->firstWhere('folder', 'variants') ?? $image;

            $this->availableImages[] = [
                'id' => $image->id,
                'url' => $image->url,
                'thumb_url' => $thumbnail->url,
                'filename' => $image->filename,
                'display_title' => $image->display_title,
                'alt_text' => $image->alt_text,
                'family_size' => $family->count(),
                'is_attached' => in_array($image->id, $currentImageIds), // Show attachment status
            ];
        }
    }

    /**
     * 🌟 Get enhanced thumbnail URL using Images facade
     */
    private function getThumbnailUrl(\App\Models\Image $image): string
    {
        // Use Images facade to get thumbnail from family
        $family = Images::find($image)->family()->all();
        $thumbnail = $family->firstWhere('folder', 'variants');
        
        return $thumbnail ? $thumbnail->url : $image->url;
    }

    /**
     * Get price range using proper pricing system
     */
    public function getPriceRange(): array
    {
        $prices = $this->product->variants->map(function ($variant) {
            return $variant->getRetailPrice();
        })->filter(function ($price) {
            return $price > 0; // Filter out 0 prices
        });

        if ($prices->isEmpty()) {
            return ['min' => 0, 'max' => 0];
        }

        return [
            'min' => $prices->min(),
            'max' => $prices->max(),
        ];
    }

    public function toggleImageSelection(int $imageId)
    {
        if (in_array($imageId, $this->selectedImages)) {
            $this->selectedImages = array_values(array_filter($this->selectedImages, fn($id) => $id !== $imageId));
        } else {
            $this->selectedImages[] = $imageId;
        }
    }

    public function attachSelectedImages()
    {
        $this->authorize('edit-products');

        if (empty($this->selectedImages)) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Please select at least one image to attach.'
            ]);
            return;
        }

        // 🌟 Use Images facade for attachment
        try {
            $images = \App\Models\Image::whereIn('id', $this->selectedImages)->get();
            foreach ($images as $image) {
                Images::product($this->product)->attach($image);
            }
            
            $count = count($this->selectedImages);
            $this->dispatch('toast', [
                'type' => 'success',
                'message' => "Successfully attached {$count} image" . ($count > 1 ? 's' : '') . " using Images facade! ✨"
            ]);

            // Refresh enhanced image data and reset selection
            $this->product->refresh();
            $this->loadEnhancedImageData();
            $this->selectedImages = []; // Clear selection but keep modal open
            
        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Failed to attach images: ' . $e->getMessage()
            ]);
        }
    }

    public function detachImage(int $imageId)
    {
        $this->authorize('edit-products');
        
        // 🌟 Use Images facade for detachment
        try {
            $image = \App\Models\Image::find($imageId);
            if ($image) {
                Images::product($this->product)->detach($image);
                
                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => 'Image detached using Images facade! 🗑️'
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Failed to detach image: ' . $e->getMessage()
            ]);
        }

        // Refresh enhanced image data
        $this->product->refresh();
        $this->loadEnhancedImageData();
    }

    public function setPrimaryImage(int $imageId)
    {
        $this->authorize('edit-products');
        
        // 🌟 Use Images facade setPrimary method (exclusive primary logic)
        try {
            Images::product($this->product)->setPrimary($imageId);
            
            $this->dispatch('toast', [
                'type' => 'success',
                'message' => 'Primary image updated! ⭐'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Failed to set primary image: ' . $e->getMessage()
            ]);
        }

        // Refresh enhanced image data  
        $this->product->refresh();
        $this->loadEnhancedImageData();
    }

    /**
     * 📤 UPLOAD IMAGES - Using same pattern as ImageLibrary
     */
    public function uploadImages(\App\Actions\Images\UploadImagesAction $uploadAction)
    {
        $this->authorize('upload-images');

        // 🪄 Use our MAGIC Images facade validation - auto-executes with ()!
        if (!empty($this->newImages)) {
            $validationResult = Images::validate($this->newImages)
                ->imageOnly()
                ->maxFiles(10)
                ->maxFileSize('5MB')(); // ✨ Magic __invoke() auto-execution!
                
            if (!$validationResult['valid']) {
                foreach ($validationResult['errors'] as $error) {
                    $this->dispatch('toast', [
                        'type' => 'error',
                        'message' => $error
                    ]);
                }
                return;
            }
        }

        try {
            // Use existing UploadImagesAction
            $result = $uploadAction->execute($this->newImages, $this->uploadMetadata);

            if ($result['success']) {
                $uploadCount = $result['data']['upload_count'];
                $uploadedImages = $result['data']['uploaded_images'] ?? [];
                
                // 🚀 Auto-attach uploaded images to this product using Images facade (BULK)
                if (!empty($uploadedImages)) {
                    Images::product($this->product)->attach($uploadedImages);
                }

                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => "{$uploadCount} images uploaded and attached to product! ✨"
                ]);

                // Refresh data and reset upload form but keep modal open
                $this->reset(['newImages', 'uploadMetadata']);
                $this->product->refresh();
                $this->loadEnhancedImageData();

            } else {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => 'Upload failed: ' . $result['message']
                ]);
            }

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Upload failed: ' . $e->getMessage()
            ]);
        }
    }

    public function pushToShopify()
    {
        $this->authorize('manage-products');

        try {
            // 🎯 KISS API - Use create() or fullUpdate() based on current status
            $shopifyStatus = $this->product->getSmartAttributeValue('shopify_status');
            $operationType = $shopifyStatus === 'synced' ? 'fullUpdate' : 'create';
            $actionMessage = $shopifyStatus === 'synced' ? 'update' : 'creation';

            // 🚀 Set status to "processing" immediately so status shows right away
            $this->product->setAttributeValue('shopify_status', 'processing');

            // Dispatch the job
            if ($shopifyStatus === 'synced') {
                Sync::marketplace('shopify')
                    ->fullUpdate($this->product->id)
                    ->dispatch();
            } else {
                Sync::marketplace('shopify')
                    ->create($this->product->id)
                    ->dispatch();
            }

            $this->dispatch('toast', [
                'type' => 'success',
                'message' => "Shopify sync {$actionMessage} job dispatched! Status will update shortly.",
            ]);

            // Refresh product to trigger status update
            $this->product->refresh();

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Failed to dispatch job: '.$e->getMessage(),
            ]);
        }
    }

    public function updateShopifyTitle()
    {
        $this->authorize('manage-products');

        try {
            // 🚀 Set status to "processing" immediately so status shows right away
            $this->product->setAttributeValue('shopify_status', 'processing');

            // KISS fluent API using queue system for consistency
            Sync::marketplace('shopify')
                ->update($this->product->id)
                ->title($this->product->name)
                ->dispatch();

            $this->dispatch('toast', [
                'type' => 'success',
                'message' => 'Shopify title update job dispatched! Status will update shortly.',
            ]);
            
            // Refresh product to trigger status update
            $this->product->refresh();

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Title update failed: '.$e->getMessage(),
            ]);
        }
    }

    public function updateShopifyPricing()
    {
        $this->authorize('manage-products');

        try {
            // 🚀 Set status to "processing" immediately so status shows right away
            $this->product->setAttributeValue('shopify_status', 'processing');

            // KISS fluent API using queue system for consistency
            Sync::marketplace('shopify')
                ->update($this->product->id)
                ->pricing()
                ->dispatch();

            $this->dispatch('toast', [
                'type' => 'success',
                'message' => 'Shopify pricing update job dispatched! Status will update shortly.',
            ]);
            
            // Refresh product to trigger status update
            $this->product->refresh();

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Pricing update failed: '.$e->getMessage(),
            ]);
        }
    }

    public function updateShopifyImages()
    {
        $this->authorize('manage-products');

        try {
            // 🚀 Set status to "processing" immediately so status shows right away
            $this->product->setAttributeValue('shopify_status', 'processing');

            // KISS fluent API using queue system for consistency
            Sync::marketplace('shopify')
                ->update($this->product->id)
                ->images([]) // Images will be queried from decoupled system by Action
                ->dispatch();

            $this->dispatch('toast', [
                'type' => 'success',
                'message' => 'Shopify images update job dispatched! Status will update shortly.',
            ]);
            
            // Refresh product to trigger status update
            $this->product->refresh();

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Images update failed: '.$e->getMessage(),
            ]);
        }
    }

    public function deleteFromShopify()
    {
        $this->authorize('manage-products');

        try {
            // KISS fluent API for delete operation
            $result = Sync::marketplace('shopify')
                ->delete($this->product->id)
                ->push();

            $this->shopifyPushResult = [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
                'data' => $result->getData(),
            ];

            if ($result->isSuccess()) {
                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => 'Successfully deleted from Shopify! '.$result->getMessage(),
                ]);

                // Refresh to show cleared attributes
                $this->product->refresh();
            } else {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => 'Delete failed: '.$result->getMessage(),
                ]);
            }

        } catch (\Exception $e) {
            $this->shopifyPushResult = [
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ];

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Delete failed: '.$e->getMessage(),
            ]);
        }
    }

    public function linkToShopify()
    {
        $this->authorize('manage-products');

        try {
            // KISS fluent API for link operation
            $result = Sync::marketplace('shopify')
                ->link($this->product->id)
                ->push();

            $this->shopifyPushResult = [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
                'data' => $result->getData(),
            ];

            if ($result->isSuccess()) {
                $data = $result->getData();
                $colorGroups = $data['color_groups_count'] ?? 0;
                $coverage = $data['coverage_percent'] ?? 0;

                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => "Successfully linked to Shopify! Found {$colorGroups} color groups ({$coverage}% coverage)",
                ]);

                // Refresh to show new sync status
                $this->product->refresh();
            } else {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => 'Link failed: '.$result->getMessage(),
                ]);
            }

        } catch (\Exception $e) {
            $this->shopifyPushResult = [
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ];

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Link failed: '.$e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.products.product-overview');
    }
}
