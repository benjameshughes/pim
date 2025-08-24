<?php

use App\Models\Image;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    
    // Create a test image
    $this->image = Image::factory()->create([
        'filename' => 'test-image.jpg',
        'path' => 'images/test-image.jpg',
        'url' => 'https://example.com/test-image.jpg',
        'title' => 'Test Image',
    ]);
});

test('image edit delete method works correctly', function () {
    expect($this->image)->not->toBeNull();
    $imageId = $this->image->id;
    
    // Test the Livewire component with proper mounting
    $component = Livewire::actingAs($this->user)
        ->test(\App\Livewire\DAM\ImageEdit::class, ['image' => $this->image])
        ->call('delete');
    
    // Check that redirect response is returned
    $component->assertRedirect('/dam');
    
    // Verify image is deleted from database
    expect(Image::find($imageId))->toBeNull();
});

test('image edit page loads correctly before deletion', function () {
    $response = $this->get(route('dam.images.edit', $this->image));
    
    $response->assertStatus(200);
    $response->assertSee($this->image->title);
});

test('accessing deleted image edit page returns 404', function () {
    $imageId = $this->image->id;
    
    // Delete the image
    $this->image->delete();
    
    // Try to access the edit page for deleted image
    $response = $this->get("/dam/images/{$imageId}/edit");
    
    $response->assertStatus(404);
});

test('delete button triggers delete method', function () {
    // Mock the component and verify delete method gets called
    $component = Livewire::test(\App\Livewire\DAM\ImageEdit::class)
        ->set('image', $this->image);
    
    // Call delete method directly
    $component->call('delete');
    
    // Should redirect to DAM index
    $component->assertRedirect('/dam');
});

test('image service deletion works independently', function () {
    $service = new \App\Services\ImageUploadService();
    $imageId = $this->image->id;
    
    // Test service deletion
    $result = $service->deleteImage($this->image);
    
    expect($result)->toBeTrue();
    expect(Image::find($imageId))->toBeNull();
});

test('full delete workflow through browser simulation', function () {
    $imageId = $this->image->id;
    $editUrl = "/dam/images/{$imageId}/edit";
    
    // 1. Access edit page - should work
    $response = $this->get($editUrl);
    $response->assertStatus(200);
    
    // 2. Simulate delete button click through Livewire
    $component = Livewire::actingAs($this->user)
        ->test(\App\Livewire\DAM\ImageEdit::class)
        ->set('image', $this->image)
        ->call('delete');
    
    // 3. Should redirect to DAM index
    $component->assertRedirect('/dam');
    
    // 4. Image should be gone
    expect(Image::find($imageId))->toBeNull();
    
    // 5. Original edit URL should now 404
    $response = $this->get($editUrl);
    $response->assertStatus(404);
});