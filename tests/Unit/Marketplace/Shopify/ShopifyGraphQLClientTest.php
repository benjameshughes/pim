<?php

use App\Models\SyncAccount;
use App\Services\Marketplace\Shopify\ShopifyGraphQLClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->syncAccount = SyncAccount::factory()->create([
        'marketplace' => 'shopify',
        'shop_domain' => 'test-store.myshopify.com',
        'access_token' => 'test-token-12345',
        'status' => 'active',
    ]);

    $this->client = new ShopifyGraphQLClient($this->syncAccount);
});

describe('ShopifyGraphQLClient', function () {

    it('can be instantiated with sync account', function () {
        expect($this->client)->toBeInstanceOf(ShopifyGraphQLClient::class);
    });

    it('constructs correct GraphQL endpoint', function () {
        $reflection = new ReflectionClass($this->client);
        $property = $reflection->getProperty('endpoint');
        $property->setAccessible(true);
        $endpoint = $property->getValue($this->client);

        expect($endpoint)->toBe('https://test-store.myshopify.com/admin/api/2024-10/graphql.json');
    });

    it('sets correct headers for authentication', function () {
        $reflection = new ReflectionClass($this->client);
        $property = $reflection->getProperty('headers');
        $property->setAccessible(true);
        $headers = $property->getValue($this->client);

        expect($headers)->toHaveKey('X-Shopify-Access-Token', 'test-token-12345')
            ->toHaveKey('Content-Type', 'application/json');
    });
});

describe('GraphQL Mutations', function () {

    it('can create product with proper mutation structure', function () {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'productCreate' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/123',
                            'title' => 'Test Product',
                            'handle' => 'test-product',
                            'status' => 'ACTIVE',
                            'variants' => [
                                'edges' => [],
                            ],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ], 200),
        ]);

        $productInput = [
            'title' => 'Test Product',
            'descriptionHtml' => 'Test description',
            'vendor' => 'Test Vendor',
            'productType' => 'Test Type',
            'status' => 'ACTIVE',
        ];

        $result = $this->client->createProduct($productInput);

        expect($result)->toHaveKey('productCreate');
        expect($result['productCreate']['product']['title'])->toBe('Test Product');

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['variables']['input']['title'] === 'Test Product';
        });
    });

    it('can update product with proper mutation structure', function () {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'productUpdate' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/123',
                            'title' => 'Updated Product',
                            'handle' => 'updated-product',
                            'updatedAt' => '2025-08-31T00:00:00Z',
                        ],
                        'userErrors' => [],
                    ],
                ],
            ], 200),
        ]);

        $productInput = [
            'title' => 'Updated Product',
        ];

        $result = $this->client->updateProduct('gid://shopify/Product/123', $productInput);

        expect($result)->toHaveKey('productUpdate');
        expect($result['productUpdate']['product']['title'])->toBe('Updated Product');
    });

    it('can update product variants in bulk', function () {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'productVariantsBulkUpdate' => [
                        'productVariants' => [
                            [
                                'id' => 'gid://shopify/ProductVariant/456',
                                'sku' => '',
                                'price' => '99.99',
                                'updatedAt' => '2025-08-31T00:00:00Z',
                            ],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ], 200),
        ]);

        $variants = [
            [
                'id' => 'gid://shopify/ProductVariant/456',
                'price' => '99.99',
            ],
        ];

        $result = $this->client->updateProductVariants('gid://shopify/Product/123', $variants);

        expect($result)->toHaveKey('productVariantsBulkUpdate');
        expect($result['productVariantsBulkUpdate']['productVariants'][0]['price'])->toBe('99.99');
    });

    it('can delete product', function () {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'productDelete' => [
                        'deletedProductId' => 'gid://shopify/Product/123',
                        'userErrors' => [],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->client->deleteProduct('gid://shopify/Product/123');

        expect($result)->toHaveKey('productDelete');
        expect($result['productDelete']['deletedProductId'])->toBe('gid://shopify/Product/123');
    });
});

describe('GraphQL Queries', function () {

    it('can search products by SKU', function () {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'products' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Product/123',
                                    'title' => 'Test Product',
                                    'handle' => 'test-product',
                                    'variants' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => 'gid://shopify/ProductVariant/456',
                                                    'sku' => 'TEST-SKU-001',
                                                    'price' => '49.99',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->client->searchProductsBySku(['TEST-SKU-001']);

        expect($result)->toBeArray();
        expect($result[0]['title'])->toBe('Test Product');
        expect($result[0]['variants'][0]['sku'])->toBe('TEST-SKU-001');
    });

    it('can get single product with variants', function () {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'product' => [
                        'id' => 'gid://shopify/Product/123',
                        'title' => 'Test Product',
                        'handle' => 'test-product',
                        'status' => 'ACTIVE',
                        'variants' => [
                            'edges' => [
                                [
                                    'node' => [
                                        'id' => 'gid://shopify/ProductVariant/456',
                                        'sku' => 'TEST-SKU-001',
                                        'price' => '49.99',
                                        'inventoryQuantity' => 10,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->client->getProduct('gid://shopify/Product/123');

        expect($result)->toHaveKey('product');
        expect($result['product']['title'])->toBe('Test Product');
    });
});

describe('Error Handling', function () {

    it('handles GraphQL user errors', function () {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'productCreate' => [
                        'product' => null,
                        'userErrors' => [
                            [
                                'field' => ['title'],
                                'message' => 'Title cannot be blank',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->client->createProduct(['title' => '']);

        expect($result['productCreate']['userErrors'])->not->toBeEmpty();
        expect($result['productCreate']['userErrors'][0]['message'])->toBe('Title cannot be blank');
    });

    it('handles HTTP errors', function () {
        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        expect(fn () => $this->client->createProduct(['title' => 'Test']))
            ->toThrow(\Exception::class);
    });

    it('handles network timeouts', function () {
        Http::fake([
            '*' => Http::response([], 200)->delay(35000), // 35 second delay
        ]);

        expect(fn () => $this->client->createProduct(['title' => 'Test']))
            ->toThrow(\Exception::class);
    });
});

describe('REST API Integration', function () {

    it('can update variant via REST API for unsupported fields', function () {
        Http::fake([
            '*/variants/*' => Http::response([
                'variant' => [
                    'id' => 456,
                    'sku' => 'NEW-SKU-001',
                    'barcode' => '123456789',
                ],
            ], 200),
        ]);

        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('updateVariantViaRest');
        $method->setAccessible(true);

        // Should not throw exception
        $method->invoke($this->client, 'gid://shopify/ProductVariant/456', [
            'sku' => 'NEW-SKU-001',
            'barcode' => '123456789',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/variants/456.json') &&
                   $request->method() === 'PUT';
        });
    });

    it('handles REST API errors gracefully', function () {
        Http::fake([
            '*/variants/*' => Http::response(['errors' => 'Invalid SKU'], 422),
        ]);

        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('updateVariantViaRest');
        $method->setAccessible(true);

        // Should not throw exception, but log error
        $method->invoke($this->client, 'gid://shopify/ProductVariant/456', ['sku' => '']);

        // Test passes if no exception is thrown
        expect(true)->toBeTrue();
    });
});

describe('Rate Limiting', function () {

    it('includes rate limiting delays in bulk operations', function () {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'productCreate' => [
                        'product' => ['id' => 'gid://shopify/Product/123'],
                        'userErrors' => [],
                    ],
                ],
            ], 200),
        ]);

        $start = microtime(true);

        $this->client->createProducts([
            ['title' => 'Product 1'],
            ['title' => 'Product 2'],
        ]);

        $duration = microtime(true) - $start;

        // Should include 1.5 second delay between requests
        expect($duration)->toBeGreaterThan(1.0);
    });
});
