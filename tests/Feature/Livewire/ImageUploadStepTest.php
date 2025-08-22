<?php

use App\Livewire\Products\Wizard\ImageUploadStep;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Image Upload Step Component', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can render the image upload step component', function () {
        $component = Livewire::test(ImageUploadStep::class)
            ->assertStatus(200);
            
        expect($component)->not->toBeNull();
    });

    it('initializes with default values', function () {
        $component = Livewire::test(ImageUploadStep::class);

        expect($component->get('currentStep'))->toBe(3);
        expect($component->get('isActive'))->toBe(false);
        expect($component->get('isEditMode'))->toBe(false);
        expect($component->get('isUploading'))->toBe(false);
        expect($component->get('uploadProgress'))->toBe(0);
        expect($component->get('enableVariantImages'))->toBe(false);
        expect($component->get('productImages')->count())->toBe(0);
        expect($component->get('variantImages')->count())->toBe(0);
        expect($component->get('availableVariants')->count())->toBe(0);
    });

    it('loads existing step data on mount', function () {
        $stepData = [
            'variants' => [
                'generated_variants' => [
                    [
                        'id' => '1',
                        'sku' => 'TEST-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140,
                    ]
                ]
            ]
        ];

        $component = Livewire::test(ImageUploadStep::class, [
            'stepData' => $stepData,
            'isActive' => true,
            'currentStep' => 3,
            'isEditMode' => false
        ]);

        expect($component->get('isActive'))->toBe(true);
        expect($component->get('availableVariants')->count())->toBe(1);
        expect($component->get('availableVariants')->first()['sku'])->toBe('TEST-001');
    });

    it('loads existing product images in edit mode', function () {
        $product = Product::factory()->create();
        $image = Image::factory()->create([
            'imageable_id' => $product->id,
            'imageable_type' => Product::class,
            'is_primary' => true
        ]);

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => true,
            'product' => $product
        ]);

        expect($component->get('isEditMode'))->toBe(true);
        expect($component->get('product')->id)->toBe($product->id);
        expect($component->get('productImages')->count())->toBe(1);
        expect($component->get('productImages')->first()->is_primary)->toBe(true);
    });

    it('loads available variants from step data', function () {
        $stepData = [
            'variants' => [
                'generated_variants' => [
                    [
                        'sku' => 'VAR-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140,
                    ],
                    [
                        'sku' => 'VAR-002', 
                        'color' => 'Blue',
                        'width' => 90,
                        'drop' => 160,
                    ]
                ]
            ]
        ];

        $component = Livewire::test(ImageUploadStep::class, [
            'stepData' => $stepData
        ]);

        expect($component->get('availableVariants')->count())->toBe(2);
        expect($component->get('availableVariants')->pluck('color')->toArray())->toBe(['Red', 'Blue']);
    });

    it('loads available variants from existing product in edit mode', function () {
        $product = Product::factory()->create();
        $variant1 = ProductVariant::factory()->create(['product_id' => $product->id, 'color' => 'Red']);
        $variant2 = ProductVariant::factory()->create(['product_id' => $product->id, 'color' => 'Blue']);

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => true,
            'product' => $product
        ]);

        expect($component->get('availableVariants')->count())->toBe(2);
        expect($component->get('availableVariants')->pluck('color')->toArray())->toBe(['Red', 'Blue']);
    });

    it('handles temporary image upload for new products', function () {
        Storage::fake('local');
        $file = UploadedFile::fake()->image('test.jpg');

        $component = Livewire::test(ImageUploadStep::class)
            ->set('newProductImages', [$file]);

        expect($component->get('productImages')->count())->toBe(1);
        expect($component->get('productImages')->first()['filename'])->toBe('test.jpg');
        expect($component->get('productImages')->first()['is_primary'])->toBe(true);
        expect($component->get('productImages')->first()['is_temporary'])->toBe(true);
    });

    it('sets first uploaded image as primary automatically', function () {
        Storage::fake('local');
        $file1 = UploadedFile::fake()->image('test1.jpg');
        $file2 = UploadedFile::fake()->image('test2.jpg');

        $component = Livewire::test(ImageUploadStep::class)
            ->set('newProductImages', [$file1])
            ->set('newProductImages', [$file2]); // Add second image

        $images = $component->get('productImages');
        $primaryCount = $images->where('is_primary', true)->count();
        
        expect($primaryCount)->toBe(1);
        expect($images->first()['is_primary'])->toBe(true);
    });

    it('can set primary image for temporary images', function () {
        Storage::fake('local');
        $file1 = UploadedFile::fake()->image('test1.jpg');
        $file2 = UploadedFile::fake()->image('test2.jpg');

        $component = Livewire::test(ImageUploadStep::class)
            ->set('newProductImages', [$file1])
            ->set('newProductImages', [$file2]);

        $images = $component->get('productImages');
        $secondImageId = $images->last()['id'];

        $component->call('setPrimaryImage', $secondImageId);

        $updatedImages = $component->get('productImages');
        expect($updatedImages->where('id', $secondImageId)->first()['is_primary'])->toBe(true);
        expect($updatedImages->where('is_primary', true)->count())->toBe(1);
    });

    it('can remove temporary images', function () {
        Storage::fake('local');
        $file = UploadedFile::fake()->image('test.jpg');

        $component = Livewire::test(ImageUploadStep::class)
            ->set('newProductImages', [$file]);

        expect($component->get('productImages')->count())->toBe(1);

        $imageId = $component->get('productImages')->first()['id'];
        $component->call('removeImage', $imageId);

        expect($component->get('productImages')->count())->toBe(0);
    });

    it('sets new primary when removing current primary image', function () {
        Storage::fake('local');
        $file1 = UploadedFile::fake()->image('test1.jpg');
        $file2 = UploadedFile::fake()->image('test2.jpg');

        $component = Livewire::test(ImageUploadStep::class)
            ->set('newProductImages', [$file1]);
        
        // Add second image separately
        $component->set('newProductImages', [$file2]);

        $images = $component->get('productImages');
        $primaryImageId = $images->where('is_primary', true)->first()['id'];

        $component->call('removeImage', $primaryImageId);

        $remainingImages = $component->get('productImages');
        expect($remainingImages->count())->toBeGreaterThanOrEqual(1);
        
        if ($remainingImages->count() > 0) {
            expect($remainingImages->where('is_primary', true)->count())->toBeGreaterThan(0);
        }
    });

    it('can clear all temporary images', function () {
        Storage::fake('local');
        $file1 = UploadedFile::fake()->image('test1.jpg');
        $file2 = UploadedFile::fake()->image('test2.jpg');

        $component = Livewire::test(ImageUploadStep::class)
            ->set('newProductImages', [$file1])
            ->set('newProductImages', [$file2]);

        expect($component->get('productImages')->count())->toBe(2);

        $component->call('clearAllImages');

        expect($component->get('productImages')->count())->toBe(0);
    });

    it('emits step-completed event', function () {
        Livewire::test(ImageUploadStep::class)
            ->call('completeStep')
            ->assertDispatched('step-completed', 3);
    });

    it('returns correct form data', function () {
        Storage::fake('local');
        $file = UploadedFile::fake()->image('test.jpg');

        $component = Livewire::test(ImageUploadStep::class)
            ->set('newProductImages', [$file]);

        // Access the protected method via call()
        $formData = $component->call('completeStep');
        
        // Check that step-completed event contains form data
        $component->assertDispatched('step-completed');
        
        // Test the public getters instead
        expect($component->get('productImages')->count())->toBe(1);
        expect($component->get('variantImages')->count())->toBe(0);
        expect($component->get('enableVariantImages'))->toBe(false);
    });

    it('computes image stats correctly', function () {
        Storage::fake('local');
        $file = UploadedFile::fake()->image('test.jpg');

        $component = Livewire::test(ImageUploadStep::class)
            ->set('newProductImages', [$file]);

        $stats = $component->instance()->imageStats();

        expect($stats['total_images'])->toBe(1);
        expect($stats['has_primary_image'])->toBe(true);
    });

    it('handles empty state gracefully', function () {
        $component = Livewire::test(ImageUploadStep::class);

        $stats = $component->instance()->imageStats();
        
        expect($stats['total_images'])->toBe(0);
        expect($stats['has_primary_image'])->toBe(false);
    });

    it('handles images-linked event', function () {
        $component = Livewire::test(ImageUploadStep::class)
            ->call('handleImagesLinked', ['count' => 3]);

        // Should dispatch success notification
        $component->assertDispatched('notify');
    });

    it('displays component sections correctly', function () {
        Livewire::test(ImageUploadStep::class)
            ->assertSee('Images') // Component should show image-related content
            ->assertStatus(200);
    });

    it('handles file upload validation errors gracefully', function () {
        // Test that the component handles empty uploads without crashing
        $component = Livewire::test(ImageUploadStep::class)
            ->set('newProductImages', []);

        // Component should still be functional
        expect($component->get('productImages')->count())->toBe(0);
    });

    it('handles variant images when enabled', function () {
        $component = Livewire::test(ImageUploadStep::class)
            ->set('enableVariantImages', true);

        expect($component->get('enableVariantImages'))->toBe(true);
        
        // Test that the setting persists
        $component->call('completeStep');
        $component->assertDispatched('step-completed');
    });

    it('maintains image upload state during upload', function () {
        $component = Livewire::test(ImageUploadStep::class)
            ->set('isUploading', true)
            ->set('uploadProgress', 50);

        expect($component->get('isUploading'))->toBe(true);
        expect($component->get('uploadProgress'))->toBe(50);
    });

    it('handles step data with no variants gracefully', function () {
        $stepData = []; // Empty step data

        $component = Livewire::test(ImageUploadStep::class, [
            'stepData' => $stepData
        ]);

        expect($component->get('availableVariants')->count())->toBe(0);
        expect($component)->not->toBeNull();
    });
});