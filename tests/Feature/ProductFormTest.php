<?php

use App\Livewire\Products\ProductForm;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

test('product form can create a new product', function () {
    $user = User::factory()->create();
    
    $initialCount = Product::count();
    
    $component = Livewire::actingAs($user)
        ->test(ProductForm::class)
        ->set('name', 'Test Product')
        ->set('slug', '') // Explicitly set empty slug to trigger generation
        ->set('description', 'A test product description')
        ->set('status', 'active')
        ->set('imageType', 'gallery'); // Set required imageType
    
    // Debug: check component state before save
    expect($component->get('name'))->toBe('Test Product');
    expect($component->get('status'))->toBe('active');
    
    $component->call('save');
    
    // Debug: check if there are any errors
    $errors = $component->get('errors');
    if (!empty($errors)) {
        dump('Validation errors:', $errors);
    }
    
    $component->assertHasNoErrors()
        ->assertRedirect(route('products.index'));
    
    // Check that a product was created
    expect(Product::count())->toBe($initialCount + 1);
    
    $product = Product::where('name', 'Test Product')->first();
    expect($product)->not()->toBeNull();
    expect($product->slug)->toBe('test-product');
});

test('product form generates unique slug when duplicate exists', function () {
    $user = User::factory()->create();
    
    // Create first product
    Product::factory()->create(['name' => 'Test Product', 'slug' => 'test-product']);
    
    Livewire::actingAs($user)
        ->test(ProductForm::class)
        ->set('name', 'Test Product')
        ->set('slug', '') // Explicitly set empty slug
        ->set('description', 'Another test product')
        ->set('status', 'active')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('products.index'));
    
    expect(Product::where('slug', 'test-product-1')->exists())->toBeTrue();
});

test('product form can edit existing product', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Original Name', 'slug' => 'original-name']);
    
    Livewire::actingAs($user)
        ->test(ProductForm::class, ['product' => $product])
        ->set('name', 'Updated Name')
        ->set('slug', 'updated-name') // Explicitly set the slug
        ->set('description', 'Updated description')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('products.index'));
    
    $product->refresh();
    expect($product->name)->toBe('Updated Name');
    expect($product->slug)->toBe('updated-name');
});

test('product form validates required fields', function () {
    $user = User::factory()->create();
    
    Livewire::actingAs($user)
        ->test(ProductForm::class)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});
