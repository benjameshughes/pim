<?php

use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ImageUploadService;

    // Mock the images disk
    Storage::fake('images');

    // Create authenticated user
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

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

describe('File Validation', function () {
    it('validates file size correctly', function () {
        // Create a file that's too large (over 10MB)
        $largeFile = UploadedFile::fake()->image('large.jpg')->size(11000); // 11MB

        expect(fn () => $this->service->uploadToProduct($this->product, [$largeFile]))
            ->toThrow(InvalidArgumentException::class, 'File too large');
    });

    it('validates mime type correctly', function () {
        // Create a non-image file
        $txtFile = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        expect(fn () => $this->service->uploadToProduct($this->product, [$txtFile]))
            ->toThrow(InvalidArgumentException::class, 'Invalid file type');
    });

    it('validates file extension correctly', function () {
        // Create a file with invalid extension
        $invalidFile = UploadedFile::fake()->create('image.bmp', 100, 'image/bmp');

        expect(fn () => $this->service->uploadToProduct($this->product, [$invalidFile]))
            ->toThrow(InvalidArgumentException::class, 'Invalid file type');
    });

    it('accepts valid image files', function () {
        $validFile = UploadedFile::fake()->image('test.jpg', 800, 600)->size(1000);

        // Should not throw any exception
        expect(fn () => $this->service->uploadToProduct($this->product, [$validFile]))
            ->not->toThrow(InvalidArgumentException::class);
    });
});

describe('Product Image Upload', function () {
    it('uploads image to product successfully', function () {
        $file = UploadedFile::fake()->image('product.jpg', 800, 600)->size(1000);

        $images = $this->service->uploadToProduct($this->product, [$file]);

        expect($images)->toHaveCount(1);
        expect($images->first())->toBeInstanceOf(Image::class);

        $image = $images->first();
        expect($image->imageable_type)->toBe(Product::class);
        expect($image->imageable_id)->toBe($this->product->id);
        expect($image->filename)->toBe('product.jpg');
        expect($image->is_primary)->toBeTrue();
        expect($image->sort_order)->toBe(0);
    });

    it('generates UUID filename for storage', function () {
        $file = UploadedFile::fake()->image('original.jpg', 800, 600);

        $images = $this->service->uploadToProduct($this->product, [$file]);
        $image = $images->first();

        // Path should contain UUID, not original filename
        expect($image->path)->not->toContain('original');
        expect($image->path)->toMatch('/^[a-f0-9\-]{36}\.jpg$/');
    });

    it('sets first image as primary', function () {
        $file1 = UploadedFile::fake()->image('first.jpg');
        $file2 = UploadedFile::fake()->image('second.jpg');

        $firstImages = $this->service->uploadToProduct($this->product, [$file1]);
        $secondImages = $this->service->uploadToProduct($this->product, [$file2]);

        expect($firstImages->first()->is_primary)->toBeTrue();
        expect($secondImages->first()->is_primary)->toBeFalse();
    });

    it('assigns correct sort order to multiple images', function () {
        $files = [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
            UploadedFile::fake()->image('third.jpg'),
        ];

        $images = $this->service->uploadToProduct($this->product, $files);

        expect($images)->toHaveCount(3);
        expect($images->pluck('sort_order')->toArray())->toBe([0, 1, 2]);
    });
});

describe('Variant Image Upload', function () {
    it('uploads image to variant successfully', function () {
        $file = UploadedFile::fake()->image('variant.jpg', 600, 400);

        $images = $this->service->uploadToVariant($this->variant, [$file]);

        expect($images)->toHaveCount(1);

        $image = $images->first();
        expect($image->imageable_type)->toBe(ProductVariant::class);
        expect($image->imageable_id)->toBe($this->variant->id);
        expect($image->filename)->toBe('variant.jpg');
    });
});

describe('Primary Image Management', function () {
    it('sets primary image correctly', function () {
        // Upload two images
        $files = [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
        ];
        $images = $this->service->uploadToProduct($this->product, $files);

        $secondImage = $images->last();

        // Set second image as primary
        $result = $this->service->setPrimaryImage($this->product, $secondImage->id);

        expect($result)->toBeTrue();

        // Refresh from database
        $this->product->refresh();
        $allImages = $this->product->images;

        expect($allImages->where('is_primary', true)->count())->toBe(1);
        expect($allImages->where('is_primary', true)->first()->id)->toBe($secondImage->id);
    });

    it('returns false for non-existent image', function () {
        $result = $this->service->setPrimaryImage($this->product, 99999);

        expect($result)->toBeFalse();
    });
});

describe('Image Deletion', function () {
    it('deletes image and file successfully', function () {
        $file = UploadedFile::fake()->image('delete-me.jpg');
        $images = $this->service->uploadToProduct($this->product, [$file]);
        $image = $images->first();

        // Verify file exists
        Storage::disk('images')->assertExists($image->path);

        $result = $this->service->deleteImage($image);

        expect($result)->toBeTrue();

        // Verify file and record are deleted
        Storage::disk('images')->assertMissing($image->path);
        expect(Image::find($image->id))->toBeNull();
    });
});

describe('R2 Storage Integration', function () {
    it('stores files with correct visibility', function () {
        $file = UploadedFile::fake()->image('public.jpg');

        $images = $this->service->uploadToProduct($this->product, [$file]);
        $image = $images->first();

        // File should be stored and accessible
        Storage::disk('images')->assertExists($image->path);

        // URL should be generated
        expect($image->url)->toBeString();
        expect($image->url)->toContain($image->path);
    });

    it('generates unique filenames for duplicate uploads', function () {
        $file1 = UploadedFile::fake()->image('same-name.jpg');
        $file2 = UploadedFile::fake()->image('same-name.jpg');

        $images1 = $this->service->uploadToProduct($this->product, [$file1]);
        $images2 = $this->service->uploadToProduct($this->product, [$file2]);

        expect($images1->first()->path)->not->toBe($images2->first()->path);
    });
});

describe('Multiple File Upload', function () {
    it('processes multiple files in single batch', function () {
        $files = [
            UploadedFile::fake()->image('batch1.jpg'),
            UploadedFile::fake()->image('batch2.png'),
            UploadedFile::fake()->image('batch3.webp'),
        ];

        $images = $this->service->uploadToProduct($this->product, $files);

        expect($images)->toHaveCount(3);
        expect($images->pluck('filename')->toArray())->toBe(['batch1.jpg', 'batch2.png', 'batch3.webp']);
    });

    it('stops processing on validation failure', function () {
        $files = [
            UploadedFile::fake()->image('valid.jpg'),
            UploadedFile::fake()->create('invalid.txt', 100, 'text/plain'), // Invalid file
        ];

        expect(fn () => $this->service->uploadToProduct($this->product, $files))
            ->toThrow(InvalidArgumentException::class);

        // No images should be created
        expect($this->product->images()->count())->toBe(0);
    });
});

describe('DAM Standalone Upload', function () {
    it('can upload standalone images to DAM', function () {
        $files = [
            UploadedFile::fake()->image('standalone1.jpg'),
            UploadedFile::fake()->image('standalone2.png'),
        ];

        $metadata = [
            'title' => 'Test Standalone Images',
            'alt_text' => 'Test images for DAM',
            'description' => 'These are test images uploaded to DAM',
            'folder' => 'test-folder',
            'tags' => ['test', 'standalone', 'dam'],
        ];

        $images = $this->service->uploadStandalone($files, $metadata);

        expect($images)->toHaveCount(2);
        
        $firstImage = $images->first();
        expect($firstImage->title)->toBe('Test Standalone Images')
            ->and($firstImage->alt_text)->toBe('Test images for DAM')
            ->and($firstImage->description)->toBe('These are test images uploaded to DAM')
            ->and($firstImage->folder)->toBe('test-folder')
            ->and($firstImage->tags)->toBe(['test', 'standalone', 'dam'])
            ->and($firstImage->created_by_user_id)->toBe($this->user->id)
            ->and($firstImage->imageable_type)->toBeNull()
            ->and($firstImage->imageable_id)->toBeNull()
            ->and($firstImage->is_primary)->toBeFalse();
    });

    it('handles minimal metadata for standalone uploads', function () {
        $files = [UploadedFile::fake()->image('minimal.jpg')];
        
        $metadata = [
            'folder' => 'minimal-test',
        ];

        $images = $this->service->uploadStandalone($files, $metadata);
        $image = $images->first();

        expect($image->title)->toBe('minimal') // From filename
            ->and($image->alt_text)->toBeNull()
            ->and($image->description)->toBeNull()
            ->and($image->folder)->toBe('minimal-test')
            ->and($image->tags)->toBe([])
            ->and($image->created_by_user_id)->toBe($this->user->id);
    });

    it('processes tags as comma-separated string', function () {
        $files = [UploadedFile::fake()->image('tagged.jpg')];
        
        $metadata = [
            'folder' => 'test',
            'tags' => 'tag1, tag2, tag3',
        ];

        $images = $this->service->uploadStandalone($files, $metadata);
        $image = $images->first();

        expect($image->tags)->toBe(['tag1', 'tag2', 'tag3']);
    });

    it('validates standalone upload files', function () {
        $files = [UploadedFile::fake()->create('invalid.txt', 100, 'text/plain')];
        
        expect(fn () => $this->service->uploadStandalone($files))
            ->toThrow(InvalidArgumentException::class, 'Invalid file type');
    });
});

describe('DAM Model-Attached Upload', function () {
    it('automatically determines folder for product uploads', function () {
        $files = [UploadedFile::fake()->image('product.jpg')];
        
        $images = $this->service->uploadToProduct($this->product, $files);
        $image = $images->first();

        expect($image->folder)->toBe('products')
            ->and($image->created_by_user_id)->toBe($this->user->id);
    });

    it('automatically determines folder for variant uploads', function () {
        $files = [UploadedFile::fake()->image('variant.jpg')];
        
        $images = $this->service->uploadToVariant($this->variant, $files);
        $image = $images->first();

        expect($image->folder)->toBe('variants')
            ->and($image->created_by_user_id)->toBe($this->user->id);
    });

    it('sets default title from filename for model uploads', function () {
        $files = [UploadedFile::fake()->image('my-product-image.jpg')];
        
        $images = $this->service->uploadToProduct($this->product, $files);
        $image = $images->first();

        expect($image->title)->toBe('my-product-image'); // Without extension
    });
});

describe('DAM Integration Features', function () {
    it('handles unauthenticated users gracefully', function () {
        auth()->logout();
        
        $files = [UploadedFile::fake()->image('test.jpg')];
        
        $images = $this->service->uploadToProduct($this->product, $files);
        $image = $images->first();

        expect($image->created_by_user_id)->toBeNull();
    });

    it('works with existing upload methods', function () {
        // Ensure backward compatibility
        $files = [UploadedFile::fake()->image('legacy.jpg')];
        
        $images = $this->service->uploadToProduct($this->product, $files);
        
        expect($images)->toHaveCount(1)
            ->and($images->first())->toBeInstanceOf(Image::class)
            ->and($this->product->images()->count())->toBe(1);
    });
});
