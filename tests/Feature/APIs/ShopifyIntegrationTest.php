<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\SyncAccount;
use App\Actions\API\Shopify\PushProductToShopify;
use App\Actions\API\Shopify\ImportShopifyProduct;
use App\Services\Shopify\API\ProductsApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

describe('Shopify Integration', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->syncAccount = SyncAccount::factory()->create([
            'channel' => 'shopify',
            'credentials' => [
                'shop_url' => 'test-shop.myshopify.com',
                'access_token' => 'test-token',
            ],
        ]);
    });

    it('can push product to Shopify', function () {
        Http::fake([
            'test-shop.myshopify.com/admin/api/*/products.json' => Http::response([
                'product' => [
                    'id' => 12345,
                    'title' => 'Test Product',
                    'handle' => 'test-product',
                ]
            ], 201),
        ]);

        $product = Product::factory()->create(['name' => 'Test Product']);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $action = new PushProductToShopify();
        $result = $action->execute($product, $this->syncAccount);

        expect($result['success'])->toBeTrue();
        expect($result['shopify_id'])->toBe(12345);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'https://test-shop.myshopify.com/admin/api/2024-01/products.json'
                && $request->hasHeader('X-Shopify-Access-Token', 'test-token');
        });
    });

    it('handles Shopify API errors gracefully', function () {
        Http::fake([
            'test-shop.myshopify.com/admin/api/*/products.json' => Http::response([
                'errors' => ['Title cannot be blank']
            ], 422),
        ]);

        $product = Product::factory()->create(['name' => '']);

        $action = new PushProductToShopify();
        $result = $action->execute($product, $this->syncAccount);

        expect($result['success'])->toBeFalse();
        expect($result['errors'])->toContain('Title cannot be blank');
    });

    it('can import product from Shopify', function () {
        Http::fake([
            'test-shop.myshopify.com/admin/api/*/products/12345.json' => Http::response([
                'product' => [
                    'id' => 12345,
                    'title' => 'Imported Product',
                    'body_html' => 'Product description',
                    'handle' => 'imported-product',
                    'variants' => [
                        [
                            'id' => 67890,
                            'title' => 'Default Title',
                            'sku' => 'IMP-001',
                            'price' => '29.99',
                        ],
                    ],
                ]
            ], 200),
        ]);

        $action = new ImportShopifyProduct();
        $result = $action->execute(12345, $this->syncAccount);

        expect($result['success'])->toBeTrue();
        expect(Product::count())->toBe(1);
        expect(ProductVariant::count())->toBe(1);

        $product = Product::first();
        expect($product->name)->toBe('Imported Product');
        expect($product->variants->first()->sku)->toBe('IMP-001');
    });

    it('can sync product updates from Shopify', function () {
        // Create existing product
        $product = Product::factory()->create([
            'name' => 'Original Name',
            'external_id' => '12345',
        ]);

        Http::fake([
            'test-shop.myshopify.com/admin/api/*/products/12345.json' => Http::response([
                'product' => [
                    'id' => 12345,
                    'title' => 'Updated Name',
                    'body_html' => 'Updated description',
                    'updated_at' => now()->addHour()->toISOString(),
                ]
            ], 200),
        ]);

        $api = new ProductsApi($this->syncAccount);
        $result = $api->syncProduct(12345);

        $product->refresh();
        expect($product->name)->toBe('Updated Name');
        expect($product->description)->toBe('Updated description');
    });

    it('can handle Shopify webhooks', function () {
        $webhookPayload = [
            'id' => 12345,
            'title' => 'Webhook Updated Product',
            'body_html' => 'Updated via webhook',
            'updated_at' => now()->toISOString(),
        ];

        $response = $this->postJson('/shopify/webhook/products/update', $webhookPayload, [
            'X-Shopify-Topic' => 'products/update',
            'X-Shopify-Shop-Domain' => 'test-shop.myshopify.com',
        ]);

        $response->assertOk();
    });

    it('validates webhook signatures', function () {
        $payload = ['id' => 12345, 'title' => 'Test'];
        
        $response = $this->postJson('/shopify/webhook/products/update', $payload, [
            'X-Shopify-Topic' => 'products/update',
            'X-Shopify-Shop-Domain' => 'test-shop.myshopify.com',
            'X-Shopify-Hmac-Sha256' => 'invalid-signature',
        ]);

        $response->assertStatus(401);
    });

    it('can test Shopify connection', function () {
        Http::fake([
            'test-shop.myshopify.com/admin/api/*/shop.json' => Http::response([
                'shop' => [
                    'id' => 1,
                    'name' => 'Test Shop',
                    'email' => 'test@shop.com',
                ]
            ], 200),
        ]);

        $component = Livewire::test('marketplace.add-integration-wizard')
            ->set('credentials.shop_url', 'test-shop.myshopify.com')
            ->set('credentials.access_token', 'test-token')
            ->call('testConnection');

        $component->assertSet('connectionStatus', 'success');
    });
});