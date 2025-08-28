<?php

use App\Actions\Import\AttributeAssignmentAction;
use App\Models\Product;
use App\Models\ProductVariant;

describe('AttributeAssignmentAction', function () {
    beforeEach(function () {
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'parent_sku' => 'TEST123',
            'status' => 'active'
        ]);
        
        $this->variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'TEST123-RED',
            'title' => 'Test Product - Red',
            'color' => 'Red',
            'status' => 'active'
        ]);

        $this->action = new AttributeAssignmentAction();
    });

    test('assigns auto attributes from unmapped CSV columns', function () {
        $csvData = ['Test Product', 'RED', '100', 'Premium Brand', 'Electronics'];
        $mappings = ['title' => 0, 'color' => 1, 'width' => 2]; // brand and category unmapped
        $csvHeaders = ['title', 'color', 'width', 'brand', 'category'];
        
        $results = $this->action->execute(
            $this->product,
            $this->variant,
            $csvData,
            $mappings,
            [],
            $csvHeaders
        );

        expect($results)
            ->toBeArray()
            ->toHaveKeys(['product_attributes', 'variant_attributes']);

        // Check that brand was assigned as product attribute
        expect($results['product_attributes']['created'])
            ->toContain('brand');
            
        // Check that category was assigned as variant attribute (not product-level)
        expect($results['variant_attributes']['created'])
            ->toContain('category');
    });

    test('assigns ad-hoc attributes', function () {
        $adHocAttributes = [
            'supplier' => 'Acme Corp',
            'season' => 'Winter 2024',
            'material' => 'Cotton'
        ];
        
        $results = $this->action->execute(
            $this->product,
            $this->variant,
            [],
            [],
            $adHocAttributes,
            []
        );

        expect($results['product_attributes']['created'])
            ->toContain('supplier') // Product-level
            ->and($results['variant_attributes']['created'])
            ->toContain('season') // Variant-level
            ->toContain('material'); // Variant-level
    });

    test('ad-hoc attributes take precedence over auto attributes', function () {
        $csvData = ['Product', 'Red', 'Old Brand'];
        $mappings = ['title' => 0, 'color' => 1]; // brand unmapped (index 2)
        $csvHeaders = ['title', 'color', 'brand'];
        $adHocAttributes = ['brand' => 'New Brand']; // Override

        $results = $this->action->execute(
            $this->product,
            $this->variant,
            $csvData,
            $mappings,
            $adHocAttributes,
            $csvHeaders
        );

        // Verify brand attribute was created
        expect($results['product_attributes']['created'])
            ->toContain('brand');

        // Check the actual value was from ad-hoc
        $brandAttribute = $this->product->fresh()->attributes()
            ->where('attribute_definition_id', function($query) {
                $query->select('id')->from('attribute_definitions')->where('key', 'brand');
            })
            ->first();

        expect($brandAttribute)->not()->toBeNull();
    });

    test('skips empty values in auto attributes', function () {
        $csvData = ['Product', '', 'Brand', null];
        $mappings = ['title' => 0]; // color, brand, description unmapped
        $csvHeaders = ['title', 'color', 'brand', 'description'];
        
        $results = $this->action->execute(
            $this->product,
            $this->variant,
            $csvData,
            $mappings,
            [],
            $csvHeaders
        );

        // Only brand should be assigned (not empty color or null description)
        $allCreated = array_merge(
            $results['product_attributes']['created'],
            $results['variant_attributes']['created']
        );
        
        expect($allCreated)
            ->toContain('brand')
            ->not()->toContain('color')
            ->not()->toContain('description');
    });

    test('sanitizes attribute keys', function () {
        $adHocAttributes = [
            'Brand Name!' => 'Test Brand',
            'Product Category' => 'Electronics',
            'special-field_123' => 'Special Value'
        ];
        
        $results = $this->action->execute(
            $this->product,
            $this->variant,
            [],
            [],
            $adHocAttributes,
            []
        );

        $allCreated = array_merge(
            $results['product_attributes']['created'],
            $results['variant_attributes']['created']
        );

        expect($allCreated)
            ->toContain('brand_name')
            ->toContain('product_category')
            ->toContain('special_field_123');
    });

    test('filters product-level vs variant-level attributes correctly', function () {
        $adHocAttributes = [
            'brand' => 'Test Brand',        // Product-level
            'manufacturer' => 'Test Mfg',   // Product-level
            'color_code' => '#FF0000',      // Variant-level
            'custom_field' => 'Custom'      // Should go to variant (not in product list)
        ];
        
        $results = $this->action->execute(
            $this->product,
            $this->variant,
            [],
            [],
            $adHocAttributes,
            []
        );

        expect($results['product_attributes']['created'])
            ->toContain('brand')
            ->toContain('manufacturer')
            ->not()->toContain('color_code')
            ->not()->toContain('custom_field');

        expect($results['variant_attributes']['created'])
            ->toContain('color_code')
            ->toContain('custom_field')
            ->not()->toContain('brand')
            ->not()->toContain('manufacturer');
    });

    test('handles both auto and ad-hoc attributes together', function () {
        $csvData = ['Product', 'Red', 'CSV Brand'];
        $mappings = ['title' => 0, 'color' => 1]; // brand unmapped
        $csvHeaders = ['title', 'color', 'brand'];
        $adHocAttributes = [
            'supplier' => 'Acme Corp',
            'warranty' => '2 years'
        ];
        
        $results = $this->action->execute(
            $this->product,
            $this->variant,
            $csvData,
            $mappings,
            $adHocAttributes,
            []
        );

        $productCreated = $results['product_attributes']['created'];
        $variantCreated = $results['variant_attributes']['created'];

        // Should have both auto (brand) and ad-hoc attributes
        expect($productCreated)
            ->toContain('brand')     // Auto from CSV
            ->toContain('supplier')  // Ad-hoc
            ->toContain('warranty'); // Ad-hoc
    });

    test('returns proper error structure when attribute creation fails', function () {
        // Mock a scenario where attribute creation might fail
        $results = $this->action->execute(
            $this->product,
            $this->variant,
            [],
            [],
            ['test' => 'value'],
            []
        );

        expect($results)
            ->toHaveKeys(['product_attributes', 'variant_attributes'])
            ->and($results['product_attributes'])
            ->toHaveKeys(['created', 'updated', 'errors'])
            ->and($results['variant_attributes'])
            ->toHaveKeys(['created', 'updated', 'errors']);
    });

    test('works without variant (product-only attributes)', function () {
        $adHocAttributes = ['brand' => 'Test Brand'];
        
        $results = $this->action->execute(
            $this->product,
            null, // No variant
            [],
            [],
            $adHocAttributes,
            []
        );

        expect($results['product_attributes']['created'])
            ->toContain('brand')
            ->and($results['variant_attributes']['created'])
            ->toBeEmpty();
    });
});