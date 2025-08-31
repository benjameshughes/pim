<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncAccount;
use App\Services\Marketplace\Facades\Sync;
use App\Services\Marketplace\ValueObjects\SyncResult;

beforeEach(function () {
    // Create test sync account using actual schema
    $this->syncAccount = SyncAccount::factory()->create([
        'name' => 'Test Shopify',
        'channel' => 'shopify',
        'display_name' => 'Test Shopify Store',
        'is_active' => true,
        'credentials' => json_encode([
            'shop_domain' => 'test-store.myshopify.com',
            'access_token' => 'test-token'
        ]),
        'settings' => json_encode([
            'auto_sync' => false,
            'sync_variants' => true
        ])
    ]);
    
    // Create test product with variants
    $this->product = Product::factory()->create([
        'name' => 'Test Product',
        'parent_sku' => 'TEST-001',
        'status' => 'active'
    ]);
    
    $this->variants = ProductVariant::factory()->count(2)->create([
        'product_id' => $this->product->id,
        'color' => 'White',
        'width' => 60,
        'drop' => 100,
        'price' => 49.99
    ]);
});

describe('Sync Facade', function () {
    
    it('can instantiate marketplace adapter', function () {
        $adapter = Sync::marketplace('shopify');
        
        expect($adapter)->toBeInstanceOf(\App\Services\Marketplace\Adapters\ShopifyAdapter::class);
    });
    
    it('throws exception for unsupported marketplace', function () {
        expect(fn() => Sync::marketplace('unsupported'))
            ->toThrow(\InvalidArgumentException::class, 'Unsupported marketplace: unsupported');
    });
    
    it('can chain operation methods', function () {
        $adapter = Sync::marketplace('shopify')
            ->create($this->product->id);
            
        expect($adapter)->toBeInstanceOf(\App\Services\Marketplace\Adapters\ShopifyAdapter::class);
    });
});

describe('Shopify Adapter Operations', function () {
    
    it('can set up create operation', function () {
        $adapter = Sync::marketplace('shopify')->create($this->product->id);
        
        expect($adapter)->toHaveProperty('operationType', 'create')
                        ->toHaveProperty('currentProductId', $this->product->id);
    });
    
    it('can set up update operation', function () {
        $adapter = Sync::marketplace('shopify')
            ->update($this->product->id)
            ->title('New Title');
            
        expect($adapter)->toHaveProperty('operationType', 'update')
                        ->toHaveProperty('fieldsToUpdate', ['title' => 'New Title']);
    });
    
    it('can set up fullUpdate operation', function () {
        $adapter = Sync::marketplace('shopify')->fullUpdate($this->product->id);
        
        expect($adapter)->toHaveProperty('operationType', 'fullUpdate')
                        ->toHaveProperty('currentProductId', $this->product->id);
    });
    
    it('can set up delete operation', function () {
        $adapter = Sync::marketplace('shopify')->delete($this->product->id);
        
        expect($adapter)->toHaveProperty('operationType', 'delete')
                        ->toHaveProperty('currentProductId', $this->product->id);
    });
    
    it('can set up link operation', function () {
        $adapter = Sync::marketplace('shopify')->link($this->product->id);
        
        expect($adapter)->toHaveProperty('operationType', 'link')
                        ->toHaveProperty('currentProductId', $this->product->id);
    });
    
    it('backwards compatibility - redirects recreate to fullUpdate', function () {
        $adapter = Sync::marketplace('shopify')->recreate($this->product->id);
        
        expect($adapter)->toHaveProperty('operationType', 'fullUpdate')
                        ->toHaveProperty('currentProductId', $this->product->id);
    });
});

describe('Update Field Methods', function () {
    
    it('can set title field for update', function () {
        $adapter = Sync::marketplace('shopify')
            ->update($this->product->id)
            ->title('Updated Title');
            
        expect($adapter)->toHaveProperty('fieldsToUpdate', ['title' => 'Updated Title']);
    });
    
    it('can set pricing field for update', function () {
        $adapter = Sync::marketplace('shopify')
            ->update($this->product->id)
            ->pricing(['variant_1' => 99.99]);
            
        expect($adapter)->toHaveProperty('fieldsToUpdate', ['pricing' => ['variant_1' => 99.99]]);
    });
    
    it('can chain multiple update fields', function () {
        $adapter = Sync::marketplace('shopify')
            ->update($this->product->id)
            ->title('Updated Title')
            ->pricing(['variant_1' => 99.99]);
            
        expect($adapter)->toHaveProperty('fieldsToUpdate', [
            'title' => 'Updated Title',
            'pricing' => ['variant_1' => 99.99]
        ]);
    });
});

describe('Push Method', function () {
    
    it('requires operation type to be set before push', function () {
        expect(fn() => Sync::marketplace('shopify')->push())
            ->toThrow(\Exception::class);
    });
    
    it('requires sync account to be configured', function () {
        // Clear all sync accounts
        SyncAccount::query()->delete();
        
        expect(fn() => Sync::marketplace('shopify')->create($this->product->id)->push())
            ->toThrow(\Exception::class);
    });
});

describe('Sync Account Management', function () {
    
    it('finds active sync account for marketplace', function () {
        $adapter = Sync::marketplace('shopify')->create($this->product->id);
        
        // Use reflection to test protected method
        $reflection = new ReflectionClass($adapter);
        $method = $reflection->getMethod('requireSyncAccount');
        $method->setAccessible(true);
        
        $account = $method->invoke($adapter);
        
        expect($account)->toBeInstanceOf(SyncAccount::class)
                        ->toHaveProperty('channel', 'shopify');
    });
    
    it('throws exception when no active sync account exists', function () {
        $this->syncAccount->update(['is_active' => false]);
        
        $adapter = Sync::marketplace('shopify')->create($this->product->id);
        
        expect(fn() => $adapter->push())
            ->toThrow(\Exception::class);
    });
});

describe('Error Handling', function () {
    
    it('handles invalid product ID gracefully', function () {
        $adapter = Sync::marketplace('shopify')->create(999999);
        
        expect(fn() => $adapter->push())
            ->toThrow(\Exception::class);
    });
    
    it('validates operation type in push method', function () {
        $adapter = Sync::marketplace('shopify');
        
        // Manually set invalid operation type using reflection
        $reflection = new ReflectionClass($adapter);
        $property = $reflection->getProperty('operationType');
        $property->setAccessible(true);
        $property->setValue($adapter, 'invalid');
        
        expect(fn() => $adapter->push())
            ->toThrow(\Exception::class);
    });
});