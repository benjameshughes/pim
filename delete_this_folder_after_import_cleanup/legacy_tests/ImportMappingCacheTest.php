<?php

use App\Models\User;
use App\Services\ImportMappingCache;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can save and retrieve import mappings for user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mappingCache = new ImportMappingCache;

    $columnMapping = [
        0 => 'product_name',
        1 => 'variant_sku',
        2 => 'variant_color',
        3 => 'retail_price',
    ];

    $importSettings = [
        'importMode' => 'create_or_update',
        'smartAttributeExtraction' => true,
        'autoGenerateParentMode' => false,
    ];

    // Save mappings
    $mappingCache->saveMapping($columnMapping, $importSettings);

    // Retrieve and verify
    $savedMapping = $mappingCache->getMapping();

    expect($savedMapping)->not()->toBeNull()
        ->and($savedMapping['column_mapping'])->toEqual($columnMapping)
        ->and($savedMapping['import_settings'])->toEqual($importSettings)
        ->and($savedMapping['user_id'])->toEqual($user->id);
});

test('returns null when no user is authenticated', function () {
    $mappingCache = new ImportMappingCache;

    $result = $mappingCache->getMapping();

    expect($result)->toBeNull();
});

test('can clear saved mappings', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mappingCache = new ImportMappingCache;

    // Save some mappings
    $mappingCache->saveMapping(['test' => 'mapping'], ['test' => 'setting']);

    // Verify they exist
    expect($mappingCache->getMapping())->not()->toBeNull();

    // Clear them
    $mappingCache->clearMapping();

    // Verify they're gone
    expect($mappingCache->getMapping())->toBeNull();
});

test('can save and retrieve headers for mapping', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mappingCache = new ImportMappingCache;

    $headers = ['Product Name', 'SKU', 'Color', 'Price'];

    // Save headers
    $mappingCache->saveHeaders($headers);

    // Retrieve headers
    $savedHeaders = $mappingCache->getLastHeadersUsed();

    expect($savedHeaders)->toEqual($headers);
});

test('provides correct mapping statistics', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mappingCache = new ImportMappingCache;

    // Test when no mappings exist
    $stats = $mappingCache->getMappingStats();
    expect($stats['has_saved_mapping'])->toBeFalse()
        ->and($stats['total_mappings'])->toEqual(0);

    // Save some mappings
    $columnMapping = [
        0 => 'product_name',
        1 => 'variant_sku',
        2 => '', // Empty mapping
        3 => 'retail_price',
    ];

    $mappingCache->saveMapping($columnMapping, []);

    // Test after saving
    $stats = $mappingCache->getMappingStats();
    expect($stats['has_saved_mapping'])->toBeTrue()
        ->and($stats['total_mappings'])->toEqual(3) // Only non-empty mappings
        ->and($stats['created_at'])->not()->toBeNull();
});

test('mappings are user-specific', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Save mapping for user 1
    $this->actingAs($user1);
    $mappingCache = new ImportMappingCache;
    $mappingCache->saveMapping(['user1' => 'mapping'], ['user1' => 'setting']);

    // Switch to user 2
    $this->actingAs($user2);
    $mappingCache2 = new ImportMappingCache;

    // User 2 should not see user 1's mappings
    expect($mappingCache2->getMapping())->toBeNull();

    // Save mapping for user 2
    $mappingCache2->saveMapping(['user2' => 'mapping'], ['user2' => 'setting']);

    // Switch back to user 1
    $this->actingAs($user1);
    $mappingCache3 = new ImportMappingCache;

    // User 1 should still see their own mappings
    $savedMapping = $mappingCache3->getMapping();
    expect($savedMapping['column_mapping'])->toEqual(['user1' => 'mapping'])
        ->and($savedMapping['import_settings'])->toEqual(['user1' => 'setting']);
});
