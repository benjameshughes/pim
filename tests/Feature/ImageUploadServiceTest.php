<?php

use App\Models\Image;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(ImageUploadService::class);
    Storage::fake('images');
});

describe('ImageUploadService', function () {

    test('can upload single image', function () {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600)->size(100);

        $image = $this->service->upload($file);

        expect($image)->toBeInstanceOf(Image::class);
        expect($image->filename)->toMatch('/^[0-9a-f-]{36}\.jpg$/'); // UUID.jpg format
        expect($image->mime_type)->toBe('image/jpeg');
        expect($image->size)->toBe(100 * 1024); // 100KB in bytes

        // Check file was stored
        Storage::disk('images')->assertExists($image->filename);
    });

    test('can upload image with metadata', function () {
        $file = UploadedFile::fake()->image('test.png');

        $metadata = [
            'title' => 'Test Image',
            'alt_text' => 'Alt text',
            'description' => 'Description',
            'folder' => 'products',
            'tags' => ['red', 'featured'],
        ];

        $image = $this->service->upload($file, $metadata);

        expect($image->title)->toBe('Test Image');
        expect($image->alt_text)->toBe('Alt text');
        expect($image->description)->toBe('Description');
        expect($image->folder)->toBe('products');
        expect($image->tags)->toBe(['red', 'featured']);
    });

    test('can upload multiple images', function () {
        $files = [
            UploadedFile::fake()->image('test1.jpg'),
            UploadedFile::fake()->image('test2.png'),
            UploadedFile::fake()->image('test3.gif'),
        ];

        $metadata = ['folder' => 'batch-upload'];

        $images = $this->service->uploadMultiple($files, $metadata);

        expect($images)->toHaveCount(3);
        expect($images[0])->toBeInstanceOf(Image::class);
        expect($images[1])->toBeInstanceOf(Image::class);
        expect($images[2])->toBeInstanceOf(Image::class);

        // All should have metadata applied
        expect($images[0]->folder)->toBe('batch-upload');
        expect($images[1]->folder)->toBe('batch-upload');
        expect($images[2]->folder)->toBe('batch-upload');

        // Check all files were stored
        foreach ($images as $image) {
            Storage::disk('images')->assertExists($image->filename);
        }
    });

    test('generates unique filenames for uploads', function () {
        $file1 = UploadedFile::fake()->image('same-name.jpg');
        $file2 = UploadedFile::fake()->image('same-name.jpg');

        $image1 = $this->service->upload($file1);
        $image2 = $this->service->upload($file2);

        expect($image1->filename)->not->toBe($image2->filename);
        expect($image1->filename)->toMatch('/^[0-9a-f-]{36}\.jpg$/');
        expect($image2->filename)->toMatch('/^[0-9a-f-]{36}\.jpg$/');
    });

});

describe('ImageUploadService File Validation', function () {

    test('rejects files that are too large', function () {
        $file = UploadedFile::fake()->image('huge.jpg')->size(15 * 1024); // 15MB

        expect(fn () => $this->service->upload($file))
            ->toThrow(\InvalidArgumentException::class, 'File too large');
    });

    test('rejects non-image files', function () {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        expect(fn () => $this->service->upload($file))
            ->toThrow(\InvalidArgumentException::class, 'Invalid file type');
    });

    test('accepts valid image types', function () {
        $jpegFile = UploadedFile::fake()->image('test.jpg');
        $pngFile = UploadedFile::fake()->image('test.png');
        $gifFile = UploadedFile::fake()->create('test.gif', 100, 'image/gif');
        $webpFile = UploadedFile::fake()->create('test.webp', 100, 'image/webp');

        $jpegImage = $this->service->upload($jpegFile);
        $pngImage = $this->service->upload($pngFile);
        $gifImage = $this->service->upload($gifFile);
        $webpImage = $this->service->upload($webpFile);

        expect($jpegImage)->toBeInstanceOf(Image::class);
        expect($pngImage)->toBeInstanceOf(Image::class);
        expect($gifImage)->toBeInstanceOf(Image::class);
        expect($webpImage)->toBeInstanceOf(Image::class);
    });

});

describe('ImageUploadService Deletion', function () {

    test('can delete image and file', function () {
        $file = UploadedFile::fake()->image('test.jpg');
        $image = $this->service->upload($file);

        // Verify file exists
        Storage::disk('images')->assertExists($image->filename);
        expect(Image::find($image->id))->not->toBeNull();

        $result = $this->service->delete($image);

        expect($result)->toBeTrue();
        Storage::disk('images')->assertMissing($image->filename);
        expect(Image::find($image->id))->toBeNull();
    });

    test('handles deletion when file does not exist', function () {
        $image = Image::factory()->create(['filename' => 'non-existent.jpg']);

        $result = $this->service->delete($image);

        expect($result)->toBeTrue();
        expect(Image::find($image->id))->toBeNull();
    });

    test('handles deletion when filename is null', function () {
        $image = Image::factory()->create(['filename' => null]);

        $result = $this->service->delete($image);

        expect($result)->toBeTrue();
        expect(Image::find($image->id))->toBeNull();
    });

});

describe('ImageUploadService Integration', function () {

    test('generated URL is accessible', function () {
        $file = UploadedFile::fake()->image('test.jpg');

        $image = $this->service->upload($file);

        expect($image->url)->toBeString();
        expect($image->url)->toContain($image->filename);

        // In fake storage, URL should contain the filename
        expect($image->url)->toMatch('/\/[0-9a-f-]{36}\.jpg$/');
    });

    test('preserves file extension correctly', function () {
        $jpgFile = UploadedFile::fake()->image('test.jpg');
        $pngFile = UploadedFile::fake()->image('test.png');
        $gifFile = UploadedFile::fake()->create('test.gif', 100, 'image/gif');

        $jpgImage = $this->service->upload($jpgFile);
        $pngImage = $this->service->upload($pngFile);
        $gifImage = $this->service->upload($gifFile);

        expect($jpgImage->filename)->toEndWith('.jpg');
        expect($pngImage->filename)->toEndWith('.png');
        expect($gifImage->filename)->toEndWith('.gif');
    });

    test('applies default values correctly', function () {
        $file = UploadedFile::fake()->image('test.jpg');

        $image = $this->service->upload($file);

        expect($image->is_primary)->toBeFalse();
        expect($image->sort_order)->toBe(0);
        expect($image->title)->toBeNull();
        expect($image->alt_text)->toBeNull();
        expect($image->description)->toBeNull();
        expect($image->folder)->toBeNull();
        expect($image->tags)->toBe([]);
    });

});
