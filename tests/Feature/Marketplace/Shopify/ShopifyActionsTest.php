<?php

use App\Actions\Marketplace\Shopify\CreateShopifyProductsAction;
use App\Actions\Marketplace\Shopify\UpdateShopifyProductsAction;
use App\Actions\Marketplace\Shopify\FullUpdateShopifyProductsAction;
use App\Actions\Marketplace\Shopify\DeleteShopifyProductsAction;
use App\Actions\Marketplace\Shopify\LinkShopifyProductsAction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncAccount;
use App\Services\Marketplace\ValueObjects\MarketplaceProduct;
use App\Services\Marketplace\ValueObjects\SyncResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test sync account
    $this->syncAccount = SyncAccount::factory()->create([
        'name' => 'Test Shopify Store',
        'marketplace' => 'shopify',
        'shop_domain' => 'test-store.myshopify.com',
        'access_token' => 'test-token-12345',
        'status' => 'active'
    ]);
    
    // Create test product with variants
    $this->product = Product::factory()->create([
        'name' => 'Test Roller Blind',
        'parent_sku' => 'TEST-ROLLER-001',
        'description' => 'Premium roller blind for testing',
        'status' => 'active'
    ]);
    
    // Create variants in different colors
    $this->whiteVariant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'sku' => 'TEST-ROLLER-001-WHITE-60x100',
        'color' => 'White',
        'width' => 60,
        'drop' => 100,
        'price' => 44.99
    ]);
    
    $this->blackVariant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'sku' => 'TEST-ROLLER-001-BLACK-60x100',
        'color' => 'Black',
        'width' => 60,
        'drop' => 100,
        'price' => 44.99
    ]);
    
    // Mock MarketplaceProduct with proper structure
    $this->marketplaceProduct = new MarketplaceProduct([
        [
            'productInput' => [
                'title' => 'Test Roller Blind - White',
                'descriptionHtml' => 'Premium roller blind for testing',
                'vendor' => 'Blinds Outlet',
                'productType' => 'Window Blinds',
                'status' => 'ACTIVE',
                'metafields' => [],
                'productOptions' => []
            ],
            'variants' => [
                [
                    'sku' => 'TEST-ROLLER-001-WHITE-60x100',
                    'price' => '44.99',
                    'title' => 'White 60x100',
                    'barcode' => null,
                    'compareAtPrice' => null,
                    'inventoryQuantity' => 0
                ]
            ],
            '_internal' => [
                'original_product_id' => $this->product->id,
                'color_group' => 'White',
                'variant_count' => 1
            ]
        ],
        [
            'productInput' => [
                'title' => 'Test Roller Blind - Black',
                'descriptionHtml' => 'Premium roller blind for testing',
                'vendor' => 'Blinds Outlet',
                'productType' => 'Window Blinds',
                'status' => 'ACTIVE',
                'metafields' => [],
                'productOptions' => []
            ],
            'variants' => [
                [
                    'sku' => 'TEST-ROLLER-001-BLACK-60x100',
                    'price' => '44.99',
                    'title' => 'Black 60x100',
                    'barcode' => null,
                    'compareAtPrice' => null,
                    'inventoryQuantity' => 0
                ]
            ],
            '_internal' => [
                'original_product_id' => $this->product->id,
                'color_group' => 'Black',
                'variant_count' => 1
            ]
        ]
    ], ['original_product_id' => $this->product->id]);
});

describe('CreateShopifyProductsAction', function () {
    
    it('can be instantiated', function () {
        $action = new CreateShopifyProductsAction();
        expect($action)->toBeInstanceOf(CreateShopifyProductsAction::class);
    });
    
    it('returns SyncResult on execute', function () {
        $action = new CreateShopifyProductsAction();
        
        // Mock the GraphQL client to avoid actual API calls
        $this->mock(\App\Services\Marketplace\Shopify\ShopifyGraphQLClient::class, function ($mock) {
            $mock->shouldReceive('createProducts')
                 ->andReturn([
                     [
                         'success' => true,
                         'product' => [
                             'id' => 'gid://shopify/Product/123',
                             'title' => 'Test Product - White',
                             'handle' => 'test-product-white',
                             'variants' => [
                                 'edges' => [
                                     ['node' => ['id' => 'gid://shopify/ProductVariant/456', 'sku' => '', 'price' => '0.00']]
                                 ]
                             ]
                         ],
                         'errors' => []
                     ]
                 ]);
            $mock->shouldReceive('updateSingleVariant')->andReturn(['success' => true]);
        });
        
        $result = $action->execute($this->marketplaceProduct, $this->syncAccount);
        
        expect($result)->toBeInstanceOf(SyncResult::class);
    });
    
    it('prevents duplicate creation by default', function () {
        // Set product as already synced
        $this->product->setAttributeValue('shopify_status', 'synced');
        $this->product->setAttributeValue('shopify_sync_account_id', $this->syncAccount->id);
        
        $action = new CreateShopifyProductsAction();
        $result = $action->execute($this->marketplaceProduct, $this->syncAccount);
        
        expect($result->isSuccess())->toBeFalse();
        expect($result->getMessage())->toContain('already exist');
    });
    
    it('can force creation even if duplicates exist', function () {
        // Set product as already synced
        $this->product->setAttributeValue('shopify_status', 'synced');
        
        $action = new CreateShopifyProductsAction();
        
        // Mock successful creation
        $this->mock(\App\Services\Marketplace\Shopify\ShopifyGraphQLClient::class, function ($mock) {
            $mock->shouldReceive('createProducts')->andReturn([
                [
                    'success' => true,
                    'product' => ['id' => 'gid://shopify/Product/123', 'title' => 'Test', 'handle' => 'test', 'variants' => ['edges' => []]],
                    'errors' => []
                ]
            ]);
        });
        
        $result = $action->execute($this->marketplaceProduct, $this->syncAccount, true);
        
        expect($result)->toBeInstanceOf(SyncResult::class);
    });
});

describe('UpdateShopifyProductsAction', function () {
    
    it('can be instantiated', function () {
        $action = new UpdateShopifyProductsAction();
        expect($action)->toBeInstanceOf(UpdateShopifyProductsAction::class);
    });
    
    it('requires existing products for update', function () {
        $action = new UpdateShopifyProductsAction();
        $result = $action->execute($this->product->id, ['title' => 'New Title'], $this->syncAccount);
        
        expect($result->isSuccess())->toBeFalse();
        expect($result->getMessage())->toContain('No existing products found');
    });
    
    it('can update product title', function () {
        // Set up existing products
        $this->product->setAttributeValue('shopify_product_ids', json_encode([
            'White' => 'gid://shopify/Product/123',
            'Black' => 'gid://shopify/Product/124'
        ]));
        $this->product->setAttributeValue('shopify_sync_account_id', $this->syncAccount->id);
        $this->product->setAttributeValue('shopify_status', 'synced');
        
        // Mock successful update
        $this->mock(\App\Services\Marketplace\Shopify\ShopifyGraphQLClient::class, function ($mock) {
            $mock->shouldReceive('updateProductTitle')
                 ->andReturn(['productUpdate' => ['userErrors' => []]]);
        });
        
        $action = new UpdateShopifyProductsAction();
        $result = $action->execute($this->product->id, ['title' => 'Updated Title'], $this->syncAccount);
        
        expect($result)->toBeInstanceOf(SyncResult::class);
    });
});

describe('FullUpdateShopifyProductsAction', function () {
    
    it('can be instantiated', function () {
        $action = new FullUpdateShopifyProductsAction();
        expect($action)->toBeInstanceOf(FullUpdateShopifyProductsAction::class);
    });
    
    it('falls back to create when no existing products', function () {
        $action = new FullUpdateShopifyProductsAction();
        
        // Mock create action
        $this->mock(\App\Actions\Marketplace\Shopify\CreateShopifyProductsAction::class, function ($mock) {
            $mock->shouldReceive('execute')
                 ->andReturn(SyncResult::success('Created successfully'));
        });
        
        $result = $action->execute($this->marketplaceProduct, $this->syncAccount);
        
        expect($result->isSuccess())->toBeTrue();
        expect($result->getMessage())->toContain('created new products instead');
    });
    
    it('performs comprehensive update when products exist', function () {
        // Set up existing products
        $this->product->setAttributeValue('shopify_product_ids', json_encode([
            'White' => 'gid://shopify/Product/123'
        ]));
        $this->product->setAttributeValue('shopify_sync_account_id', $this->syncAccount->id);
        
        // Mock GraphQL client
        $this->mock(\App\Services\Marketplace\Shopify\ShopifyGraphQLClient::class, function ($mock) {
            $mock->shouldReceive('updateProduct')
                 ->andReturn(['productUpdate' => ['userErrors' => []]]);
            $mock->shouldReceive('getProduct')
                 ->andReturn(['product' => ['variants' => ['edges' => []]]]);
            $mock->shouldReceive('updateProductVariants')
                 ->andReturn(['productVariantsBulkUpdate' => ['userErrors' => []]]);
        });
        
        $action = new FullUpdateShopifyProductsAction();
        $result = $action->execute($this->marketplaceProduct, $this->syncAccount);
        
        expect($result)->toBeInstanceOf(SyncResult::class);
    });
});

describe('DeleteShopifyProductsAction', function () {
    
    it('can be instantiated', function () {
        $action = new DeleteShopifyProductsAction();
        expect($action)->toBeInstanceOf(DeleteShopifyProductsAction::class);
    });
    
    it('handles non-existent products gracefully', function () {
        $action = new DeleteShopifyProductsAction();
        $result = $action->execute(999999, $this->syncAccount);
        
        expect($result->isSuccess())->toBeFalse();
        expect($result->getMessage())->toContain('not found');
    });
    
    it('can delete existing products', function () {
        // Set up existing products
        $this->product->setAttributeValue('shopify_product_ids', json_encode([
            'White' => 'gid://shopify/Product/123',
            'Black' => 'gid://shopify/Product/124'
        ]));
        $this->product->setAttributeValue('shopify_sync_account_id', $this->syncAccount->id);
        
        // Mock successful deletion
        $this->mock(\App\Services\Marketplace\Shopify\ShopifyGraphQLClient::class, function ($mock) {
            $mock->shouldReceive('deleteProduct')
                 ->andReturn(['productDelete' => ['userErrors' => []]]);
        });
        
        $action = new DeleteShopifyProductsAction();
        $result = $action->execute($this->product->id, $this->syncAccount);
        
        expect($result)->toBeInstanceOf(SyncResult::class);
    });
});

describe('LinkShopifyProductsAction', function () {
    
    it('can be instantiated', function () {
        $action = new LinkShopifyProductsAction();
        expect($action)->toBeInstanceOf(LinkShopifyProductsAction::class);
    });
    
    it('requires variants with SKUs for linking', function () {
        // Create product without variants
        $emptyProduct = Product::factory()->create();
        
        $action = new LinkShopifyProductsAction();
        $result = $action->execute($emptyProduct->id, $this->syncAccount);
        
        expect($result->isSuccess())->toBeFalse();
        expect($result->getMessage())->toContain('no variants');
    });
    
    it('prevents linking already synced products', function () {
        $this->product->setAttributeValue('shopify_status', 'synced');
        
        $action = new LinkShopifyProductsAction();
        $result = $action->execute($this->product->id, $this->syncAccount);
        
        expect($result->isSuccess())->toBeFalse();
        expect($result->getMessage())->toContain('already linked');
    });
    
    it('can link products when matching SKUs found', function () {
        // Mock successful SKU search
        $this->mock(\App\Services\Marketplace\Shopify\ShopifyGraphQLClient::class, function ($mock) {
            $mock->shouldReceive('searchProductsBySku')
                 ->andReturn([
                     [
                         'id' => 'gid://shopify/Product/123',
                         'title' => 'Test Roller Blind - White',
                         'handle' => 'test-roller-blind-white',
                         'status' => 'ACTIVE',
                         'variants' => [
                             [
                                 'id' => 'gid://shopify/ProductVariant/456',
                                 'sku' => 'TEST-ROLLER-001-WHITE-60x100',
                                 'price' => '44.99'
                             ]
                         ]
                     ]
                 ]);
        });
        
        $action = new LinkShopifyProductsAction();
        $result = $action->execute($this->product->id, $this->syncAccount);
        
        expect($result)->toBeInstanceOf(SyncResult::class);
    });
});

describe('Action Error Handling', function () {
    
    it('handles GraphQL client exceptions gracefully', function () {
        $this->mock(\App\Services\Marketplace\Shopify\ShopifyGraphQLClient::class, function ($mock) {
            $mock->shouldReceive('createProducts')
                 ->andThrow(new \Exception('GraphQL API Error'));
        });
        
        $action = new CreateShopifyProductsAction();
        $result = $action->execute($this->marketplaceProduct, $this->syncAccount);
        
        expect($result->isSuccess())->toBeFalse();
        expect($result->getMessage())->toContain('GraphQL API Error');
    });
    
    it('validates sync account marketplace type', function () {
        $ebayAccount = SyncAccount::factory()->create(['marketplace' => 'ebay']);
        
        $action = new CreateShopifyProductsAction();
        $result = $action->execute($this->marketplaceProduct, $ebayAccount);
        
        expect($result->isSuccess())->toBeFalse();
    });
});