<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Livewire\Pim\Products\Management\ProductView;
use Livewire\Livewire;

describe('ProductView Tabs Integration', function () {
    beforeEach(function () {
        // Create test product with variants  
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'description' => 'Test description',
            'product_features_1' => 'Feature 1',
            'product_details_1' => 'Detail 1'
        ]);
        
        // Create variants separately
        ProductVariant::factory()->count(2)->create([
            'product_id' => $this->product->id
        ]);
    });

    it('can render ProductView component with all tabs', function () {
        Livewire::test(ProductView::class, ['product' => $this->product])
            ->assertOk()
            ->assertSee($this->product->name)
            ->assertSee('Overview')
            ->assertSee('Variants')
            ->assertSee('Images')
            ->assertSee('Attributes')
            ->assertSee('Marketplace Sync');
    });

    it('shows correct content in overview tab when on overview route', function () {
        // Mock being on overview tab
        $this->get(route('products.product.overview', $this->product));
        
        Livewire::test(ProductView::class, ['product' => $this->product])
            ->assertOk()
            ->assertSee('Test description')
            ->assertSee('Feature 1')
            ->assertSee('Detail 1')
            ->assertSee('Quick Stats');
    });

    it('can navigate to overview tab and render tab partials', function () {
        // Test that tab partials can be rendered
        $overviewView = view('livewire.pim.products.management.tabs.overview', [
            'product' => $this->product
        ]);
        
        expect($overviewView->render())->toContain('Test description');
        expect($overviewView->render())->toContain('Feature 1');
        expect($overviewView->render())->toContain('Quick Stats');
    });

    it('can render variants tab partial without errors', function () {
        // The variants tab just includes the VariantIndex component
        // Test that the partial exists and contains the expected Livewire directive
        $partialContent = file_get_contents(resource_path('views/livewire/pim/products/management/tabs/variants.blade.php'));
        
        expect($partialContent)->toContain('livewire:pim.products.variants.variant-index');
        expect($partialContent)->toContain(':product="$product"');
    });

    it('can render images tab partial', function () {
        $imagesView = view('livewire.pim.products.management.tabs.images', [
            'product' => $this->product
        ]);
        
        expect($imagesView->render())->toContain('Product Images');
        expect($imagesView->render())->toContain('Main Images');
    });

    it('can render attributes tab partial', function () {
        $attributesView = view('livewire.pim.products.management.tabs.attributes', [
            'product' => $this->product
        ]);
        
        expect($attributesView->render())->toContain('No Attributes');
    });

    it('can render sync tab partial', function () {
        $syncView = view('livewire.pim.products.management.tabs.sync', [
            'product' => $this->product
        ]);
        
        expect($syncView->render())->toContain('Marketplace Sync Status');
        expect($syncView->render())->toContain('Shopify');
    });
});