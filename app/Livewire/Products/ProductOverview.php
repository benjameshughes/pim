<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Services\Marketplace\Facades\Sync;
use Livewire\Component;

class ProductOverview extends Component
{
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
    public array $selectedImages = [];

    // Thumbnail cache
    public \Illuminate\Support\Collection $thumbnails;

    public function mount(Product $product)
    {
        $this->authorize('view-product-details');

        // 🚀 NO RELATIONSHIP LOADING - ProductShow already loaded all needed data
        $this->product = $product;
        
        // Load all thumbnails in a single query for performance
        $this->loadThumbnails();
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
    }

    public function loadAvailableImages()
    {
        // Get all ORIGINAL images not already attached to this product (exclude variants)
        $currentImageIds = $this->product->images()->pluck('images.id')->toArray();
        
        $availableImages = \App\Models\Image::query()
            ->originals() // Only show original images, not variants
            ->whereNotIn('id', $currentImageIds)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
            
        // Get thumbnails for all available images in a single query for performance
        $availableImageIds = $availableImages->pluck('id');
        $availableThumbnails = \App\Models\Image::where('folder', 'variants')
            ->whereJsonContains('tags', 'thumb')
            ->get()
            ->filter(function($thumbnail) use ($availableImageIds) {
                foreach ($thumbnail->tags ?? [] as $tag) {
                    if (str_starts_with($tag, 'original-')) {
                        $originalId = (int) str_replace('original-', '', $tag);
                        return $availableImageIds->contains($originalId);
                    }
                }
                return false;
            })
            ->keyBy(function($thumbnail) {
                foreach ($thumbnail->tags ?? [] as $tag) {
                    if (str_starts_with($tag, 'original-')) {
                        return (int) str_replace('original-', '', $tag);
                    }
                }
                return null;
            })
            ->filter();
        
        $this->availableImages = $availableImages->map(function ($image) use ($availableThumbnails) {
            return [
                'id' => $image->id,
                'url' => $image->url,
                'thumb_url' => $availableThumbnails[$image->id]->url ?? $image->url,
                'filename' => $image->filename,
                'display_title' => $image->display_title,
                'alt_text' => $image->alt_text,
            ];
        })->toArray();
    }

    /**
     * Load all thumbnails for product images in a single query
     */
    public function loadThumbnails(): void
    {
        if ($this->product->images->isEmpty()) {
            $this->thumbnails = collect();
            return;
        }
        
        $imageIds = $this->product->images->pluck('id');
        
        // Single query to get all relevant thumbnails
        $thumbnails = \App\Models\Image::where('folder', 'variants')
            ->whereJsonContains('tags', 'thumb')
            ->get()
            ->filter(function($thumbnail) use ($imageIds) {
                // Filter thumbnails that belong to our product images
                foreach ($thumbnail->tags ?? [] as $tag) {
                    if (str_starts_with($tag, 'original-')) {
                        $originalId = (int) str_replace('original-', '', $tag);
                        return $imageIds->contains($originalId);
                    }
                }
                return false;
            })
            ->keyBy(function($thumbnail) {
                // Key by original image ID for easy lookup
                foreach ($thumbnail->tags ?? [] as $tag) {
                    if (str_starts_with($tag, 'original-')) {
                        return (int) str_replace('original-', '', $tag);
                    }
                }
                return null;
            })
            ->filter(); // Remove null keys
            
        $this->thumbnails = $thumbnails;
    }

    /**
     * Get thumbnail URL for an image (with fallback to original)
     */
    private function getThumbnailUrl(\App\Models\Image $image): string
    {
        // Use cached thumbnail if available
        if (isset($this->thumbnails[$image->id])) {
            return $this->thumbnails[$image->id]->url;
        }

        return $image->url;
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

        // Attach selected ORIGINAL images to the product 
        // (selectedImages contains original image IDs, thumbnails are just for display)
        $this->product->images()->attach($this->selectedImages);
        
        $count = count($this->selectedImages);
        $this->dispatch('toast', [
            'type' => 'success',
            'message' => "Successfully attached {$count} image" . ($count > 1 ? 's' : '') . "! ✨"
        ]);

        // Refresh product data and close modal
        $this->product->refresh();
        $this->closeImageModal();
    }

    public function detachImage(int $imageId)
    {
        $this->authorize('edit-products');
        
        $this->product->images()->detach($imageId);
        
        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Image detached successfully! 🗑️'
        ]);

        // Refresh product data
        $this->product->refresh();
    }

    public function setPrimaryImage(int $imageId)
    {
        $this->authorize('edit-products');
        
        // Remove primary flag from all current images
        $this->product->images()->updateExistingPivot($this->product->images()->pluck('images.id'), ['is_primary' => false]);
        
        // Set the selected image as primary
        $this->product->images()->updateExistingPivot($imageId, ['is_primary' => true]);
        
        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Primary image updated successfully! ⭐'
        ]);

        // Refresh product data
        $this->product->refresh();
    }

    public function pushToShopify()
    {
        $this->authorize('manage-products');

        try {
            // 🎯 KISS API - Use create() or fullUpdate() based on current status
            $shopifyStatus = $this->product->getSmartAttributeValue('shopify_status');

            if ($shopifyStatus === 'synced') {
                // Products already exist - perform full update (preserves Shopify IDs)
                Sync::marketplace('shopify')
                    ->fullUpdate($this->product->id)
                    ->dispatch();

                $actionMessage = 'update job dispatched';
            } else {
                // No existing products - create new ones
                Sync::marketplace('shopify')
                    ->create($this->product->id)
                    ->dispatch();

                $actionMessage = 'creation job dispatched';
            }

            $this->dispatch('toast', [
                'type' => 'success',
                'message' => "Shopify sync {$actionMessage}! Check logs for progress.",
            ]);

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
            // KISS fluent API for partial update
            $result = Sync::marketplace('shopify')
                ->update($this->product->id)
                ->title($this->product->name.' - UPDATED')
                ->push();

            $this->shopifyPushResult = [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
            ];

            if ($result->isSuccess()) {
                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => 'Title updated in Shopify! '.$result->getMessage(),
                ]);

                // Refresh to show any status changes
                $this->product->refresh();
            } else {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => 'Title update failed: '.$result->getMessage(),
                ]);
            }

        } catch (\Exception $e) {
            $this->shopifyPushResult = [
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ];

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
            // KISS fluent API for pricing update
            $result = Sync::marketplace('shopify')
                ->update($this->product->id)
                ->pricing()
                ->push();

            $this->shopifyPushResult = [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
            ];

            if ($result->isSuccess()) {
                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => 'Pricing updated in Shopify! '.$result->getMessage(),
                ]);

                // Refresh to show any status changes
                $this->product->refresh();
            } else {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => 'Pricing update failed: '.$result->getMessage(),
                ]);
            }

        } catch (\Exception $e) {
            $this->shopifyPushResult = [
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ];

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Pricing update failed: '.$e->getMessage(),
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
