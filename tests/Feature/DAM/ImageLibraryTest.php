<?php

use App\Models\Image;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('Image Library Management', function () {
    beforeEach(function () {
        Storage::fake('public');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can display image library', function () {
        Image::factory()->count(5)->create();

        $component = Livewire::test('d-a-m.image-library');

        $component->assertSee('Image Library');
        expect($component->get('images'))->toHaveCount(5);
    });

    it('can upload single image', function () {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);

        $component = Livewire::test('d-a-m.image-library')
            ->set('uploadedFiles', [$file])
            ->call('uploadImages');

        expect(Image::count())->toBe(1);
        $image = Image::first();
        expect($image->filename)->toContain('test');
        expect($image->mime_type)->toBe('image/jpeg');
        Storage::disk('public')->assertExists($image->path);
    });

    it('can upload multiple images', function () {
        $files = [
            UploadedFile::fake()->image('test1.jpg'),
            UploadedFile::fake()->image('test2.png'),
            UploadedFile::fake()->image('test3.jpg'),
        ];

        $component = Livewire::test('d-a-m.image-library')
            ->set('uploadedFiles', $files)
            ->call('uploadImages');

        expect(Image::count())->toBe(3);
        expect(Image::where('mime_type', 'image/jpeg')->count())->toBe(2);
        expect(Image::where('mime_type', 'image/png')->count())->toBe(1);
    });

    it('validates image file types', function () {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $component = Livewire::test('d-a-m.image-library')
            ->set('uploadedFiles', [$file])
            ->call('uploadImages')
            ->assertHasErrors(['uploadedFiles.0']);

        expect(Image::count())->toBe(0);
    });

    it('can delete image', function () {
        $image = Image::factory()->create();
        Storage::disk('public')->put($image->path, 'fake content');

        $component = Livewire::test('d-a-m.image-library')
            ->call('deleteImage', $image->id);

        expect(Image::count())->toBe(0);
        Storage::disk('public')->assertMissing($image->path);
    });

    it('can search images', function () {
        Image::factory()->create(['filename' => 'product-red-shirt.jpg']);
        Image::factory()->create(['filename' => 'product-blue-shirt.jpg']);
        Image::factory()->create(['filename' => 'banner-image.jpg']);

        $component = Livewire::test('d-a-m.image-library')
            ->set('search', 'shirt')
            ->call('render');

        expect($component->get('images'))->toHaveCount(2);
    });

    it('can filter images by type', function () {
        Image::factory()->create(['mime_type' => 'image/jpeg']);
        Image::factory()->create(['mime_type' => 'image/png']);
        Image::factory()->create(['mime_type' => 'image/webp']);

        $component = Livewire::test('d-a-m.image-library')
            ->set('filterType', 'image/jpeg')
            ->call('render');

        expect($component->get('images'))->toHaveCount(1);
    });
});