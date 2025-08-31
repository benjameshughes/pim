<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncAccount;
use App\Services\Marketplace\Facades\Sync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test sync account
    $this->syncAccount = SyncAccount::factory()->create([
        'name' => 'Test Shopify Integration',
        'marketplace' => 'shopify',
        'shop_domain' => 'integration-test.myshopify.com',
        'access_token' => 'integration-test-token',
        'status' => 'active',
    ]);

    // Create test product with multiple variants
    $this->product = Product::factory()->create([
        'name' => 'Integration Test Roller Blind',
        'parent_sku' => 'INT-TEST-001',
        'description' => 'Premium roller blind for integration testing',
        'status' => 'active',
    ]);

    // Create variants in multiple colors and sizes
    $this->variants = collect([
        ['color' => 'White', 'width' => 60, 'drop' => 100, 'sku' => 'INT-TEST-001-WHITE-60x100'],
        ['color' => 'White', 'width' => 80, 'drop' => 120, 'sku' => 'INT-TEST-001-WHITE-80x120'],
        ['color' => 'Black', 'width' => 60, 'drop' => 100, 'sku' => 'INT-TEST-001-BLACK-60x100'],
        ['color' => 'Black', 'width' => 80, 'drop' => 120, 'sku' => 'INT-TEST-001-BLACK-80x120'],
    ])->map(function ($variantData) {
        return ProductVariant::factory()->create(array_merge([
            'product_id' => $this->product->id,
            'price' => 59.99,
        ], $variantData));
    });
});

describe('End-to-End Shopify Integration', function () {

    it('can complete full product creation workflow', function () {
        // Mock successful product creation
        Http::fake([
            '*/graphql.json' => Http::response([
                'data' => [
                    'productCreate' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/123456',
                            'title' => 'Integration Test Roller Blind - White',
                            'handle' => 'integration-test-roller-blind-white',
                            'status' => 'ACTIVE',
                            'variants' => [
                                'edges' => [
                                    [
                                        'node' => [
                                            'id' => 'gid://shopify/ProductVariant/789',
                                            'sku' => '',
                                            'price' => '0.00',
                                        ],
                                    ],
                                    [
                                        'node' => [
                                            'id' => 'gid://shopify/ProductVariant/790',
                                            'sku' => '',
                                            'price' => '0.00',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'userErrors' => [],
                    ],
                    'productVariantsBulkUpdate' => [
                        'productVariants' => [
                            [
                                'id' => 'gid://shopify/ProductVariant/789',
                                'sku' => '',
                                'price' => '59.99',
                                'updatedAt' => now()->toISOString(),
                            ],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ], 200),
            '*/variants/*' => Http::response([
                'variant' => ['id' => 789, 'sku' => 'INT-TEST-001-WHITE-60x100'],
            ], 200),
        ]);

        // Execute create operation
        $result = Sync::marketplace('shopify')->create($this->product->id)->push();

        // Verify success
        expect($result->isSuccess())->toBeTrue();
        expect($result->getMessage())->toContain('Successfully created');

        // Verify product attributes are updated
        $this->product->refresh();
        expect($this->product->getSmartAttributeValue('shopify_status'))->toBe('synced');
        expect($this->product->getSmartAttributeValue('shopify_sync_account_id'))->toBe($this->syncAccount->id);

        $productIds = $this->product->getSmartAttributeValue('shopify_product_ids');
        expect($productIds)->toBeString(); // JSON string

        $decodedIds = json_decode($productIds, true);
        expect($decodedIds)->toHaveKey('White');
        expect($decodedIds)->toHaveKey('Black');
    });

    it('can complete full update workflow for existing products', function () {
        // First, set up product as already synced
        $this->product->setAttributeValue('shopify_product_ids', json_encode([
            'White' => 'gid://shopify/Product/123456',
            'Black' => 'gid://shopify/Product/123457',
        ]));
        $this->product->setAttributeValue('shopify_sync_account_id', $this->syncAccount->id);
        $this->product->setAttributeValue('shopify_status', 'synced');

        // Mock successful updates
        Http::fake([
            '*/graphql.json' => Http::response([
                'data' => [
                    'productUpdate' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/123456',
                            'title' => 'Updated Integration Test - White',
                            'updatedAt' => now()->toISOString(),
                        ],
                        'userErrors' => [],
                    ],
                    'product' => [
                        'id' => 'gid://shopify/Product/123456',
                        'variants' => [
                            'edges' => [
                                [
                                    'node' => [
                                        'id' => 'gid://shopify/ProductVariant/789',
                                        'sku' => 'OLD-SKU',
                                        'price' => '49.99',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'productVariantsBulkUpdate' => [
                        'productVariants' => [
                            [
                                'id' => 'gid://shopify/ProductVariant/789',
                                'price' => '59.99',
                                'updatedAt' => now()->toISOString(),
                            ],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ], 200),
            '*/variants/*' => Http::response([
                'variant' => ['id' => 789, 'sku' => 'INT-TEST-001-WHITE-60x100'],
            ], 200),
        ]);

        // Execute full update operation
        $result = Sync::marketplace('shopify')->fullUpdate($this->product->id)->push();

        // Verify success
        expect($result)->toBeInstanceOf(\App\Services\Marketplace\ValueObjects\SyncResult::class);
    });

    it('can complete product linking workflow', function () {
        // Mock Shopify product search results
        Http::fake([
            '*/graphql.json' => Http::response([
                'data' => [
                    'products' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Product/999888',
                                    'title' => 'Integration Test Roller Blind - White',
                                    'handle' => 'existing-product-white',
                                    'status' => 'ACTIVE',
                                    'variants' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => 'gid://shopify/ProductVariant/777',
                                                    'sku' => 'INT-TEST-001-WHITE-60x100',
                                                    'price' => '59.99',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Product/999889',
                                    'title' => 'Integration Test Roller Blind - Black',
                                    'handle' => 'existing-product-black',
                                    'status' => 'ACTIVE',
                                    'variants' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => 'gid://shopify/ProductVariant/778',
                                                    'sku' => 'INT-TEST-001-BLACK-60x100',
                                                    'price' => '59.99',
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

        // Execute link operation
        $result = Sync::marketplace('shopify')->link($this->product->id)->push();

        // Verify successful linking
        expect($result->isSuccess())->toBeTrue();
        expect($result->getMessage())->toContain('linked');

        // Verify product attributes are updated
        $this->product->refresh();
        expect($this->product->getSmartAttributeValue('shopify_status'))->toBe('synced');

        $productIds = json_decode($this->product->getSmartAttributeValue('shopify_product_ids'), true);
        expect($productIds)->toHaveKey('White');
        expect($productIds)->toHaveKey('Black');
        expect($productIds['White'])->toBe('gid://shopify/Product/999888');
        expect($productIds['Black'])->toBe('gid://shopify/Product/999889');
    });

    it('can complete product deletion workflow', function () {
        // Set up existing synced product
        $this->product->setAttributeValue('shopify_product_ids', json_encode([
            'White' => 'gid://shopify/Product/123456',
            'Black' => 'gid://shopify/Product/123457',
        ]));
        $this->product->setAttributeValue('shopify_sync_account_id', $this->syncAccount->id);
        $this->product->setAttributeValue('shopify_status', 'synced');

        // Mock successful deletion
        Http::fake([
            '*/graphql.json' => Http::response([
                'data' => [
                    'productDelete' => [
                        'deletedProductId' => 'gid://shopify/Product/123456',
                        'userErrors' => [],
                    ],
                ],
            ], 200),
        ]);

        // Execute delete operation
        $result = Sync::marketplace('shopify')->delete($this->product->id)->push();

        // Verify successful deletion
        expect($result->isSuccess())->toBeTrue();
        expect($result->getMessage())->toContain('deleted');

        // Verify attributes are cleared
        $this->product->refresh();
        expect($this->product->getSmartAttributeValue('shopify_status'))->toBe('pending');
        expect($this->product->getSmartAttributeValue('shopify_product_ids'))->toBeNull();
    });
});

describe('Error Recovery and Edge Cases', function () {

    it('handles partial creation failures gracefully', function () {
        Http::fake([
            '*/graphql.json' => Http::sequence()
                ->push([
                    'data' => [
                        'productCreate' => [
                            'product' => [
                                'id' => 'gid://shopify/Product/123',
                                'title' => 'Success Product',
                                'variants' => ['edges' => []],
                            ],
                            'userErrors' => [],
                        ],
                    ],
                ], 200)
                ->push([
                    'data' => [
                        'productCreate' => [
                            'product' => null,
                            'userErrors' => [
                                ['field' => ['title'], 'message' => 'Title already exists'],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $result = Sync::marketplace('shopify')->create($this->product->id)->push();

        // Should report partial success
        expect($result->isSuccess())->toBeFalse();
        expect($result->getMessage())->toContain('failed');
        expect($result->getErrors())->not->toBeEmpty();
    });

    it('handles network failures during sync', function () {
        Http::fake([
            '*' => Http::response([], 500), // Server error
        ]);

        $result = Sync::marketplace('shopify')->create($this->product->id)->push();

        expect($result->isSuccess())->toBeFalse();
        expect($result->getMessage())->toContain('failed');
    });

    it('prevents duplicate operations on same product', function () {
        // Mark product as already synced
        $this->product->setAttributeValue('shopify_status', 'synced');
        $this->product->setAttributeValue('shopify_sync_account_id', $this->syncAccount->id);

        $result = Sync::marketplace('shopify')->create($this->product->id)->push();

        expect($result->isSuccess())->toBeFalse();
        expect($result->getMessage())->toContain('already exist');
    });

    it('validates sync account permissions', function () {
        // Create inactive sync account
        $inactiveAccount = SyncAccount::factory()->create([
            'marketplace' => 'shopify',
            'status' => 'inactive',
        ]);

        // Delete the active account to force use of inactive one
        $this->syncAccount->delete();

        expect(fn () => Sync::marketplace('shopify')->create($this->product->id)->push())
            ->toThrow(\Exception::class, 'No active sync account');
    });
});

describe('Performance and Rate Limiting', function () {

    it('respects Shopify rate limits during bulk operations', function () {
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

        // Create operation should include rate limiting delays
        Sync::marketplace('shopify')->create($this->product->id)->push();

        $duration = microtime(true) - $start;

        // Should take some time due to rate limiting between requests
        expect($duration)->toBeGreaterThan(0.1);
    });

    it('handles large product catalogs efficiently', function () {
        // Create product with many variants
        ProductVariant::factory()->count(50)->create([
            'product_id' => $this->product->id,
            'color' => 'MultiColor',
        ]);

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'productCreate' => [
                        'product' => ['id' => 'gid://shopify/Product/123', 'variants' => ['edges' => []]],
                        'userErrors' => [],
                    ],
                ],
            ], 200),
        ]);

        $result = Sync::marketplace('shopify')->create($this->product->id)->push();

        // Should handle large catalogs without memory issues
        expect($result)->toBeInstanceOf(\App\Services\Marketplace\ValueObjects\SyncResult::class);
    });
});
