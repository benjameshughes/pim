<?php

namespace App\Livewire\Products;

use App\Actions\Products\DeleteProductAction;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Product Show Livewire Component
 * 
 * Displays detailed product information with related data.
 * Clean implementation with organized data sections.
 * 
 * @package App\Livewire\Products
 */
#[Layout('components.layouts.app')]
#[Title('View Product')]
class ProductShow extends Component
{
    /**
     * Product being displayed
     * 
     * @var Product
     */
    public Product $product;
    
    /**
     * Current active tab
     * 
     * @var string
     */
    public string $activeTab = 'overview';
    
    /**
     * Available tabs
     * 
     * @var array
     */
    public array $tabs = [
        'overview' => 'Overview',
        'variants' => 'Variants',
        'images' => 'Images',
        'attributes' => 'Attributes',
        'metadata' => 'Metadata',
    ];
    
    /**
     * Deletion confirmation state
     * 
     * @var bool
     */
    public bool $showDeleteConfirmation = false;
    
    /**
     * Mount the component with product data
     * 
     * @param Product $product
     * @return void
     */
    public function mount(Product $product): void
    {
        $this->product = $product->load([
            'variants' => function ($query) {
                $query->with(['barcodes', 'pricing', 'variantImages']);
            },
            'productImages',
            'categories',
            'attributes',
            'metadata'
        ]);
    }
    
    /**
     * Switch to a different tab
     * 
     * @param string $tab
     * @return void
     */
    public function switchTab(string $tab): void
    {
        if (array_key_exists($tab, $this->tabs)) {
            $this->activeTab = $tab;
        }
    }
    
    /**
     * Show delete confirmation modal
     * 
     * @return void
     */
    public function confirmDelete(): void
    {
        $this->showDeleteConfirmation = true;
    }
    
    /**
     * Cancel delete confirmation
     * 
     * @return void
     */
    public function cancelDelete(): void
    {
        $this->showDeleteConfirmation = false;
    }
    
    /**
     * Delete the product
     * 
     * @return void
     */
    public function deleteProduct(): void
    {
        try {
            $productName = $this->product->name;
            
            $deleteAction = app(DeleteProductAction::class);
            $deleteAction->execute($this->product);
            
            // Dispatch success event
            $this->dispatch('product-deleted', productName: $productName);
            
            // Flash success message and redirect
            session()->flash('success', "Product '{$productName}' deleted successfully.");
            $this->redirect(route('products.index'), navigate: true);
            
        } catch (\Exception $e) {
            $this->showDeleteConfirmation = false;
            
            $this->dispatch('product-delete-failed', error: $e->getMessage());
            
            // Flash error message
            session()->flash('error', 'Failed to delete product: ' . $e->getMessage());
        }
    }
    
    /**
     * Get product features as array
     * 
     * @return array
     */
    public function getProductFeatures(): array
    {
        return array_filter([
            $this->product->product_features_1,
            $this->product->product_features_2,
            $this->product->product_features_3,
            $this->product->product_features_4,
            $this->product->product_features_5,
        ]);
    }
    
    /**
     * Get product details as array
     * 
     * @return array
     */
    public function getProductDetails(): array
    {
        return array_filter([
            $this->product->product_details_1,
            $this->product->product_details_2,
            $this->product->product_details_3,
            $this->product->product_details_4,
            $this->product->product_details_5,
        ]);
    }
    
    /**
     * Get the status badge class for display
     * 
     * @param string $status
     * @return string
     */
    public function getStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'active' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            'inactive' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
            'draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            'archived' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        };
    }
    
    /**
     * Get variant status summary
     * 
     * @return array
     */
    public function getVariantStatusSummary(): array
    {
        $variants = $this->product->variants;
        
        return [
            'total' => $variants->count(),
            'with_barcodes' => $variants->filter(fn($v) => $v->barcodes->isNotEmpty())->count(),
            'with_pricing' => $variants->filter(fn($v) => $v->pricing->isNotEmpty())->count(),
            'with_images' => $variants->filter(fn($v) => $v->variantImages->isNotEmpty())->count(),
        ];
    }
    
    /**
     * Get main product image URL
     * 
     * @return string|null
     */
    public function getMainImageUrl(): ?string
    {
        $mainImage = $this->product->mainImage();
        if ($mainImage) {
            return \Storage::url($mainImage->image_path);
        }
        
        // Fallback to first image in images array
        if ($this->product->images && is_array($this->product->images) && !empty($this->product->images)) {
            return asset('storage/' . $this->product->images[0]);
        }
        
        return null;
    }
    
    /**
     * Get all product image URLs
     * 
     * @return array
     */
    public function getAllImageUrls(): array
    {
        $urls = [];
        
        // From product images relationship
        foreach ($this->product->productImages as $image) {
            $urls[] = \Storage::url($image->image_path);
        }
        
        // From images JSON array
        if ($this->product->images && is_array($this->product->images)) {
            foreach ($this->product->images as $imagePath) {
                $urls[] = asset('storage/' . $imagePath);
            }
        }
        
        return array_unique($urls);
    }
    
    /**
     * Check if product has any data in a specific section
     * 
     * @param string $section
     * @return bool
     */
    public function hasDataInSection(string $section): bool
    {
        return match ($section) {
            'variants' => $this->product->variants->isNotEmpty(),
            'images' => $this->getAllImageUrls() !== [] || $this->product->productImages->isNotEmpty(),
            'attributes' => $this->product->attributes->isNotEmpty(),
            'metadata' => $this->product->metadata->isNotEmpty(),
            default => false,
        };
    }
    
    /**
     * Render the component
     * 
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.products.product-show', [
            'features' => $this->getProductFeatures(),
            'details' => $this->getProductDetails(),
            'variantSummary' => $this->getVariantStatusSummary(),
            'mainImageUrl' => $this->getMainImageUrl(),
            'allImageUrls' => $this->getAllImageUrls(),
        ]);
    }
}