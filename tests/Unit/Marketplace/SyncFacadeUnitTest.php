<?php

use App\Services\Marketplace\Facades\Sync;
use App\Services\Marketplace\Adapters\ShopifyAdapter;

describe('Sync Facade Unit Tests', function () {
    
    it('can instantiate marketplace adapter', function () {
        $adapter = Sync::marketplace('shopify');
        
        expect($adapter)->toBeInstanceOf(ShopifyAdapter::class);
    });
    
    it('throws exception for unsupported marketplace', function () {
        expect(fn() => Sync::marketplace('unsupported'))
            ->toThrow(\InvalidArgumentException::class, 'Unsupported marketplace: unsupported');
    });
    
    it('can chain operation methods', function () {
        $adapter = Sync::marketplace('shopify')->create(1);
        
        expect($adapter)->toBeInstanceOf(ShopifyAdapter::class);
        
        // Test that operation type is set using reflection
        $reflection = new ReflectionClass($adapter);
        $operationProperty = $reflection->getProperty('operationType');
        $operationProperty->setAccessible(true);
        $operationType = $operationProperty->getValue($adapter);
        
        expect($operationType)->toBe('create');
    });
    
    it('supports fullUpdate operation', function () {
        $adapter = Sync::marketplace('shopify')->fullUpdate(1);
        
        // Test that operation type is set
        $reflection = new ReflectionClass($adapter);
        $operationProperty = $reflection->getProperty('operationType');
        $operationProperty->setAccessible(true);
        $operationType = $operationProperty->getValue($adapter);
        
        expect($operationType)->toBe('fullUpdate');
    });
    
    it('backwards compatibility - recreate redirects to fullUpdate', function () {
        $adapter = Sync::marketplace('shopify')->recreate(1);
        
        // Test that operation type is set to fullUpdate
        $reflection = new ReflectionClass($adapter);
        $operationProperty = $reflection->getProperty('operationType');
        $operationProperty->setAccessible(true);
        $operationType = $operationProperty->getValue($adapter);
        
        expect($operationType)->toBe('fullUpdate');
    });
    
    it('can set update fields', function () {
        $adapter = Sync::marketplace('shopify')
            ->update(1)
            ->title('New Title')
            ->pricing(['variant_1' => 99.99]);
        
        // Test fields are set using reflection
        $reflection = new ReflectionClass($adapter);
        $fieldsProperty = $reflection->getProperty('fieldsToUpdate');
        $fieldsProperty->setAccessible(true);
        $fields = $fieldsProperty->getValue($adapter);
        
        expect($fields)->toBe([
            'title' => 'New Title',
            'pricing' => ['variant_1' => 99.99]
        ]);
    });
    
    it('can set all operation types', function () {
        $createAdapter = Sync::marketplace('shopify')->create(1);
        $updateAdapter = Sync::marketplace('shopify')->update(1);
        $deleteAdapter = Sync::marketplace('shopify')->delete(1);
        $linkAdapter = Sync::marketplace('shopify')->link(1);
        $fullUpdateAdapter = Sync::marketplace('shopify')->fullUpdate(1);
        
        // Test operation types using reflection
        $reflection = new ReflectionClass($createAdapter);
        $operationProperty = $reflection->getProperty('operationType');
        $operationProperty->setAccessible(true);
        
        expect($operationProperty->getValue($createAdapter))->toBe('create');
        expect($operationProperty->getValue($updateAdapter))->toBe('update');
        expect($operationProperty->getValue($deleteAdapter))->toBe('delete');
        expect($operationProperty->getValue($linkAdapter))->toBe('link');
        expect($operationProperty->getValue($fullUpdateAdapter))->toBe('fullUpdate');
    });
    
    it('stores product ID correctly', function () {
        $adapter = Sync::marketplace('shopify')->create(12345);
        
        // Test product ID is set using reflection
        $reflection = new ReflectionClass($adapter);
        $productProperty = $reflection->getProperty('currentProductId');
        $productProperty->setAccessible(true);
        $productId = $productProperty->getValue($adapter);
        
        expect($productId)->toBe(12345);
    });
});