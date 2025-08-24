<?php

use App\Models\Image;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('dam index route loads successfully', function () {
    $response = $this->get(route('dam.index'));
    
    $response->assertStatus(200)
        ->assertSee('Digital Asset Management')
        ->assertSee('Organize, search, and manage all your product images');
});

test('dam index route requires authentication', function () {
    auth()->logout();
    
    $response = $this->get(route('dam.index'));
    
    $response->assertRedirect(route('login'));
});

test('dam images edit route loads successfully', function () {
    $image = Image::factory()->create([
        'title' => 'Test Image',
        'filename' => 'test.jpg',
        'path' => 'images/test.jpg',
        'url' => 'https://example.com/test.jpg',
    ]);
    
    $response = $this->get(route('dam.images.edit', $image));
    
    $response->assertStatus(200)
        ->assertSee('Edit Image')
        ->assertSee('Update image metadata')
        ->assertSee($image->title);
});

test('dam images edit route requires authentication', function () {
    auth()->logout();
    
    $image = Image::factory()->create();
    
    $response = $this->get(route('dam.images.edit', $image));
    
    $response->assertRedirect(route('login'));
});

test('dam images edit route returns 404 for nonexistent image', function () {
    $response = $this->get('/dam/images/99999/edit');
    
    $response->assertStatus(404);
});

test('dam images edit route uses model binding', function () {
    $image = Image::factory()->create([
        'title' => 'Bound Image',
        'alt_text' => 'Alt text for bound image',
    ]);
    
    $response = $this->get(route('dam.images.edit', $image));
    
    $response->assertStatus(200)
        ->assertSee('Bound Image')
        ->assertSee('Alt text for bound image');
});

test('dam route names are correctly defined', function () {
    expect(route('dam.index'))->toContain('/dam')
        ->and(route('dam.images.edit', 1))->toContain('/dam/images/1/edit');
});

test('dam index contains image library component', function () {
    $response = $this->get(route('dam.index'));
    
    $response->assertStatus(200)
        ->assertSeeLivewire('d-a-m.image-library');
});

test('dam images edit contains image edit component', function () {
    $image = Image::factory()->create();
    
    $response = $this->get(route('dam.images.edit', $image));
    
    $response->assertStatus(200)
        ->assertSeeLivewire('d-a-m.image-edit');
});

test('dam images edit has back navigation', function () {
    $image = Image::factory()->create();
    
    $response = $this->get(route('dam.images.edit', $image));
    
    $response->assertStatus(200)
        ->assertSee('Back to Library')
        ->assertSee(route('dam.index'));
});

test('dam images edit page has proper page structure', function () {
    $image = Image::factory()->create([
        'title' => 'Test Image',
        'filename' => 'test.jpg',
    ]);
    
    $response = $this->get(route('dam.images.edit', $image));
    
    $response->assertStatus(200)
        ->assertSee('<h1', false)
        ->assertSee('Edit Image')
        ->assertSee('Update image metadata');
});

test('dam routes are within auth middleware group', function () {
    // Test that unauthenticated users are redirected
    auth()->logout();
    
    $image = Image::factory()->create();
    
    $indexResponse = $this->get(route('dam.index'));
    $editResponse = $this->get(route('dam.images.edit', $image));
    
    $indexResponse->assertRedirect(route('login'));
    $editResponse->assertRedirect(route('login'));
});

test('dam index uses correct layout', function () {
    $response = $this->get(route('dam.index'));
    
    $response->assertStatus(200)
        ->assertSee('max-w-7xl') // Layout container class
        ->assertSee('px-6 py-6'); // Layout padding
});

test('dam edit uses correct layout', function () {
    $image = Image::factory()->create();
    
    $response = $this->get(route('dam.images.edit', $image));
    
    $response->assertStatus(200)
        ->assertSee('max-w-4xl') // Edit page has smaller container
        ->assertSee('px-6 py-6'); // Layout padding
});