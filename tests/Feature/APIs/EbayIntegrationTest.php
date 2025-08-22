<?php

use App\Models\Product;
use App\Models\User;
use App\Models\SyncAccount;
use App\Services\EbayConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

describe('eBay Integration', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->syncAccount = SyncAccount::factory()->create([
            'channel' => 'ebay',
            'credentials' => [
                'client_id' => 'test-client-id',
                'client_secret' => 'test-client-secret',
                'access_token' => 'test-access-token',
                'refresh_token' => 'test-refresh-token',
            ],
        ]);
    });

    it('can test eBay connection', function () {
        Http::fake([
            'api.ebay.com/sell/inventory/v1/inventory_item' => Http::response([
                'inventoryItems' => []
            ], 200),
        ]);

        $service = new EbayConnectService();
        $result = $service->testConnection($this->syncAccount);

        expect($result['success'])->toBeTrue();
        expect($result['message'])->toContain('successfully');
    });

    it('handles eBay authentication errors', function () {
        Http::fake([
            'api.ebay.com/sell/inventory/v1/inventory_item' => Http::response([
                'errors' => [
                    [
                        'errorId' => 1001,
                        'domain' => 'ACCESS',
                        'message' => 'Invalid access token',
                    ]
                ]
            ], 401),
        ]);

        $service = new EbayConnectService();
        $result = $service->testConnection($this->syncAccount);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('Invalid access token');
    });

    it('can create inventory item on eBay', function () {
        Http::fake([
            'api.ebay.com/sell/inventory/v1/inventory_item/*' => Http::response([
                'sku' => 'TEST-SKU-001',
                'product' => [
                    'title' => 'Test Product',
                    'description' => 'Test description',
                ]
            ], 204),
        ]);

        $product = Product::factory()->create(['sku' => 'TEST-SKU-001']);
        
        $service = new EbayConnectService();
        $result = $service->createInventoryItem($product, $this->syncAccount);

        expect($result['success'])->toBeTrue();
        
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'inventory_item/TEST-SKU-001')
                && $request->hasHeader('Authorization')
                && $request->hasHeader('Content-Type', 'application/json');
        });
    });

    it('can create eBay listing', function () {
        Http::fake([
            'api.ebay.com/sell/inventory/v1/offer' => Http::response([
                'offerId' => '12345',
                'sku' => 'TEST-SKU-001',
                'marketplaceId' => 'EBAY_US',
            ], 201),
        ]);

        $product = Product::factory()->create(['sku' => 'TEST-SKU-001']);
        
        $service = new EbayConnectService();
        $result = $service->createOffer($product, $this->syncAccount, [
            'categoryId' => '12345',
            'price' => 29.99,
            'quantity' => 10,
        ]);

        expect($result['success'])->toBeTrue();
        expect($result['offerId'])->toBe('12345');
    });

    it('can handle eBay rate limiting', function () {
        Http::fake([
            'api.ebay.com/sell/inventory/v1/inventory_item/*' => Http::response([
                'errors' => [
                    [
                        'errorId' => 21919,
                        'domain' => 'API_INVENTORY',
                        'message' => 'Application request limit reached',
                    ]
                ]
            ], 429, ['Retry-After' => '60']),
        ]);

        $product = Product::factory()->create(['sku' => 'TEST-SKU-001']);
        
        $service = new EbayConnectService();
        $result = $service->createInventoryItem($product, $this->syncAccount);

        expect($result['success'])->toBeFalse();
        expect($result['retry_after'])->toBe(60);
    });

    it('can fetch eBay categories', function () {
        Http::fake([
            'api.ebay.com/commerce/taxonomy/v1/category_tree/0' => Http::response([
                'categoryTreeId' => '0',
                'categoryTreeVersion' => '119',
                'rootCategoryNode' => [
                    'categoryId' => '20081',
                    'categoryName' => 'Antiques',
                    'childCategoryTreeNodes' => []
                ]
            ], 200),
        ]);

        $service = new EbayConnectService();
        $result = $service->getCategories($this->syncAccount);

        expect($result['success'])->toBeTrue();
        expect($result['categories'])->toBeArray();
        expect($result['categories'][0]['categoryName'])->toBe('Antiques');
    });

    it('validates required eBay credentials', function () {
        $invalidAccount = SyncAccount::factory()->create([
            'channel' => 'ebay',
            'credentials' => [
                'client_id' => '',
                'access_token' => 'test-token',
            ],
        ]);

        $service = new EbayConnectService();
        
        expect(fn() => $service->testConnection($invalidAccount))
            ->toThrow(InvalidArgumentException::class, 'Missing required eBay credentials');
    });
});