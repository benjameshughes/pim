<?php

use App\Actions\Marketplace\Shopify\TransformToShopifyAction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->product = Product::factory()->create([
        'name' => 'Premium Roller Blind Collection',
        'parent_sku' => 'TEST-ROLLER-001',
        'description' => 'Premium quality roller blind for modern homes',
    ]);

    $this->whiteVariant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'sku' => 'TEST-ROLLER-001-WHITE-60x100',
        'color' => 'White',
        'width' => 60,
        'drop' => 100,
        'price' => 44.99,
    ]);

    $this->blackVariant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'sku' => 'TEST-ROLLER-001-BLACK-60x100',
        'color' => 'Black',
        'width' => 60,
        'drop' => 100,
        'price' => 44.99,
    ]);

    $this->syncAccount = SyncAccount::factory()->create([
        'name' => 'Test Shopify',
        'channel' => 'shopify',
        'display_name' => 'Test Shopify Store',
        'is_active' => true,
        'credentials' => json_encode([
            'shop_domain' => 'test-store.myshopify.com',
            'access_token' => 'test-token',
        ]),
        'settings' => json_encode([
            'auto_sync' => false,
            'sync_variants' => true,
        ]),
    ]);

    $this->action = new TransformToShopifyAction;
});

describe('TransformToShopifyAction', function () {

    it('can be instantiated', function () {
        expect($this->action)->toBeInstanceOf(TransformToShopifyAction::class);
    });

    it('transforms color groups to Shopify products', function () {
        $colorGroups = [
            'White' => [$this->whiteVariant],
            'Black' => [$this->blackVariant],
        ];

        $result = $this->action->execute($colorGroups, $this->product);

        expect($result)->toBeArray();
        expect($result)->toHaveCount(2);

        // Check first product (White)
        expect($result[0])->toHaveKey('productInput');
        expect($result[0])->toHaveKey('variants');
        expect($result[0])->toHaveKey('images');
        expect($result[0])->toHaveKey('_internal');

        expect($result[0]['productInput']['title'])->toBe('Premium Roller Blind Collection - White');
        expect($result[0]['_internal']['color_group'])->toBe('White');
        expect($result[0]['_internal']['original_product_id'])->toBe($this->product->id);
    });

    it('creates correct product input structure', function () {
        $colorGroups = ['White' => [$this->whiteVariant]];

        $result = $this->action->execute($colorGroups, $this->product);
        $productInput = $result[0]['productInput'];

        expect($productInput)->toHaveKey('title');
        expect($productInput)->toHaveKey('descriptionHtml');
        expect($productInput)->toHaveKey('vendor');
        expect($productInput)->toHaveKey('productType');
        expect($productInput)->toHaveKey('status');
        expect($productInput)->toHaveKey('metafields');
        expect($productInput)->toHaveKey('productOptions');

        expect($productInput['title'])->toBe('Premium Roller Blind Collection - White');
        expect($productInput['vendor'])->toBe('Blinds Outlet');
        expect($productInput['productType'])->toBe('Window Blinds');
        expect($productInput['status'])->toBe('ACTIVE');
    });

    it('creates correct variant structure', function () {
        $colorGroups = ['White' => [$this->whiteVariant]];

        $result = $this->action->execute($colorGroups, $this->product);
        $variants = $result[0]['variants'];

        expect($variants)->toBeArray();
        expect($variants)->toHaveCount(1);

        $variant = $variants[0];
        expect($variant)->toHaveKey('title');
        expect($variant)->toHaveKey('sku');
        expect($variant)->toHaveKey('price');
        expect($variant)->toHaveKey('options');
        expect($variant)->toHaveKey('inventoryQuantity');
        expect($variant)->toHaveKey('inventoryPolicy');
        expect($variant)->toHaveKey('inventoryManagement');
        expect($variant)->toHaveKey('requiresShipping');
        expect($variant)->toHaveKey('weight');
        expect($variant)->toHaveKey('weightUnit');

        expect($variant['sku'])->toBe('TEST-ROLLER-001-WHITE-60x100');
        expect($variant['price'])->toBe('44.99');
        expect($variant['options'])->toBe(['60cm', '100cm']);
        expect($variant['weightUnit'])->toBe('KILOGRAMS');
    });

    it('creates separate width and drop options', function () {
        // Create variants with different dimensions
        $variant1 = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'color' => 'White',
            'width' => 60,
            'drop' => 100,
        ]);
        $variant2 = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'color' => 'White',
            'width' => 80,
            'drop' => 120,
        ]);

        $colorGroups = ['White' => [$variant1, $variant2]];

        $result = $this->action->execute($colorGroups, $this->product);
        $productOptions = $result[0]['productInput']['productOptions'];

        expect($productOptions)->toHaveCount(2);
        expect($productOptions[0]['name'])->toBe('Width');
        expect($productOptions[1]['name'])->toBe('Drop');

        // Check width values
        $widthValues = array_column($productOptions[0]['values'], 'name');
        expect($widthValues)->toContain('60cm');
        expect($widthValues)->toContain('80cm');

        // Check drop values
        $dropValues = array_column($productOptions[1]['values'], 'name');
        expect($dropValues)->toContain('100cm');
        expect($dropValues)->toContain('120cm');
    });

    it('creates metafields for product', function () {
        $colorGroups = ['White' => [$this->whiteVariant]];

        $result = $this->action->execute($colorGroups, $this->product);
        $metafields = $result[0]['productInput']['metafields'];

        expect($metafields)->toBeArray();
        expect($metafields)->toHaveCount(2);

        // Check parent_sku metafield
        $parentSkuField = collect($metafields)->firstWhere('key', 'parent_sku');
        expect($parentSkuField)->not->toBeNull();
        expect($parentSkuField['namespace'])->toBe('custom');
        expect($parentSkuField['value'])->toBe('TEST-ROLLER-001');
        expect($parentSkuField['type'])->toBe('single_line_text_field');

        // Check color_group metafield
        $colorField = collect($metafields)->firstWhere('key', 'color_group');
        expect($colorField)->not->toBeNull();
        expect($colorField['value'])->toBe('White');
    });

    it('calculates variant weight correctly', function () {
        $colorGroups = ['White' => [$this->whiteVariant]];

        $result = $this->action->execute($colorGroups, $this->product);
        $variant = $result[0]['variants'][0];

        expect($variant['weight'])->toBeFloat();
        expect($variant['weight'])->toBeGreaterThan(0);

        // Should be base weight (0.5) + calculated weight based on dimensions
        $expectedWeight = 0.5 + ((60 * 100) * 0.0001);
        expect($variant['weight'])->toBe(round($expectedWeight, 2));
    });

    it('handles account-specific pricing', function () {
        // Set up channel-specific pricing
        $this->whiteVariant->setChannelPrice('shopify_main', 49.99);

        $colorGroups = ['White' => [$this->whiteVariant]];

        $result = $this->action->execute($colorGroups, $this->product, $this->syncAccount);
        $variant = $result[0]['variants'][0];

        expect($variant['price'])->toBe('49.99');
    });

    it('creates comprehensive variant metafields', function () {
        $colorGroups = ['White' => [$this->whiteVariant]];

        $result = $this->action->execute($colorGroups, $this->product);
        $variant = $result[0]['variants'][0];
        $metafields = $variant['metafields'];

        expect($metafields)->toBeArray();

        // Check for dimension metafields
        $widthField = collect($metafields)->firstWhere('key', 'width_cm');
        expect($widthField)->not->toBeNull();
        expect($widthField['value'])->toBe('60');

        $dropField = collect($metafields)->firstWhere('key', 'drop_cm');
        expect($dropField)->not->toBeNull();
        expect($dropField['value'])->toBe('100');

        // Check status field
        $statusField = collect($metafields)->firstWhere('key', 'status');
        expect($statusField)->not->toBeNull();
        expect($statusField['type'])->toBe('single_line_text_field');
    });

    it('detects product categories but disables them', function () {
        $colorGroups = ['White' => [$this->whiteVariant]];

        $result = $this->action->execute($colorGroups, $this->product);
        $productInput = $result[0]['productInput'];

        // Category should not be set due to isValidTaxonomyId returning false
        expect($productInput)->not->toHaveKey('category');
    });

    it('handles empty variant lists gracefully', function () {
        $colorGroups = ['White' => []];

        $result = $this->action->execute($colorGroups, $this->product);

        expect($result)->toBeArray();
        expect($result)->toHaveCount(1);
        expect($result[0]['variants'])->toBeEmpty();
        expect($result[0]['productInput']['productOptions'])->toBeEmpty();
    });
});
