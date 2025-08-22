<?php

use App\Models\Image;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('DAM System - Fixed Version', function () {
    beforeEach(function () {
        Storage::fake('images'); // Fake R2 storage
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can create image with correct database schema', function () {
        $image = Image::create([
            'filename' => 'test-image.jpg',
            'path' => 'images/test-image.jpg',
            'url' => 'https://example.com/test-image.jpg',
            'size' => 1024, // Now matches the migration
            'mime_type' => 'image/jpeg',
            'title' => 'Test Image',
            'folder' => 'testing',
            'created_by_user_id' => $this->user->id, // Now matches migration
            'is_primary' => false,
            'sort_order' => 0,
        ]);

        expect(Image::count())->toBe(1);
        expect($image->filename)->toBe('test-image.jpg');
        expect($image->size)->toBe(1024);
        expect($image->created_by_user_id)->toBe($this->user->id);
    });

    it('can upload image through service', function () {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);
        
        $service = new ImageUploadService();
        $images = $service->uploadStandalone([$file], [
            'title' => 'Service Test',
            'folder' => 'service-test',
        ]);

        expect($images)->toHaveCount(1);
        expect(Image::count())->toBe(1);
        
        $image = Image::first();
        expect($image->title)->toBe('Service Test');
        expect($image->folder)->toBe('service-test');
        expect($image->created_by_user_id)->toBe($this->user->id);
    });

    it('can load DAM dashboard without errors', function () {
        // Create some test images
        Image::factory()->count(3)->create();

        $component = Livewire::test('d-a-m.image-library');
        
        $component->assertOk();
        expect($component->get('images'))->toHaveCount(3);
    });

    it('can upload through Livewire component', function () {
        $file = UploadedFile::fake()->image('livewire-test.jpg', 600, 400);
        
        $component = Livewire::test('d-a-m.image-library')
            ->set('newImages', [$file])
            ->set('uploadMetadata', [
                'title' => 'Livewire Test',
                'folder' => 'livewire-testing',
                'tags' => ['test', 'upload'],
            ])
            ->call('uploadImages');

        expect(Image::count())->toBe(1);
        $image = Image::first();
        expect($image->title)->toBe('Livewire Test');
        expect($image->folder)->toBe('livewire-testing');
        expect($image->tags)->toBe(['test', 'upload']);
    });

    it('can search and filter images in DAM', function () {
        Image::factory()->create(['title' => 'Red Product Image', 'folder' => 'products']);
        Image::factory()->create(['title' => 'Blue Product Image', 'folder' => 'products']); 
        Image::factory()->create(['title' => 'Marketing Banner', 'folder' => 'marketing']);

        $component = Livewire::test('d-a-m.image-library');
        
        // Test search
        $component->set('search', 'Product')
                  ->call('render');
        expect($component->get('images'))->toHaveCount(2);

        // Test folder filter
        $component->set('search', '')
                  ->set('selectedFolder', 'marketing')
                  ->call('render');
        expect($component->get('images'))->toHaveCount(1);
    });

    it('can handle image metadata correctly', function () {
        $image = Image::factory()->create([
            'title' => 'Test Image',
            'alt_text' => 'Test alt text',
            'description' => 'Test description',
            'tags' => ['product', 'test'],
        ]);

        expect($image->display_title)->toBe('Test Image');
        expect($image->tags)->toBe(['product', 'test']);
        expect($image->hasTag('product'))->toBeTrue();
        expect($image->hasTag('nonexistent'))->toBeFalse();
    });
});