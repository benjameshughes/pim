<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncAccount;
use App\Services\Marketplace\Facades\Sync;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeFreemansAccount(): SyncAccount {
    return SyncAccount::create([
        'name' => 'main',
        'channel' => 'freemans',
        'display_name' => 'Freemans UK',
        'is_active' => true,
        'credentials' => [
            'api_url' => 'https://freemansuk-prod.mirakl.net',
            'api_key' => 'test-api-key',
            'shop_id' => '2023',
        ],
        'settings' => [
            'currency' => 'GBP',
            'category_code' => 'H02',
            'default_state' => 11,
            'logistic_class' => 'DL',
            'leadtime_to_ship' => 2,
        ],
    ]);
}

function makeProductWithVariants(): Product {
    /** @var Product $product */
    $product = Product::factory()->create([
        'parent_sku' => 'FREEMANS-PARENT-001',
        'name' => 'Test Curtains',
        'description' => 'Blackout curtains',
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'SKU-A',
        'title' => 'Curtains A',
        'price' => 29.99,
        'stock_level' => 5,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'SKU-B',
        'title' => 'Curtains B',
        'price' => 39.99,
        'stock_level' => 3,
    ]);

    return $product->fresh('variants');
}

it('creates a product on Freemans via REST', function () {
    $account = makeFreemansAccount();
    $product = makeProductWithVariants();

    Http::fake([
        '*/api/products' => Http::response(['product' => ['product_id' => 'FREEMANS-PARENT-001']], 201),
    ]);

    $result = Sync::freemans($account->name)->create($product->id)->push();

    expect($result->isSuccess())->toBeTrue();
    expect($result->get('product_id'))->toBe('FREEMANS-PARENT-001');
});

it('links existing offers by variant skus on Freemans', function () {
    $account = makeFreemansAccount();
    $product = makeProductWithVariants();

    // Fake offers: ensure shop_sku maps to product_id
    Http::fake([
        '*/api/offers*' => Http::response([
            'offers' => [
                [
                    'product_id' => 'ID-A',
                    'shop_sku' => 'SKU-A',
                    'quantity' => 5,
                    'price' => '29.99',
                    'currency_iso_code' => 'GBP',
                ],
                [
                    'product_id' => 'ID-B',
                    'shop_sku' => 'SKU-B',
                    'quantity' => 3,
                    'price' => '39.99',
                    'currency_iso_code' => 'GBP',
                ],
            ],
        ], 200),
    ]);

    $result = Sync::freemans($account->name)->link($product->id)->push();

    expect($result->isSuccess())->toBeTrue();
    $mapping = $result->get('linked_offers');

    expect($mapping)->toHaveKey('SKU-A', 'ID-A');
    expect($mapping)->toHaveKey('SKU-B', 'ID-B');
    expect($result->get('coverage_percent'))->toBe(100);
});

