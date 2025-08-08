<?php

use App\Media\ImageManager;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('images');
});

test('ImageManager can handle complete upload workflow', function () {
    $product = Product::factory()->create();
    
    // Create a fake image file
    $file = UploadedFile::fake()->image('test-image.jpg', 800, 600)->size(500); // 500 KB
    
    $manager = ImageManager::for('product', $product)
        ->type('main')
        ->maxSize('1MB')
        ->acceptTypes(['jpg', 'png'])
        ->createThumbnails(true)
        ->uploadTo('public')
        ->processImmediately(true);
    
    // Execute the upload
    $uploadResult = $manager->upload([$file]);
    
    expect($uploadResult->hasErrors())->toBeFalse();
    expect($uploadResult->getSuccessCount())->toBe(1);
    
    // Check that ProductImage record was created
    expect(ProductImage::count())->toBe(1);
    
    $productImage = ProductImage::first();
    expect($productImage->product_id)->toBe($product->id);
    expect($productImage->image_type)->toBe('main');
    expect($productImage->processing_status)->toBe(ProductImage::PROCESSING_PENDING);
});

test('ImageManager validates file size correctly', function () {
    $product = Product::factory()->create();
    
    // Create a file that exceeds the limit
    $file = UploadedFile::fake()->image('large-image.jpg', 2000, 2000)->size(2048); // 2MB
    
    $manager = ImageManager::for('product', $product)
        ->maxSize('1MB') // 1024 KB limit
        ->acceptTypes(['jpg', 'png']);
    
    // The validation error should be captured in the result
    $uploadResult = $manager->upload([$file]);
    
    expect($uploadResult->hasErrors())->toBeTrue();
    expect($uploadResult->getSuccessCount())->toBe(0);
    expect($uploadResult->getErrorCount())->toBe(1);
    expect(ProductImage::count())->toBe(0);
});

test('ImageManager validates file types correctly', function () {
    $product = Product::factory()->create();
    
    // Create a file with wrong type
    $file = UploadedFile::fake()->create('document.pdf', 100);
    
    $manager = ImageManager::for('product', $product)
        ->acceptTypes(['jpg', 'png']);
    
    // The validation error should be captured in the result
    $uploadResult = $manager->upload([$file]);
    
    expect($uploadResult->hasErrors())->toBeTrue();
    expect($uploadResult->getSuccessCount())->toBe(0);
    expect($uploadResult->getErrorCount())->toBe(1);
    expect(ProductImage::count())->toBe(0);
});

test('ImageManager can handle unassigned uploads', function () {
    // Create a fake image file
    $file = UploadedFile::fake()->image('unassigned-image.jpg', 400, 400)->size(200);
    
    $manager = ImageManager::make()
        ->type('main')
        ->maxSize('1MB')
        ->acceptTypes(['jpg', 'png']);
    
    // Execute the upload without assigning to any model
    $uploadResult = $manager->upload([$file]);
    
    expect($uploadResult->hasErrors())->toBeFalse();
    expect($uploadResult->getSuccessCount())->toBe(1);
    
    // Check that ProductImage record was created without assignment
    $productImage = ProductImage::first();
    expect($productImage->product_id)->toBeNull();
    expect($productImage->variant_id)->toBeNull();
    expect($productImage->image_type)->toBe('main');
});

test('ImageManager can handle multiple file uploads', function () {
    $product = Product::factory()->create();
    
    $files = [
        UploadedFile::fake()->image('image1.jpg', 400, 400)->size(200),
        UploadedFile::fake()->image('image2.png', 500, 500)->size(300),
        UploadedFile::fake()->image('image3.jpg', 600, 600)->size(400),
    ];
    
    $manager = ImageManager::for('product', $product)
        ->type('detail')
        ->maxSize('1MB')
        ->acceptTypes(['jpg', 'png']);
    
    $uploadResult = $manager->upload($files);
    
    expect($uploadResult->hasErrors())->toBeFalse();
    expect($uploadResult->getSuccessCount())->toBe(3);
    expect(ProductImage::count())->toBe(3);
    
    // Check all images are assigned to the product
    ProductImage::all()->each(function ($image) use ($product) {
        expect($image->product_id)->toBe($product->id);
        expect($image->image_type)->toBe('detail');
    });
});

test('ImageManager handles partial failures correctly', function () {
    $product = Product::factory()->create();
    
    $files = [
        UploadedFile::fake()->image('good-image.jpg', 400, 400)->size(200),      // Valid
        UploadedFile::fake()->image('too-large.jpg', 1000, 1000)->size(2048),   // Too large
        UploadedFile::fake()->create('document.pdf', 100),                      // Wrong type
    ];
    
    $manager = ImageManager::for('product', $product)
        ->type('main')
        ->maxSize('1MB')
        ->acceptTypes(['jpg', 'png']);
    
    $uploadResult = $manager->upload($files);
    
    expect($uploadResult->hasErrors())->toBeTrue();
    expect($uploadResult->getSuccessCount())->toBe(1);
    expect($uploadResult->getErrorCount())->toBe(2);
    
    // Only one image should be created
    expect(ProductImage::count())->toBe(1);
});