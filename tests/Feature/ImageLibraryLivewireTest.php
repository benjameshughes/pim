<?php

use App\Livewire\Images\ImageLibrary;
use App\Models\Image;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Storage::fake('images');
});

describe('ImageLibrary Livewire Component', function () {

    test('component renders successfully', function () {
        Livewire::test(ImageLibrary::class)
            ->assertStatus(200);
    });

    test('component displays images', function () {
        $image1 = Image::factory()->create(['title' => 'Test Image 1']);
        $image2 = Image::factory()->create(['title' => 'Test Image 2']);

        Livewire::test(ImageLibrary::class)
            ->assertStatus(200)
            ->assertSee('Test Image 1')
            ->assertSee('Test Image 2');
    });

    test('component handles empty state', function () {
        Livewire::test(ImageLibrary::class)
            ->assertStatus(200)
            ->assertDontSee('Test Image');
    });

});

describe('ImageLibrary File Upload', function () {

    test('can upload single image', function () {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);

        Livewire::test(ImageLibrary::class)
            ->set('newImages', [$file])
            ->call('uploadImages')
            ->assertStatus(200)
            ->assertDispatched('notify');

        expect(Image::count())->toBe(1);
        $image = Image::first();
        expect($image->mime_type)->toBe('image/jpeg');
        Storage::disk('images')->assertExists($image->filename);
    });

    test('can upload multiple images', function () {
        $files = [
            UploadedFile::fake()->image('test1.jpg'),
            UploadedFile::fake()->image('test2.png'),
            UploadedFile::fake()->image('test3.gif'),
        ];

        Livewire::test(ImageLibrary::class)
            ->set('newImages', $files)
            ->call('uploadImages')
            ->assertStatus(200)
            ->assertDispatched('notify');

        expect(Image::count())->toBe(3);

        foreach (Image::all() as $image) {
            Storage::disk('images')->assertExists($image->filename);
        }
    });

    test('applies metadata to uploaded images', function () {
        $file = UploadedFile::fake()->image('test.jpg');

        Livewire::test(ImageLibrary::class)
            ->set('newImages', [$file])
            ->set('newImageFolder', 'products')
            ->set('newImageTags', ['red', 'featured'])
            ->call('uploadImages')
            ->assertStatus(200);

        $image = Image::first();
        expect($image->folder)->toBe('products');
        expect($image->tags)->toBe(['red', 'featured']);
    });

    test('validates upload file types', function () {
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        Livewire::test(ImageLibrary::class)
            ->set('newImages', [$invalidFile])
            ->call('uploadImages')
            ->assertHasErrors('newImages.*');
    });

    test('validates upload file size', function () {
        $largeFile = UploadedFile::fake()->image('huge.jpg')->size(15 * 1024); // 15MB

        Livewire::test(ImageLibrary::class)
            ->set('newImages', [$largeFile])
            ->call('uploadImages')
            ->assertHasErrors('newImages.*');
    });

    test('resets form after successful upload', function () {
        $file = UploadedFile::fake()->image('test.jpg');

        $component = Livewire::test(ImageLibrary::class)
            ->set('newImages', [$file])
            ->set('newImageFolder', 'products')
            ->set('newImageTags', ['test'])
            ->call('uploadImages')
            ->assertStatus(200);

        expect($component->get('newImages'))->toBe([]);
        expect($component->get('newImageFolder'))->toBe('');
        expect($component->get('newImageTags'))->toBe([]);
    });

});

describe('ImageLibrary Image Management', function () {

    test('can delete image', function () {
        $image = Image::factory()->create();
        Storage::disk('images')->put($image->filename, 'fake content');

        Livewire::test(ImageLibrary::class)
            ->call('deleteImage', $image->id)
            ->assertStatus(200)
            ->assertDispatched('notify');

        expect(Image::find($image->id))->toBeNull();
        Storage::disk('images')->assertMissing($image->filename);
    });

    test('handles deletion of non-existent image gracefully', function () {
        Livewire::test(ImageLibrary::class)
            ->call('deleteImage', 999)
            ->assertStatus(200);

        // Should not throw error or dispatch notification
    });

});

describe('ImageLibrary Tag Management', function () {

    test('can add tags for new uploads', function () {
        $component = Livewire::test(ImageLibrary::class)
            ->set('newTagInput', 'new-tag')
            ->call('addTag')
            ->assertStatus(200);

        expect($component->get('newImageTags'))->toBe(['new-tag']);
        expect($component->get('newTagInput'))->toBe('');
    });

    test('does not add duplicate tags', function () {
        $component = Livewire::test(ImageLibrary::class)
            ->set('newImageTags', ['existing-tag'])
            ->set('newTagInput', 'existing-tag')
            ->call('addTag')
            ->assertStatus(200);

        expect($component->get('newImageTags'))->toBe(['existing-tag']);
    });

    test('can remove tags', function () {
        $component = Livewire::test(ImageLibrary::class)
            ->set('newImageTags', ['tag1', 'tag2', 'tag3'])
            ->call('removeTag', 'tag2')
            ->assertStatus(200);

        expect($component->get('newImageTags'))->toBe(['tag1', 'tag3']);
    });

    test('ignores empty tag input', function () {
        $component = Livewire::test(ImageLibrary::class)
            ->set('newTagInput', '')
            ->call('addTag')
            ->assertStatus(200);

        expect($component->get('newImageTags'))->toBe([]);
    });

    test('trims whitespace from tags', function () {
        $component = Livewire::test(ImageLibrary::class)
            ->set('newTagInput', '  spaced-tag  ')
            ->call('addTag')
            ->assertStatus(200);

        expect($component->get('newImageTags'))->toBe(['spaced-tag']);
    });

});

describe('ImageLibrary Search and Filtering', function () {

    beforeEach(function () {
        $this->image1 = Image::factory()->create([
            'title' => 'Red Product Image',
            'folder' => 'products',
            'tags' => ['red', 'featured'],
        ]);

        $this->image2 = Image::factory()->create([
            'title' => 'Blue Banner Image',
            'folder' => 'banners',
            'tags' => ['blue', 'sale'],
        ]);

        $this->image3 = Image::factory()->create([
            'title' => 'Green Product',
            'folder' => 'products',
            'tags' => ['green'],
        ]);
    });

    test('can search images by title', function () {
        $component = Livewire::test(ImageLibrary::class)
            ->set('search', 'Red Product')
            ->assertStatus(200);

        $images = $component->get('images');
        expect($images->items())->toHaveCount(1);
        expect($images->items()[0]->id)->toBe($this->image1->id);
    });

    test('can filter by folder', function () {
        $component = Livewire::test(ImageLibrary::class)
            ->set('selectedFolder', 'products')
            ->assertStatus(200);

        $images = $component->get('images');
        expect($images->items())->toHaveCount(2);
        expect(collect($images->items())->pluck('id'))->toContain($this->image1->id, $this->image3->id);
    });

    test('can filter by tag', function () {
        $component = Livewire::test(ImageLibrary::class)
            ->set('selectedTag', 'red')
            ->assertStatus(200);

        $images = $component->get('images');
        expect($images->items())->toHaveCount(1);
        expect($images->items()[0]->id)->toBe($this->image1->id);
    });

    test('can combine search and filters', function () {
        $component = Livewire::test(ImageLibrary::class)
            ->set('search', 'Product')
            ->set('selectedFolder', 'products')
            ->assertStatus(200);

        $images = $component->get('images');
        expect($images->items())->toHaveCount(2);
    });

    test('can clear all filters', function () {
        $component = Livewire::test(ImageLibrary::class)
            ->set('search', 'test')
            ->set('selectedFolder', 'products')
            ->set('selectedTag', 'red')
            ->call('clearFilters')
            ->assertStatus(200);

        expect($component->get('search'))->toBe('');
        expect($component->get('selectedFolder'))->toBe('');
        expect($component->get('selectedTag'))->toBe('');
    });

});

describe('ImageLibrary Data Properties', function () {

    beforeEach(function () {
        Image::factory()->create(['folder' => 'products', 'tags' => ['red', 'blue']]);
        Image::factory()->create(['folder' => 'banners', 'tags' => ['green', 'red']]);
        Image::factory()->create(['folder' => null, 'tags' => ['blue']]);
    });

    test('folders property returns unique folders', function () {
        $component = Livewire::test(ImageLibrary::class);

        $folders = $component->get('folders');
        expect($folders->toArray())->toBe(['banners', 'products']);
    });

    test('tags property returns unique tags', function () {
        $component = Livewire::test(ImageLibrary::class);

        $tags = $component->get('tags');
        expect($tags->toArray())->toBe(['blue', 'green', 'red']);
    });

    test('stats property returns correct counts', function () {
        $component = Livewire::test(ImageLibrary::class);

        $stats = $component->get('stats');
        expect($stats['total'])->toBe(3);
        expect($stats['unattached'])->toBe(3); // None attached to products/variants
        expect($stats['folders'])->toBe(2); // products, banners (null excluded)
    });

});

describe('ImageLibrary Pagination', function () {

    test('respects per page setting', function () {
        Image::factory()->count(30)->create();

        $component = Livewire::test(ImageLibrary::class)
            ->set('perPage', 10)
            ->assertStatus(200);

        $images = $component->get('images');
        expect($images->items())->toHaveCount(10);
        expect($images->total())->toBe(30);
    });

    test('resets page after upload', function () {
        Image::factory()->count(30)->create();

        $file = UploadedFile::fake()->image('test.jpg');

        $component = Livewire::test(ImageLibrary::class)
            ->set('perPage', 10)
            ->set('page', 3) // Go to page 3
            ->set('newImages', [$file])
            ->call('uploadImages')
            ->assertStatus(200);

        // Should reset to page 1 after upload
        expect($component->get('page'))->toBe(1);
    });

});
