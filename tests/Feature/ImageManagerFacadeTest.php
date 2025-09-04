<?php

use App\Models\Product;
use App\Models\Image;
use App\Services\ImageManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('r2');
    
    $this->product = Product::factory()->create();
    $this->image = Image::factory()->create();
    
    // Attach image to product for testing
    $this->image->attachTo($this->product);
});

it('returns ProductImageContext when calling asPrimary', function () {
    // Test that asPrimary returns correct context for chaining
    $result = app(ImageManager::class)
        ->product($this->product)
        ->asPrimary();
    
    expect($result)->toBeInstanceOf(\App\Services\ProductImageContext::class);
});

it('handles asPrimary when no pending image exists', function () {
    // Call asPrimary without attaching an image first - should not throw error
    $result = app(ImageManager::class)
        ->product($this->product)
        ->asPrimary();
    
    expect($result)->toBeInstanceOf(\App\Services\ProductImageContext::class);
});

it('can chain attach and asPrimary methods', function () {
    $newImage = Image::factory()->create();
    
    // Test method chaining - should not throw errors
    $context = app(ImageManager::class)
        ->product($this->product)
        ->attach($newImage->id)
        ->asPrimary();
    
    expect($context)->toBeInstanceOf(\App\Services\ProductImageContext::class);
});

it('asPrimary method exists and does not throw errors', function () {
    // Simple test to verify the asPrimary method works without errors
    $result = app(ImageManager::class)
        ->product($this->product)
        ->asPrimary();
    
    expect($result)->toBeInstanceOf(\App\Services\ProductImageContext::class);
    
    // Verify the method can be called multiple times without issues
    $result2 = $result->asPrimary();
    expect($result2)->toBeInstanceOf(\App\Services\ProductImageContext::class);
});