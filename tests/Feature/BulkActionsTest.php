<?php

use App\Actions\Images\BulkDeleteImagesAction;
use App\Actions\Images\BulkMoveImagesAction;
use App\Actions\Images\BulkTagImagesAction;
use App\Livewire\Images\ImageLibrary;
use App\Livewire\Images\ImageLibraryHeader;
use App\Models\Image;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    
    // Create permission if it doesn't exist
    if (!\Spatie\Permission\Models\Permission::where('name', 'manage-images')->exists()) {
        \Spatie\Permission\Models\Permission::create(['name' => 'manage-images']);
    }
    
    // Give user the permission
    $this->user->givePermissionTo('manage-images');
    
    Storage::fake('images');
});

test('image library header component renders successfully', function () {
    $component = Livewire::test(ImageLibraryHeader::class);
    
    $component->assertStatus(200);
    $component->assertSee('Search images...');
});

test('image delete action step by step debugging', function () {
    // Create test image
    $image = Image::factory()->create([
        'folder' => null,
        'tags' => ['test']
    ]);
    
    try {
        dump('Testing getVariantsOfOriginal method...');
        
        // Test the problem method directly
        $deleteAction = app(\App\Actions\Images\DeleteImageAction::class);
        
        // Create reflection to access protected method
        $reflection = new ReflectionClass($deleteAction);
        $method = $reflection->getMethod('getVariantsOfOriginal');
        $method->setAccessible(true);
        
        $variants = $method->invokeArgs($deleteAction, [$image]);
        dump('Variants result:', $variants->count());
        
        expect(true)->toBeTrue();
    } catch (\Exception $e) {
        dump('Step failed:', $e->getMessage());
        dump('Stack trace:', $e->getTraceAsString());
        expect(false)->toBeTrue();
    }
});

test('bulk delete action works with multiple images', function () {
    // Create test images
    $images = Image::factory()->count(3)->create([
        'folder' => null,
        'tags' => ['test']
    ]);
    
    $imageIds = $images->pluck('id')->toArray();
    
    $action = app(BulkDeleteImagesAction::class);
    $result = $action->execute($imageIds);
    
    if (!$result['success']) {
        dump('Bulk delete failed:', $result);
    }
    
    expect($result['success'])->toBeTrue();
    expect($result['data']['deleted_count'])->toBe(3);
    
    // Verify images are deleted
    expect(Image::whereIn('id', $imageIds)->count())->toBe(0);
});

test('bulk move action works with valid folder', function () {
    // Create test images
    $images = Image::factory()->count(2)->create([
        'folder' => 'old-folder',
        'tags' => ['test']
    ]);
    
    $imageIds = $images->pluck('id')->toArray();
    
    $action = app(BulkMoveImagesAction::class);
    $result = $action->execute($imageIds, 'new-folder');
    
    expect($result['success'])->toBeTrue();
    expect($result['data']['moved_count'])->toBe(2);
    
    // Verify images are moved
    $updatedImages = Image::whereIn('id', $imageIds)->get();
    expect($updatedImages->every(fn($img) => $img->folder === 'new-folder'))->toBeTrue();
});

test('bulk tag action can add tags', function () {
    // Create test images
    $images = Image::factory()->count(2)->create([
        'tags' => ['existing-tag']
    ]);
    
    $imageIds = $images->pluck('id')->toArray();
    
    $action = app(BulkTagImagesAction::class);
    $result = $action->execute($imageIds, 'new-tag,another-tag', 'add');
    
    expect($result['success'])->toBeTrue();
    expect($result['data']['updated_count'])->toBe(2);
    
    // Verify tags are added
    $updatedImages = Image::whereIn('id', $imageIds)->get();
    expect($updatedImages->every(function($img) {
        return in_array('existing-tag', $img->tags) && 
               in_array('new-tag', $img->tags) && 
               in_array('another-tag', $img->tags);
    }))->toBeTrue();
});

test('image library loads without errors', function () {
    // Create some test images
    Image::factory()->count(5)->create();
    
    // Test component loading with proper authentication
    $component = Livewire::test(ImageLibrary::class);
    
    $component->assertStatus(200);
});