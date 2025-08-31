<?php

namespace App\Livewire\Images;

use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * 🔗 IMAGE ATTACHMENT MANAGER - UNIFIED SEARCH WITH MULTI-SELECT
 *
 * Unified interface for attaching images to products and variants
 * Supports multiple selection and batch operations
 */
class ImageProductAttachment extends Component
{
    public Image $image;

    // Search and selection state
    public string $searchType = 'both'; // 'products', 'variants', 'both'
    public string $searchTerm = '';
    public array $selectedItems = []; // Array of ['type' => 'product'|'variant', 'id' => int]
    public bool $showResults = false;

    /**
     * 🎪 MOUNT - Initialize with image
     */
    public function mount(Image $image): void
    {
        $this->image = $image;
    }

    /**
     * 🔍 UPDATE SEARCH TERM - Show results when typing
     */
    public function updatedSearchTerm(): void
    {
        $this->showResults = !empty($this->searchTerm);
    }

    /**
     * 🎯 SET SEARCH TYPE
     */
    public function setSearchType(string $type): void
    {
        $this->searchType = $type;
        $this->showResults = !empty($this->searchTerm);
    }

    /**
     * ✅ ADD ITEM TO SELECTION
     */
    public function addToSelection(string $type, int $id): void
    {
        $item = ['type' => $type, 'id' => $id];
        
        // Prevent duplicates
        foreach ($this->selectedItems as $selected) {
            if ($selected['type'] === $type && $selected['id'] === $id) {
                return;
            }
        }
        
        $this->selectedItems[] = $item;
        // Keep search term and results visible for continued searching
        $this->showResults = true;
    }

    /**
     * ❌ REMOVE ITEM FROM SELECTION
     */
    public function removeFromSelection(int $index): void
    {
        unset($this->selectedItems[$index]);
        $this->selectedItems = array_values($this->selectedItems); // Reindex
    }

    /**
     * 🔗 ATTACH SELECTED ITEMS
     */
    public function attachSelectedItems(): void
    {
        if (empty($this->selectedItems)) {
            return;
        }

        try {
            $attachedCount = 0;
            $attachedNames = [];

            foreach ($this->selectedItems as $item) {
                if ($item['type'] === 'product') {
                    $product = Product::find($item['id']);
                    if ($product && !$this->image->isAttachedTo($product)) {
                        $this->image->attachTo($product);
                        $attachedNames[] = "📦 {$product->name}";
                        $attachedCount++;
                    }
                } else {
                    $variant = ProductVariant::with('product')->find($item['id']);
                    if ($variant && !$this->image->isAttachedTo($variant)) {
                        $this->image->attachTo($variant);
                        $attachedNames[] = "💎 {$variant->product->name} - {$variant->title}";
                        $attachedCount++;
                    }
                }
            }

            if ($attachedCount > 0) {
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => "Image attached to {$attachedCount} item(s): " . implode(', ', $attachedNames),
                ]);
                
                $this->selectedItems = []; // Clear selection
            } else {
                $this->dispatch('notify', [
                    'type' => 'info',
                    'message' => 'No new attachments were made (items may already be attached)',
                ]);
            }

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to attach image: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * 🔓 DETACH FROM SPECIFIC ITEM
     */
    public function detachFromItem(string $type, int $id): void
    {
        try {
            if ($type === 'product') {
                $product = Product::find($id);
                if ($product) {
                    $this->image->detachFrom($product);
                    $message = "Image detached from product: {$product->name}";
                }
            } else {
                $variant = ProductVariant::with('product')->find($id);
                if ($variant) {
                    $this->image->detachFrom($variant);
                    $message = "Image detached from variant: {$variant->product->name} - {$variant->title}";
                }
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $message ?? 'Image detached successfully',
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to detach image: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * 🔓 DETACH FROM ALL PRODUCTS/VARIANTS
     */
    public function detachFromAll(): void
    {
        try {
            $this->image->products()->detach();
            $this->image->variants()->detach();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Image detached from all products and variants',
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to detach image: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * 🔍 GET SEARCH RESULTS
     */
    public function getSearchResultsProperty(): array
    {
        if (empty($this->searchTerm) || strlen($this->searchTerm) < 2) {
            return [];
        }

        $results = [];

        // Search products
        if ($this->searchType === 'products' || $this->searchType === 'both') {
            $products = Product::where('name', 'like', '%' . $this->searchTerm . '%')
                ->orWhere('parent_sku', 'like', '%' . $this->searchTerm . '%')
                ->limit(10)
                ->get();

            foreach ($products as $product) {
                $results[] = [
                    'type' => 'product',
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->parent_sku,
                    'display_name' => $product->name,
                    'is_attached' => $this->image->isAttachedTo($product),
                ];
            }
        }

        // Search variants
        if ($this->searchType === 'variants' || $this->searchType === 'both') {
            $variants = ProductVariant::with('product')
                ->where('sku', 'like', '%' . $this->searchTerm . '%')
                ->orWhere('title', 'like', '%' . $this->searchTerm . '%')
                ->orWhere('color', 'like', '%' . $this->searchTerm . '%')
                ->orWhereHas('product', function ($query) {
                    $query->where('name', 'like', '%' . $this->searchTerm . '%');
                })
                ->limit(10)
                ->get();

            foreach ($variants as $variant) {
                $results[] = [
                    'type' => 'variant',
                    'id' => $variant->id,
                    'name' => $variant->product->name . ' - ' . $variant->title,
                    'sku' => $variant->sku,
                    'display_name' => $variant->product->name . ' - ' . $variant->color . ' ' . $variant->width . 'cm',
                    'is_attached' => $this->image->isAttachedTo($variant),
                ];
            }
        }

        return $results;
    }

    /**
     * 📊 GET CURRENT ATTACHMENTS FOR DISPLAY
     */
    public function getCurrentAttachmentsProperty(): array
    {
        $attachments = [];

        // Get product attachments
        $products = $this->image->products()->get();
        foreach ($products as $product) {
            $attachments[] = [
                'type' => 'product',
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->parent_sku,
            ];
        }

        // Get variant attachments
        $variants = $this->image->variants()->with('product')->get();
        foreach ($variants as $variant) {
            $attachments[] = [
                'type' => 'variant',
                'id' => $variant->id,
                'name' => $variant->product->name . ' - ' . $variant->title,
                'sku' => $variant->sku,
            ];
        }

        return $attachments;
    }

    /**
     * 📊 GET SELECTED ITEMS WITH DETAILS
     */
    public function getSelectedItemsWithDetailsProperty(): array
    {
        $items = [];

        foreach ($this->selectedItems as $index => $item) {
            if ($item['type'] === 'product') {
                $product = Product::find($item['id']);
                if ($product) {
                    $items[] = [
                        'index' => $index,
                        'type' => 'product',
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->parent_sku,
                        'display_name' => $product->name,
                    ];
                }
            } else {
                $variant = ProductVariant::with('product')->find($item['id']);
                if ($variant) {
                    $items[] = [
                        'index' => $index,
                        'type' => 'variant',
                        'id' => $variant->id,
                        'name' => $variant->product->name . ' - ' . $variant->title,
                        'sku' => $variant->sku,
                        'display_name' => $variant->product->name . ' - ' . $variant->color . ' ' . $variant->width . 'cm',
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * 🎨 RENDER COMPONENT
     */
    public function render(): View
    {
        return view('livewire.images.image-product-attachment');
    }
}
