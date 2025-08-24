<?php

use App\Livewire\DAM\ImageLibrary;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('image library component mounts successfully', function () {
    Livewire::test(ImageLibrary::class)
        ->assertSet('search', '')
        ->assertSet('selectedFolder', '')
        ->assertSet('selectedTags', [])
        ->assertSet('filterBy', 'all')
        ->assertSet('sortBy', 'created_at')
        ->assertSet('sortDirection', 'desc')
        ->assertSet('showUploadModal', false)
        ->assertSet('showDeleteConfirmModal', false);
});

test('can search images', function () {
    Image::factory()->create(['title' => 'Test Image']);
    Image::factory()->create(['title' => 'Other Image']);
    
    $component = Livewire::test(ImageLibrary::class)
        ->set('search', 'Test');
    
    $images = $component->instance()->images;
    expect($images->count())->toBe(1);
});

test('can filter by folder', function () {
    Image::factory()->create(['folder' => 'folder1']);
    Image::factory()->create(['folder' => 'folder2']);
    Image::factory()->create(['folder' => 'folder1']);
    
    $component = Livewire::test(ImageLibrary::class)
        ->set('selectedFolder', 'folder1');
    
    $images = $component->instance()->images;
    expect($images->count())->toBe(2);
});

test('can filter by tags', function () {
    Image::factory()->create(['tags' => ['tag1', 'tag2']]);
    Image::factory()->create(['tags' => ['tag2', 'tag3']]);
    Image::factory()->create(['tags' => ['tag3', 'tag4']]);
    
    $component = Livewire::test(ImageLibrary::class)
        ->set('selectedTags', ['tag2']);
    
    $images = $component->instance()->images;
    expect($images->count())->toBe(2);
});

test('can filter by attachment status', function () {
    $attachedImage = Image::factory()->create();
    $unattachedImage = Image::factory()->create();
    
    $product = Product::factory()->create();
    $attachedImage->products()->attach($product->id);
    
    // Test attached filter
    $component = Livewire::test(ImageLibrary::class)
        ->set('filterBy', 'attached');
    $images = $component->instance()->images;
    expect($images->count())->toBe(1);
    
    // Test unattached filter  
    $component->set('filterBy', 'unattached');
    $images = $component->instance()->images;
    expect($images->count())->toBe(1);
});

test('can filter by user', function () {
    $otherUser = User::factory()->create();
    
    Image::factory()->create(['created_by' => $this->user->id]);
    Image::factory()->create(['created_by' => $otherUser->id]);
    Image::factory()->create(['created_by' => $this->user->id]);
    
    $component = Livewire::test(ImageLibrary::class)
        ->set('filterBy', 'mine');
    
    $images = $component->instance()->images;
    expect($images->count())->toBe(2);
});

test('can sort images', function () {
    $image1 = Image::factory()->create(['title' => 'A Image']);
    $image2 = Image::factory()->create(['title' => 'B Image']);
    
    $component = Livewire::test(ImageLibrary::class)
        ->set('sortBy', 'title')
        ->set('sortDirection', 'asc');
    
    $images = $component->instance()->images;
    expect($images->first()->id)->toBe($image1->id);
});

test('folders property returns unique folders', function () {
    Image::factory()->create(['folder' => 'folder1']);
    Image::factory()->create(['folder' => 'folder2']);
    Image::factory()->create(['folder' => 'folder1']); // duplicate
    Image::factory()->create(['folder' => null]); // should be excluded
    
    $component = Livewire::test(ImageLibrary::class);
    $folders = $component->instance()->folders;
    
    expect($folders)->toHaveCount(2)
        ->and($folders)->toContain('folder1', 'folder2');
});

test('availableTags property returns unique tags', function () {
    Image::factory()->create(['tags' => ['tag1', 'tag2']]);
    Image::factory()->create(['tags' => ['tag2', 'tag3']]);
    Image::factory()->create(['tags' => null]); // should be excluded
    
    $component = Livewire::test(ImageLibrary::class);
    $tags = $component->instance()->availableTags;
    
    expect($tags)->toHaveCount(3)
        ->and($tags)->toContain('tag1', 'tag2', 'tag3');
});

test('can open upload modal', function () {
    Livewire::test(ImageLibrary::class)
        ->call('openUploadModal')
        ->assertSet('showUploadModal', true);
});

test('can copy image URL', function () {
    Livewire::test(ImageLibrary::class)
        ->call('copyUrl', 'https://example.com/image.jpg')
        ->assertDispatched('success', 'Image URL copied to clipboard!');
});

test('can delete image', function () {
    $image = Image::factory()->create();
    
    Livewire::test(ImageLibrary::class)
        ->call('deleteImage', $image->id)
        ->assertDispatched('notify', fn ($event) => 
            $event['type'] === 'success' && 
            str_contains($event['message'], 'Image deleted successfully!')
        );
        
    expect(Image::find($image->id))->toBeNull();
});

test('can select all images', function () {
    $images = Image::factory()->count(3)->create();
    
    Livewire::test(ImageLibrary::class)
        ->set('selectAll', true)
        ->assertCount('selectedImages', 3);
});

test('can deselect all images', function () {
    $images = Image::factory()->count(3)->create();
    
    Livewire::test(ImageLibrary::class)
        ->set('selectAll', true)
        ->set('selectAll', false)
        ->assertSet('selectedImages', []);
});

test('allSelected returns correct status', function () {
    $images = Image::factory()->count(3)->create();
    
    $component = Livewire::test(ImageLibrary::class)
        ->set('selectedImages', [1, 2, 3]);
    
    expect($component->instance()->allSelected())->toBe(true);
    
    $component->set('selectedImages', [1, 2]);
    expect($component->instance()->allSelected())->toBe(false);
});

test('someSelected returns correct status', function () {
    $images = Image::factory()->count(3)->create();
    
    $component = Livewire::test(ImageLibrary::class)
        ->set('selectedImages', [1, 2]);
    
    expect($component->instance()->someSelected())->toBe(true);
    
    $component->set('selectedImages', []);
    expect($component->instance()->someSelected())->toBe(false);
    
    $component->set('selectedImages', [1, 2, 3]);
    expect($component->instance()->someSelected())->toBe(false);
});

test('can apply bulk action', function () {
    $images = Image::factory()->count(3)->create(['folder' => 'old-folder']);
    
    Livewire::test(ImageLibrary::class)
        ->set('selectedImages', $images->pluck('id')->toArray())
        ->set('bulkAction', [
            'type' => 'move_folder',
            'folder' => 'new-folder'
        ])
        ->call('applyBulkAction')
        ->assertDispatched('notify', fn ($event) => 
            $event['type'] === 'success' && 
            str_contains($event['message'], 'Bulk action applied to 3 image(s)!')
        );
        
    foreach ($images as $image) {
        $image->refresh();
        expect($image->folder)->toBe('new-folder');
    }
});

test('can bulk add tags', function () {
    $images = Image::factory()->count(2)->create(['tags' => ['existing']]);
    
    Livewire::test(ImageLibrary::class)
        ->set('selectedImages', $images->pluck('id')->toArray())
        ->set('bulkAction', [
            'type' => 'add_tags',
            'tags_to_add' => 'new1, new2'
        ])
        ->call('applyBulkAction');
        
    foreach ($images as $image) {
        $image->refresh();
        expect($image->tags)->toContain('existing', 'new1', 'new2');
    }
});

test('can bulk remove tags', function () {
    $images = Image::factory()->count(2)->create(['tags' => ['keep', 'remove', 'other']]);
    
    Livewire::test(ImageLibrary::class)
        ->set('selectedImages', $images->pluck('id')->toArray())
        ->set('bulkAction', [
            'type' => 'remove_tags',
            'tags_to_remove' => 'remove, other'
        ])
        ->call('applyBulkAction');
        
    foreach ($images as $image) {
        $image->refresh();
        expect($image->tags)->toBe(['keep']);
    }
});

test('can handle bulk delete with confirmation', function () {
    $images = Image::factory()->count(2)->create();
    
    // First test the confirmation modal
    Livewire::test(ImageLibrary::class)
        ->call('handleFloatingAction', [
            'action' => ['type' => 'delete'],
            'items' => $images->pluck('id')->toArray()
        ])
        ->assertSet('showDeleteConfirmModal', true)
        ->assertSet('pendingDeleteAction.action.type', 'delete');
    
    // Then confirm the delete
    $component = Livewire::test(ImageLibrary::class)
        ->set('pendingDeleteAction', [
            'action' => ['type' => 'delete'],
            'items' => $images->pluck('id')->toArray()
        ])
        ->call('confirmBulkDelete')
        ->assertDispatched('notify', fn ($event) => 
            $event['type'] === 'success' && 
            str_contains($event['message'], 'Successfully deleted 2 image')
        );
        
    expect(Image::whereIn('id', $images->pluck('id'))->count())->toBe(0);
});

test('can cancel bulk delete', function () {
    $images = Image::factory()->count(2)->create();
    
    Livewire::test(ImageLibrary::class)
        ->set('pendingDeleteAction', [
            'action' => ['type' => 'delete'],
            'items' => $images->pluck('id')->toArray()
        ])
        ->set('showDeleteConfirmModal', true)
        ->call('cancelBulkDelete')
        ->assertSet('pendingDeleteAction', [])
        ->assertSet('showDeleteConfirmModal', false);
});

test('can clear selection', function () {
    $images = Image::factory()->count(3)->create();
    
    Livewire::test(ImageLibrary::class)
        ->set('selectedImages', $images->pluck('id')->toArray())
        ->call('clearSelection')
        ->assertSet('selectedImages', []);
});

test('search triggers page reset', function () {
    Image::factory()->count(30)->create(); // More than one page
    
    $component = Livewire::test(ImageLibrary::class);
    
    // Go to page 2
    $component->set('page', 2);
    
    // Search should reset to page 1
    $component->set('search', 'test');
    
    // Page should be reset (can't directly test page property but this ensures it's working)
    expect($component->instance()->images->currentPage())->toBe(1);
});

test('folder filter triggers page reset', function () {
    Image::factory()->count(30)->create();
    
    $component = Livewire::test(ImageLibrary::class);
    $component->set('page', 2);
    $component->set('selectedFolder', 'test-folder');
    
    expect($component->instance()->images->currentPage())->toBe(1);
});

test('filter by triggers page reset', function () {
    Image::factory()->count(30)->create();
    
    $component = Livewire::test(ImageLibrary::class);
    $component->set('page', 2);
    $component->set('filterBy', 'attached');
    
    expect($component->instance()->images->currentPage())->toBe(1);
});

test('component does not have edit modal functionality', function () {
    $image = Image::factory()->create();
    
    // Test that editImage method does not exist
    $component = Livewire::test(ImageLibrary::class);
    
    expect(method_exists($component->instance(), 'editImage'))->toBeFalse()
        ->and(method_exists($component->instance(), 'saveImageChanges'))->toBeFalse()
        ->and(property_exists($component->instance(), 'showEditModal'))->toBeFalse()
        ->and(property_exists($component->instance(), 'editingImage'))->toBeFalse();
});

test('bulk actions work without confirmation for non-delete actions', function () {
    $images = Image::factory()->count(2)->create(['folder' => 'old']);
    
    Livewire::test(ImageLibrary::class)
        ->call('handleFloatingAction', [
            'action' => [
                'type' => 'move_folder',
                'folder' => 'new'
            ],
            'items' => $images->pluck('id')->toArray()
        ])
        ->assertDispatched('notify', fn ($event) => 
            $event['type'] === 'success' && 
            str_contains($event['message'], 'Bulk action applied to 2 image(s)!')
        );
        
    foreach ($images as $image) {
        $image->refresh();
        expect($image->folder)->toBe('new');
    }
});