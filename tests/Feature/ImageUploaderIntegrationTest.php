<?php

namespace Tests\Feature;

use App\Livewire\Components\ImageUploader;
use App\Livewire\Pim\Media\ImageManager;
use App\Livewire\Pim\Products\Management\ProductView;
use App\Livewire\Pim\Products\Variants\VariantEdit;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ImageUploaderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_image_manager_has_image_uploader_trait()
    {
        $component = Livewire::test(ImageManager::class);
        
        $this->assertTrue(method_exists($component->instance(), 'onImagesUploaded'));
        $this->assertTrue(method_exists($component->instance(), 'getImageUploaderConfig'));
    }

    public function test_product_view_has_image_uploader_trait()
    {
        $product = Product::factory()->create();
        
        $component = Livewire::test(ProductView::class, ['product' => $product]);
        
        $this->assertTrue(method_exists($component->instance(), 'onImagesUploaded'));
        $this->assertTrue(method_exists($component->instance(), 'getImageUploaderConfig'));
    }

    public function test_variant_edit_has_image_uploader_trait()
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        
        $component = Livewire::test(VariantEdit::class, ['variant' => $variant]);
        
        $this->assertTrue(method_exists($component->instance(), 'onImagesUploaded'));
        $this->assertTrue(method_exists($component->instance(), 'getImageUploaderConfig'));
    }

    public function test_image_uploader_configuration()
    {
        $product = Product::factory()->create();
        
        $component = Livewire::test(ImageUploader::class, [
            'modelType' => 'product',
            'modelId' => $product->id,
            'imageType' => 'main'
        ]);
        
        $this->assertEquals('product', $component->instance()->modelType);
        $this->assertEquals($product->id, $component->instance()->modelId);
        $this->assertEquals('main', $component->instance()->imageType);
    }

    public function test_image_uploader_event_dispatching()
    {
        $product = Product::factory()->create();
        
        $component = Livewire::test(ImageUploader::class, [
            'modelType' => 'product',
            'modelId' => $product->id,
            'imageType' => 'main'
        ]);
        
        // Mock file upload
        $file = UploadedFile::fake()->image('test.jpg', 600, 600);
        $component->set('files', [$file]);
        
        // Test upload dispatches the correct events
        $component->call('upload')
            ->assertDispatched('images-uploaded')
            ->assertDispatched('$refresh');
    }

    public function test_product_image_scopes_work_correctly()
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        
        // Create test images
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'variant_id' => null,
            'image_type' => 'main',
            'sort_order' => 1
        ]);
        
        ProductImage::factory()->create([
            'product_id' => null,
            'variant_id' => $variant->id,
            'image_type' => 'swatch',
            'sort_order' => 2
        ]);
        
        ProductImage::factory()->create([
            'product_id' => null,
            'variant_id' => null,
            'image_type' => 'main',
            'sort_order' => 3
        ]);
        
        // Test scopes
        $this->assertEquals(1, ProductImage::forProduct($product->id)->count());
        $this->assertEquals(1, ProductImage::forVariant($variant->id)->count());
        $this->assertEquals(2, ProductImage::byType('main')->count());
        $this->assertEquals(1, ProductImage::byType('swatch')->count());
        
        // Test ordered scope
        $orderedImages = ProductImage::ordered()->get();
        $this->assertEquals(1, $orderedImages->first()->sort_order);
        $this->assertEquals(3, $orderedImages->last()->sort_order);
    }
}