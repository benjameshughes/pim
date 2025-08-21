<?php

use App\Livewire\Products\Wizard\ImageUploadStep;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    // Mock the images disk
    Storage::fake('images');

    // Create test models
    $this->product = Product::factory()->create([
        'name' => 'Test Product',
        'parent_sku' => 'TEST-001',
        'status' => 'active',
    ]);

    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'sku' => 'TEST-001-001',
        'color' => 'Red',
        'width' => 120,
        'drop' => 160,
    ]);
});

describe('ImageUploadStep Component Mounting', function () {
    it('mounts with default props correctly', function () {
        $component = Livewire::test(ImageUploadStep::class);

        expect($component->stepData)->toBe([]);
        expect($component->isActive)->toBeFalse();
        expect($component->currentStep)->toBe(3);
        expect($component->isEditMode)->toBeFalse();
    });

    it('mounts with custom props correctly', function () {
        $stepData = ['test' => 'data'];

        $component = Livewire::test(ImageUploadStep::class, [
            'stepData' => $stepData,
            'isActive' => true,
            'currentStep' => 5,
            'isEditMode' => true,
            'product' => $this->product,
        ]);

        expect($component->stepData)->toBe($stepData);
        expect($component->isActive)->toBeTrue();
        expect($component->currentStep)->toBe(5);
        expect($component->isEditMode)->toBeTrue();
    });

    it('initializes collections correctly', function () {
        $component = Livewire::test(ImageUploadStep::class);

        expect($component->productImages)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($component->variantImages)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($component->availableVariants)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });
});

describe('Existing Images Loading', function () {
    it('loads existing product images in edit mode', function () {
        // Create some existing images
        Image::factory()->count(3)->create([
            'imageable_type' => Product::class,
            'imageable_id' => $this->product->id,
        ]);

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => true,
            'product' => $this->product,
        ]);

        expect($component->productImages)->toHaveCount(3);
    });

    it('loads existing variant images in edit mode', function () {
        // Create some variant images
        Image::factory()->count(2)->create([
            'imageable_type' => ProductVariant::class,
            'imageable_id' => $this->variant->id,
        ]);

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => true,
            'product' => $this->product,
        ]);

        expect($component->variantImages)->toHaveCount(2);
    });

    it('starts with empty collections for new products', function () {
        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => false,
        ]);

        expect($component->productImages)->toHaveCount(0);
        expect($component->variantImages)->toHaveCount(0);
    });
});

describe('Available Variants Loading', function () {
    it('loads variants from step data', function () {
        $stepData = [
            'variants' => [
                'generated_variants' => [
                    [
                        'id' => 'test-1',
                        'sku' => 'TEST-001-001',
                        'color' => 'Red',
                        'width' => 120,
                        'drop' => 160,
                    ],
                    [
                        'id' => 'test-2',
                        'sku' => 'TEST-001-002',
                        'color' => 'Blue',
                        'width' => 150,
                        'drop' => 180,
                    ],
                ],
            ],
        ];

        $component = Livewire::test(ImageUploadStep::class, [
            'stepData' => $stepData,
        ]);

        expect($component->availableVariants)->toHaveCount(2);
        expect($component->availableVariants->first()['color'])->toBe('Red');
    });

    it('loads variants from product in edit mode', function () {
        // Clear existing variant from beforeEach
        ProductVariant::where('product_id', $this->product->id)->delete();

        ProductVariant::factory()->count(3)->create([
            'product_id' => $this->product->id,
        ]);

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => true,
            'product' => $this->product,
        ]);

        expect($component->availableVariants)->toHaveCount(3);
    });
});

describe('File Upload - New Product Mode', function () {
    it('handles single file upload correctly', function () {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600)->size(1000);

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => false,
        ])
            ->set('newProductImages', [$file]);

        expect($component->productImages)->toHaveCount(1);
        expect($component->productImages->first()['filename'])->toBe('test.jpg');
        expect($component->productImages->first()['is_primary'])->toBeTrue();
        expect($component->productImages->first()['is_temporary'])->toBeTrue();
    });

    it('handles multiple file upload correctly', function () {
        $files = [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.png'),
            UploadedFile::fake()->image('third.webp'),
        ];

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => false,
        ])
            ->set('newProductImages', $files);

        expect($component->productImages)->toHaveCount(3);
        expect($component->productImages->first()['is_primary'])->toBeTrue();
        expect($component->productImages->skip(1)->first()['is_primary'])->toBeFalse();
    });

    it('shows upload progress during upload', function () {
        $file = UploadedFile::fake()->image('test.jpg');

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => false,
        ]);

        // Before upload
        expect($component->isUploading)->toBeFalse();
        expect($component->uploadProgress)->toBe(0);

        $component->set('newProductImages', [$file]);

        // After upload
        expect($component->isUploading)->toBeFalse(); // Should be false after completion
        expect($component->uploadProgress)->toBe(100);
    });
});

describe('File Upload - Edit Mode', function () {
    it('uploads directly to existing product', function () {
        $file = UploadedFile::fake()->image('edit.jpg', 600, 400);

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => true,
            'product' => $this->product,
        ])
            ->set('newProductImages', [$file]);

        // Check database
        expect($this->product->images()->count())->toBe(1);
        expect($this->product->images()->first()->filename)->toBe('edit.jpg');

        // Component should reload existing images
        expect($component->productImages)->toHaveCount(1);
    });

    it('merges new uploads with existing images', function () {
        // Create existing image
        Image::factory()->create([
            'imageable_type' => Product::class,
            'imageable_id' => $this->product->id,
            'filename' => 'existing.jpg',
        ]);

        $file = UploadedFile::fake()->image('new.jpg');

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => true,
            'product' => $this->product,
        ])
            ->set('newProductImages', [$file]);

        expect($this->product->images()->count())->toBe(2);
        expect($component->productImages)->toHaveCount(2);
    });
});

describe('Primary Image Management', function () {
    it('sets primary image correctly in new mode', function () {
        $files = [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
        ];

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => false,
        ])
            ->set('newProductImages', $files);

        $secondImageId = $component->productImages->skip(1)->first()['id'];

        $component->call('setPrimaryImage', $secondImageId);

        expect($component->productImages->where('is_primary', true)->count())->toBe(1);
        expect($component->productImages->where('is_primary', true)->first()['id'])->toBe($secondImageId);
    });

    it('sets primary image correctly in edit mode', function () {
        // Create two images
        $image1 = Image::factory()->create([
            'imageable_type' => Product::class,
            'imageable_id' => $this->product->id,
            'is_primary' => true,
        ]);

        $image2 = Image::factory()->create([
            'imageable_type' => Product::class,
            'imageable_id' => $this->product->id,
            'is_primary' => false,
        ]);

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => true,
            'product' => $this->product,
        ])
            ->call('setPrimaryImage', (string) $image2->id);

        // Check database
        $image1->refresh();
        $image2->refresh();

        expect($image1->is_primary)->toBeFalse();
        expect($image2->is_primary)->toBeTrue();
    });

    it('dispatches success notification when setting primary image', function () {
        $file = UploadedFile::fake()->image('test.jpg');

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => false,
        ])
            ->set('newProductImages', [$file]);

        $imageId = $component->productImages->first()['id'];

        $component->call('setPrimaryImage', $imageId)
            ->assertDispatched('notify');
    });
});

describe('Image Removal', function () {
    it('removes image in new mode', function () {
        $files = [
            UploadedFile::fake()->image('keep.jpg'),
            UploadedFile::fake()->image('remove.jpg'),
        ];

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => false,
        ])
            ->set('newProductImages', $files);

        $removeImageId = $component->productImages->skip(1)->first()['id'];

        $component->call('removeImage', $removeImageId, 'product');

        expect($component->productImages)->toHaveCount(1);
        expect($component->productImages->first()['filename'])->toBe('keep.jpg');
    });

    it('removes image and file in edit mode', function () {
        $image = Image::factory()->create([
            'imageable_type' => Product::class,
            'imageable_id' => $this->product->id,
            'path' => 'test-image.jpg',
        ]);

        // Mock file existence
        Storage::disk('images')->put('test-image.jpg', 'fake content');

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => true,
            'product' => $this->product,
        ])
            ->call('removeImage', (string) $image->id, 'product');

        // Check database and storage
        expect(Image::find($image->id))->toBeNull();
        Storage::disk('images')->assertMissing('test-image.jpg');
    });

    it('dispatches success notification when removing image', function () {
        $file = UploadedFile::fake()->image('test.jpg');

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => false,
        ])
            ->set('newProductImages', [$file]);

        $imageId = $component->productImages->first()['id'];

        $component->call('removeImage', $imageId, 'product')
            ->assertDispatched('notify');
    });
});

describe('Clear All Images', function () {
    it('clears all images in new mode', function () {
        $files = [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
            UploadedFile::fake()->image('third.jpg'),
        ];

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => false,
        ])
            ->set('newProductImages', $files);

        expect($component->productImages)->toHaveCount(3);

        $component->call('clearAllImages', 'product');

        expect($component->productImages)->toHaveCount(0);
    });

    it('clears all images and files in edit mode', function () {
        $images = Image::factory()->count(3)->create([
            'imageable_type' => Product::class,
            'imageable_id' => $this->product->id,
        ]);

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => true,
            'product' => $this->product,
        ])
            ->call('clearAllImages', 'product');

        expect($this->product->images()->count())->toBe(0);
        expect($component->productImages)->toHaveCount(0);
    });
});

describe('Step Completion', function () {
    it('completes step and dispatches event with form data', function () {
        $files = [
            UploadedFile::fake()->image('test1.jpg'),
            UploadedFile::fake()->image('test2.jpg'),
        ];

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => false,
        ])
            ->set('newProductImages', $files)
            ->call('completeStep')
            ->assertDispatched('step-completed');
    });

    it('returns correct form data structure', function () {
        $file = UploadedFile::fake()->image('test.jpg');

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => false,
        ])
            ->set('newProductImages', [$file]);

        $formData = $component->getFormData();

        expect($formData)->toHaveKeys([
            'product_images',
            'variant_images',
            'enable_variant_images',
            'total_product_images',
            'total_variant_images',
        ]);

        expect($formData['total_product_images'])->toBe(1);
        expect($formData['total_variant_images'])->toBe(0);
    });
});

describe('Computed Properties', function () {
    it('correctly separates existing and new images', function () {
        // Create existing image
        Image::factory()->create([
            'imageable_type' => Product::class,
            'imageable_id' => $this->product->id,
        ]);

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => true,
            'product' => $this->product,
        ]);

        // Add new temporary image
        $file = UploadedFile::fake()->image('new.jpg');
        $component->set('newProductImages', [$file]);

        expect($component->existingImages())->toHaveCount(1);
        expect($component->newImages())->toHaveCount(1);
    });

    it('calculates image stats correctly', function () {
        $files = [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
        ];

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => false,
        ])
            ->set('newProductImages', $files);

        $stats = $component->imageStats();

        expect($stats['total_images'])->toBe(2);
        expect($stats['existing_count'])->toBe(0);
        expect($stats['new_count'])->toBe(2);
        expect($stats['has_primary_image'])->toBeTrue();
        expect($stats['primary_image_id'])->not->toBeNull();
    });

});

describe('Error Handling', function () {
    it('handles upload exceptions gracefully', function () {
        // Mock the service to throw an exception
        $this->mock(ImageUploadService::class)
            ->shouldReceive('uploadToProduct')
            ->andThrow(new \Exception('Upload failed'));

        $file = UploadedFile::fake()->image('test.jpg');

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => true,
            'product' => $this->product,
        ])
            ->set('newProductImages', [$file])
            ->assertDispatched('notify');

        // Upload should have been reset
        expect($component->isUploading)->toBeFalse();
        expect($component->newProductImages)->toBe([]);
    });

    it('resets upload state after exception', function () {
        // Mock service to throw exception
        $this->mock(ImageUploadService::class)
            ->shouldReceive('uploadToProduct')
            ->andThrow(new \Exception('Test exception'));

        $file = UploadedFile::fake()->image('test.jpg');

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => true,
            'product' => $this->product,
        ])
            ->set('newProductImages', [$file]);

        expect($component->isUploading)->toBeFalse();
        expect($component->newProductImages)->toBe([]);
    });
});

describe('UI State Management', function () {
    it('shows and hides upload progress correctly', function () {
        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => false,
        ]);

        // Initially not uploading
        expect($component->isUploading)->toBeFalse();
        expect($component->uploadProgress)->toBe(0);

        $file = UploadedFile::fake()->image('test.jpg');
        $component->set('newProductImages', [$file]);

        // After upload completes
        expect($component->isUploading)->toBeFalse();
        expect($component->uploadProgress)->toBe(100);
    });

    it('enables and disables variant images feature', function () {
        $component = Livewire::test(ImageUploadStep::class)
            ->set('enableVariantImages', true);

        expect($component->enableVariantImages)->toBeTrue();

        $component->set('enableVariantImages', false);

        expect($component->enableVariantImages)->toBeFalse();
    });
});

describe('Component Rendering', function () {
    it('renders without errors', function () {
        $component = Livewire::test(ImageUploadStep::class);

        $component->assertOk();
    });

    it('renders with existing product images', function () {
        Image::factory()->create([
            'imageable_type' => Product::class,
            'imageable_id' => $this->product->id,
            'filename' => 'test.jpg',
        ]);

        $component = Livewire::test(ImageUploadStep::class, [
            'isEditMode' => true,
            'product' => $this->product,
        ]);

        $component->assertOk()
            ->assertSee('test.jpg');
    });

    it('shows empty state when no images exist', function () {
        $component = Livewire::test(ImageUploadStep::class);

        $component->assertOk()
            ->assertSee('No Images Yet');
    });

    it('shows upload requirements info', function () {
        $component = Livewire::test(ImageUploadStep::class);

        $component->assertOk()
            ->assertSee('Upload Requirements')
            ->assertSee('Maximum file size')
            ->assertSee('Supported formats');
    });
});
