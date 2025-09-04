<?php

namespace App\Livewire\Products;

use App\Facades\Images;
use App\Models\Product;
use Livewire\Component;

/**
 * 🖼️ PRODUCT IMAGES TAB - ENHANCED WITH IMAGES FACADE
 *
 * Display and manage images for a specific product using the new Images facade system
 * Demonstrates smart image resolution and color group functionality
 */
class ProductImages extends Component
{
    public Product $product;
    public array $imageStats = [];
    public array $colorGroups = [];
    public string $viewMode = 'grid'; // grid, list, color_groups
    public bool $showVariants = false;

    public function mount(Product $product)
    {
        // Authorize viewing images
        $this->authorize('view-images');

        $this->product = $product;
        
        // 🌟 USE NEW IMAGES FACADE FOR ENHANCED DATA
        $this->loadImageData();
    }
    
    /**
     * 🌟 Load enhanced image data using the new Images facade
     */
    public function loadImageData()
    {
        // Get basic product images
        $productImages = Images::product($this->product)->get();
        
        // Get color-grouped images
        $this->colorGroups = [];
        $colors = $this->product->variants()->distinct('color')->pluck('color');
        
        foreach ($colors as $color) {
            $colorImages = Images::color($this->product, $color)->get();
            if ($colorImages->isNotEmpty()) {
                $this->colorGroups[$color] = [
                    'images' => $colorImages,
                    'primary' => Images::color($this->product, $color)->primary(),
                    'count' => $colorImages->count(),
                ];
            }
        }
        
        // Calculate image statistics
        $this->imageStats = [
            'total_images' => Images::product($this->product)->count(),
            'color_groups' => count($this->colorGroups),
            'has_primary' => Images::product($this->product)->primary() !== null,
            'primary_image' => Images::product($this->product)->primary(),
        ];
    }
    
    /**
     * 🔄 REFRESH IMAGES - Force reload using Images facade
     */
    public function refreshImages()
    {
        $this->product = $this->product->fresh(['images']);
        $this->loadImageData();
        $this->dispatch('success', 'Images refreshed with enhanced data!');
    }

    /**
     * 👁️ Toggle view mode
     */
    public function setViewMode(string $mode)
    {
        $this->viewMode = $mode;
    }

    /**
     * 🎨 Toggle variant images display
     */
    public function toggleVariants()
    {
        $this->showVariants = !$this->showVariants;
    }

    /**
     * 🌟 Get smart image data for a variant
     */
    public function getVariantImageData($variantId)
    {
        $variant = $this->product->variants()->find($variantId);
        if (!$variant) return null;

        // Use smart image resolution
        $smartData = Images::smart($variant)->stats();
        $displayImage = Images::smart($variant)->display();
        
        return [
            'variant' => $variant,
            'smart_data' => $smartData,
            'display_image' => $displayImage,
            'has_images' => Images::smart($variant)->hasImages(),
            'source' => Images::smart($variant)->source(),
        ];
    }

    /**
     * 🔍 Get image family data for gallery view
     */
    public function getImageFamily($imageId)
    {
        $image = $this->product->images()->find($imageId);
        if (!$image) return null;

        return Images::find($image)->family()->stats();
    }

    public function render()
    {
        return view('livewire.products.product-images');
    }
}
