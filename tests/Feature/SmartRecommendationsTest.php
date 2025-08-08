<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\SmartRecommendations\SmartRecommendationsService;

test('smart recommendations service can be instantiated', function () {
    $service = app(SmartRecommendationsService::class);

    expect($service)->toBeInstanceOf(SmartRecommendationsService::class);
});

test('smart recommendations can analyze variants', function () {
    // Create test data
    $product = Product::factory()->create(['name' => 'Test Product']);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    $service = app(SmartRecommendationsService::class);
    $recommendations = $service->getRecommendations([$variant->id]);

    expect($recommendations)->toBeInstanceOf(\App\Services\SmartRecommendations\DTOs\RecommendationCollection::class);
    expect($recommendations->count())->toBeGreaterThanOrEqual(0);
});

test('smart recommendations can identify missing barcodes', function () {
    // Create variant without barcode
    $product = Product::factory()->create(['name' => 'Test Product']);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    $service = app(SmartRecommendationsService::class);
    $recommendations = $service->getRecommendations([$variant->id]);

    // Should have at least one recommendation for missing barcode
    $barcodeRecommendations = $recommendations->filter(function ($rec) {
        return str_contains($rec->title, 'Barcode');
    });

    expect($barcodeRecommendations->count())->toBeGreaterThan(0);
});
