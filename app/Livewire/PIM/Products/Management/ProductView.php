<?php

namespace App\Livewire\Pim\Products\Management;

use App\Models\Product;
use App\Concerns\HasTabs;
use App\Livewire\Concerns\HasImageUpload;
use App\UI\Components\Tab;
use App\UI\Components\TabSet;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ProductView extends Component
{
    use HasTabs, HasImageUpload;

    public Product $product;

    public function mount(Product $product)
    {
        $this->product = $product;
        $this->initializeTabs();
    }

    /**
     * Configure the tabs for this component
     */
    protected function configureTabs(): TabSet
    {
        return TabSet::make()
            ->baseRoute('products.product')
            ->defaultRouteParameters(['product' => $this->product])
            ->wireNavigate(true)
            ->tabs([
                Tab::make('overview')
                    ->label('Overview')
                    ->icon('package'),

                Tab::make('variants')
                    ->label('Variants')
                    ->icon('layers')
                    ->badge(fn() => $this->getVariantCount(), 'blue'),

                Tab::make('images')
                    ->label('Images')
                    ->icon('image')
                    ->badge(fn() => $this->getImageCount(), 'green'),

                Tab::make('attributes')
                    ->label('Attributes')
                    ->icon('tag')
                    ->badge(fn() => $this->getAttributeCount(), 'purple'),

                Tab::make('sync')
                    ->label('Marketplace Sync')
                    ->icon('globe')
                    ->badge(fn() => $this->getSyncCount(), 'orange')
                    ->hidden(fn() => !$this->hasSyncCapability()),
            ]);
    }

    /**
     * Helper methods for tab badges
     */
    protected function getVariantCount(): int
    {
        return $this->product->variants_count ?? $this->product->variants()->count();
    }

    protected function getImageCount(): int
    {
        return $this->product->productImages()->count();
    }

    protected function getAttributeCount(): int
    {
        return $this->product->attributes()->count();
    }

    protected function getSyncCount(): int
    {
        return $this->product->variants()
            ->whereHas('marketplaceVariants')
            ->count();
    }

    protected function hasSyncCapability(): bool
    {
        // Show sync tab if product has variants or marketplace configurations
        return $this->product->variants()->exists() || config('marketplace.enabled', true);
    }

    /**
     * Check if we should redirect to the default tab
     */
    protected function shouldRedirectToFirstTab(string $currentRoute): bool
    {
        return $currentRoute === 'products.product.view' || $currentRoute === 'products.product';
    }

    /**
     * Get the active tab property for the view (backwards compatibility)
     */
    public function getActiveTabProperty(): string
    {
        $activeTab = $this->getActiveTab();
        return $activeTab ? $activeTab->getKey() : 'overview';
    }

    /**
     * Get image uploader configuration for product images
     */
    protected function getImageUploaderConfig(): array
    {
        return [
            'modelType' => 'product',
            'modelId' => $this->product->id,
            'imageType' => 'main',
            'multiple' => true,
            'maxFiles' => 15,
            'maxSize' => 10240, // 10MB
            'acceptTypes' => ['jpg', 'jpeg', 'png', 'webp'],
            'processImmediately' => true,
            'showPreview' => true,
            'allowReorder' => true,
            'showExistingImages' => true,
            'uploadText' => 'Add product images'
        ];
    }

    /**
     * Custom handler for image uploads - refresh product images
     */
    public function handleImagesUploaded($data)
    {
        // Reload the product with images to show the new ones
        $this->product->refresh();
        $this->product->load(['productImages']);
        
        $count = $data['count'] ?? 0;
        session()->flash('success', "Uploaded {$count} product images successfully!");
    }

    /**
     * Custom handler for image deletion - refresh product images
     */
    public function handleImageDeleted($data)
    {
        $this->product->refresh();
        $this->product->load(['productImages']);
        
        session()->flash('success', 'Product image deleted successfully.');
    }

    /**
     * Custom handler for image reordering - refresh product images
     */
    public function handleImagesReordered($data)
    {
        $this->product->refresh();
        $this->product->load(['productImages']);
        
        session()->flash('success', 'Image order updated successfully.');
    }


    public function render()
    {
        // Load relationships
        $this->product->load([
            'variants.pricing',
            'variants.barcodes', 
            'variants.marketplaceVariants.marketplace',
            'productImages',
            'attributes.attributeDefinition'
        ]);

        // Check if we should redirect to default tab
        $this->redirectToDefaultTabIfNeeded();

        return view('livewire.pim.products.management.product-view', [
            'tabs' => $this->getTabsForNavigation($this->product),
        ]);
    }
}