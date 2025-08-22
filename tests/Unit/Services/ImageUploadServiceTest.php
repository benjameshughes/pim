<?php

use App\Services\ImageUploadService;
use App\Models\Image;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('Image Upload Service', function () {
    beforeEach(function () {
        Storage::fake('public');
        $this->service = app(ImageUploadService::class);
    });

    it('can upload single image', function () {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);

        $image = $this->service->uploadImage($file);

        expect($image)->toBeInstanceOf(Image::class);
        expect($image->filename)->toContain('test');
        expect($image->width)->toBe(800);
        expect($image->height)->toBe(600);
        expect($image->mime_type)->toBe('image/jpeg');
        Storage::disk('public')->assertExists($image->path);
    });

    it('generates thumbnails on upload', function () {
        $file = UploadedFile::fake()->image('test.jpg', 1200, 800);

        $image = $this->service->uploadImage($file, ['generateThumbnails' => true]);

        expect($image->thumbnail_path)->not->toBeNull();
        Storage::disk('public')->assertExists($image->thumbnail_path);
    });

    it('can resize image on upload', function () {
        $file = UploadedFile::fake()->image('test.jpg', 2000, 1500);

        $image = $this->service->uploadImage($file, [
            'maxWidth' => 1000,
            'maxHeight' => 750,
        ]);

        expect($image->width)->toBeLessThanOrEqual(1000);
        expect($image->height)->toBeLessThanOrEqual(750);
    });

    it('validates file size', function () {
        $file = UploadedFile::fake()->image('large.jpg')->size(10240); // 10MB

        expect(fn() => $this->service->uploadImage($file, ['maxSize' => 5120]))
            ->toThrow(InvalidArgumentException::class);
    });

    it('can upload multiple images', function () {
        $files = [
            UploadedFile::fake()->image('test1.jpg'),
            UploadedFile::fake()->image('test2.png'),
        ];

        $images = $this->service->uploadMultiple($files);

        expect($images)->toHaveCount(2);
        expect(Image::count())->toBe(2);
    });

    it('generates unique filenames', function () {
        $file1 = UploadedFile::fake()->image('same-name.jpg');
        $file2 = UploadedFile::fake()->image('same-name.jpg');

        $image1 = $this->service->uploadImage($file1);
        $image2 = $this->service->uploadImage($file2);

        expect($image1->path)->not->toBe($image2->path);
        expect($image1->filename)->not->toBe($image2->filename);
    });

    it('extracts image metadata', function () {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);

        $image = $this->service->uploadImage($file);

        expect($image->file_size)->toBeGreaterThan(0);
        expect($image->width)->toBe(800);
        expect($image->height)->toBe(600);
        expect($image->mime_type)->toBe('image/jpeg');
    });
});