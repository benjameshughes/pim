<?php

use App\Media\ImageManager;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('images');
});

test('ImageManager can be instantiated with fluent API', function () {
    $manager = ImageManager::make();
    
    expect($manager)->toBeInstanceOf(ImageManager::class);
});

test('ImageManager can be configured with fluent methods', function () {
    $manager = ImageManager::make()
        ->type('main')
        ->maxSize('5MB')
        ->acceptTypes(['jpg', 'png'])
        ->createThumbnails()
        ->uploadTo('images')
        ->processImmediately();
    
    expect($manager)->toBeInstanceOf(ImageManager::class);
});

test('ImageManager can be created for a product', function () {
    $product = Product::factory()->create();
    
    $manager = ImageManager::for('product', $product);
    
    expect($manager)->toBeInstanceOf(ImageManager::class);
});

test('ImageManager can be created for a variant', function () {
    $variant = ProductVariant::factory()->create();
    
    $manager = ImageManager::for('variant', $variant);
    
    expect($manager)->toBeInstanceOf(ImageManager::class);
});

test('ImageManager bulk operations can be configured', function () {
    $manager = ImageManager::bulk()
        ->filter(['unassigned', 'failed']);
    
    expect($manager)->toBeInstanceOf(ImageManager::class);
});

test('ImageManager configuration returns correct array', function () {
    $product = Product::factory()->create();
    
    $manager = ImageManager::for('product', $product)
        ->type('detail')
        ->maxSize('10MB')
        ->acceptTypes(['jpg', 'png', 'webp'])
        ->createThumbnails(false)
        ->uploadTo('images');
    
    // Use reflection to access protected method
    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('getConfig');
    $method->setAccessible(true);
    
    $config = $method->invoke($manager);
    
    expect($config)->toMatchArray([
        'modelType' => 'product',
        'modelId' => $product->id,
        'imageType' => 'detail',
        'maxSize' => 10240, // 10MB in KB
        'acceptTypes' => ['jpg', 'png', 'webp'],
        'createThumbnails' => false,
        'storageDisk' => 'images',
    ]);
});

test('ImageManager correctly parses size strings', function () {
    $manager = ImageManager::make();
    
    // Use reflection to test protected method
    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('parseSizeString');
    $method->setAccessible(true);
    
    expect($method->invoke($manager, '5MB'))->toBe(5120);
    expect($method->invoke($manager, '1GB'))->toBe(1048576);
    expect($method->invoke($manager, '500'))->toBe(500);
});

test('ImageManager determines model type correctly', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create();
    
    $manager = ImageManager::make();
    
    // Use reflection to test protected method
    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('getModelType');
    $method->setAccessible(true);
    
    expect($method->invoke($manager, $product))->toBe('product');
    expect($method->invoke($manager, $variant))->toBe('variant');
});