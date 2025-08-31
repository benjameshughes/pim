<?php

use App\Services\Marketplace\Shopify\ShopifyTaxonomyHelper;

describe('ShopifyTaxonomyHelper', function () {
    
    it('can detect blinds category from title', function () {
        $productData = [
            'title' => 'Premium Roller Blind - White',
            'productType' => 'Window Blinds',
            'descriptionHtml' => 'High quality roller blind for modern homes'
        ];
        
        $category = ShopifyTaxonomyHelper::detectCategory($productData);
        
        expect($category)->toBe('gid://shopify/TaxonomyCategory/ho-1-1-6-1');
    });
    
    it('can detect specific blind types', function () {
        $rollerBlindData = [
            'title' => 'Premium Roller Blind Collection',
            'productType' => 'Window Treatments'
        ];
        
        $verticalBlindData = [
            'title' => 'Vertical Blind Set',
            'productType' => 'Window Treatments'
        ];
        
        $venetianBlindData = [
            'title' => 'Venetian Blind Premium',
            'productType' => 'Window Treatments'
        ];
        
        expect(ShopifyTaxonomyHelper::detectBlindsCategory($rollerBlindData))
            ->toBe('gid://shopify/TaxonomyCategory/ho-1-1-6-1');
            
        expect(ShopifyTaxonomyHelper::detectBlindsCategory($verticalBlindData))
            ->toBe('gid://shopify/TaxonomyCategory/ho-1-1-6-2');
            
        expect(ShopifyTaxonomyHelper::detectBlindsCategory($venetianBlindData))
            ->toBe('gid://shopify/TaxonomyCategory/ho-1-1-6-3');
    });
    
    it('can detect window treatment categories', function () {
        $curtainData = [
            'title' => 'Premium Curtain Collection',
            'productType' => 'Window Treatments'
        ];
        
        $shutterData = [
            'title' => 'Wooden Shutters',
            'productType' => 'Window Treatments'
        ];
        
        expect(ShopifyTaxonomyHelper::detectCategory($curtainData))
            ->toBe('gid://shopify/TaxonomyCategory/ho-1-1');
            
        expect(ShopifyTaxonomyHelper::detectCategory($shutterData))
            ->toBe('gid://shopify/TaxonomyCategory/ho-1-1-7');
    });
    
    it('returns null for unrecognized products', function () {
        $unknownData = [
            'title' => 'Random Electronics Device',
            'productType' => 'Electronics'
        ];
        
        $category = ShopifyTaxonomyHelper::detectCategory($unknownData);
        
        expect($category)->toBeNull();
    });
    
    it('can get category name from ID', function () {
        $categoryId = 'gid://shopify/TaxonomyCategory/ho-1-1-6';
        $name = ShopifyTaxonomyHelper::getCategoryName($categoryId);
        
        expect($name)->toBe('blinds');
    });
    
    it('validates category ID format', function () {
        $validId = 'gid://shopify/TaxonomyCategory/ho-1-1-6';
        $invalidId = 'invalid-id';
        
        expect(ShopifyTaxonomyHelper::isValidCategoryId($validId))->toBeTrue();
        expect(ShopifyTaxonomyHelper::isValidCategoryId($invalidId))->toBeFalse();
    });
    
    it('returns available categories', function () {
        $categories = ShopifyTaxonomyHelper::getAvailableCategories();
        
        expect($categories)->toBeArray();
        expect($categories)->toHaveKey('blinds');
        expect($categories)->toHaveKey('roller blind');
        expect($categories)->toHaveKey('curtains');
        expect($categories)->toHaveKey('shutters');
    });
    
    it('handles case insensitive matching', function () {
        $upperCaseData = [
            'title' => 'PREMIUM ROLLER BLIND',
            'productType' => 'WINDOW BLINDS'
        ];
        
        $lowerCaseData = [
            'title' => 'premium roller blind',
            'productType' => 'window blinds'
        ];
        
        $upperResult = ShopifyTaxonomyHelper::detectCategory($upperCaseData);
        $lowerResult = ShopifyTaxonomyHelper::detectCategory($lowerCaseData);
        
        expect($upperResult)->toBe($lowerResult);
        expect($upperResult)->toBe('gid://shopify/TaxonomyCategory/ho-1-1-6-1');
    });
    
    it('matches keywords in description', function () {
        $dataWithDescKeyword = [
            'title' => 'Premium Window Treatment',
            'productType' => 'Home Decor',
            'descriptionHtml' => 'This beautiful blind will enhance your room'
        ];
        
        $category = ShopifyTaxonomyHelper::detectCategory($dataWithDescKeyword);
        
        expect($category)->toBe('gid://shopify/TaxonomyCategory/ho-1');
    });
    
    it('prioritizes specific matches over generic ones', function () {
        // "roller blind" should match the specific roller blind category
        // rather than the generic "blinds" category
        $rollerBlindData = [
            'title' => 'Premium Roller Blind Collection',
            'productType' => 'Window Blinds'
        ];
        
        $category = ShopifyTaxonomyHelper::detectCategory($rollerBlindData);
        
        // Should match "roller blind" specifically, not just "blind"
        expect($category)->toBe('gid://shopify/TaxonomyCategory/ho-1-1-6-1');
    });
    
    it('handles empty or minimal data gracefully', function () {
        $emptyData = [];
        $minimalData = ['title' => ''];
        
        expect(ShopifyTaxonomyHelper::detectCategory($emptyData))->toBeNull();
        expect(ShopifyTaxonomyHelper::detectCategory($minimalData))->toBeNull();
    });
});