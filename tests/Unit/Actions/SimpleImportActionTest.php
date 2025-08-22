<?php

use App\Actions\Import\SimpleImportAction;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Simple Import Action', function () {
    beforeEach(function () {
        $this->action = new SimpleImportAction();
    });

    it('can import products from array data', function () {
        $data = [
            [
                'name' => 'Action Product 1',
                'description' => 'First product via action',
                'sku' => 'ACT-001',
                'price' => 29.99,
            ],
            [
                'name' => 'Action Product 2',
                'description' => 'Second product via action',
                'sku' => 'ACT-002',
                'price' => 39.99,
            ],
        ];

        $result = $this->action->execute($data);

        expect($result['successful'])->toBe(2);
        expect($result['errors'])->toBe(0);
        expect(Product::count())->toBe(2);
    });

    it('validates required fields', function () {
        $data = [
            [
                'name' => 'Valid Product',
                'sku' => 'VALID-001',
            ],
            [
                'description' => 'Invalid - no name or SKU',
                'price' => 29.99,
            ],
        ];

        $result = $this->action->execute($data);

        expect($result['successful'])->toBe(1);
        expect($result['errors'])->toBe(1);
        expect(Product::count())->toBe(1);
    });

    it('handles update mode for existing products', function () {
        $existingProduct = Product::factory()->create([
            'sku' => 'EXISTING-001',
            'name' => 'Original Name',
        ]);

        $data = [
            [
                'name' => 'Updated Name',
                'sku' => 'EXISTING-001',
                'description' => 'Updated description',
            ],
        ];

        $result = $this->action->execute($data, ['mode' => 'update']);

        expect($result['successful'])->toBe(1);
        expect($result['updated'])->toBe(1);
        expect(Product::count())->toBe(1);
        
        $existingProduct->refresh();
        expect($existingProduct->name)->toBe('Updated Name');
    });

    it('handles skip mode for existing products', function () {
        Product::factory()->create(['sku' => 'EXISTING-001']);

        $data = [
            [
                'name' => 'Duplicate Product',
                'sku' => 'EXISTING-001',
            ],
            [
                'name' => 'New Product',
                'sku' => 'NEW-001',
            ],
        ];

        $result = $this->action->execute($data, ['mode' => 'skip']);

        expect($result['successful'])->toBe(1);
        expect($result['skipped'])->toBe(1);
        expect(Product::count())->toBe(2);
    });

    it('can transform data during import', function () {
        $data = [
            [
                'product_name' => 'Transform Test',
                'product_sku' => 'TRANS-001',
                'selling_price' => '29.99',
            ],
        ];

        $transformer = function ($row) {
            return [
                'name' => $row['product_name'],
                'sku' => $row['product_sku'],
                'price' => floatval($row['selling_price']),
            ];
        };

        $result = $this->action->execute($data, ['transformer' => $transformer]);

        expect($result['successful'])->toBe(1);
        $product = Product::first();
        expect($product->name)->toBe('Transform Test');
        expect($product->sku)->toBe('TRANS-001');
    });

    it('returns detailed import statistics', function () {
        Product::factory()->create(['sku' => 'EXISTING']);

        $data = [
            ['name' => 'New Product', 'sku' => 'NEW-001'],
            ['name' => 'Existing Product', 'sku' => 'EXISTING'],
            ['invalid' => 'data'],
        ];

        $result = $this->action->execute($data, ['mode' => 'skip']);

        expect($result)->toHaveKeys([
            'total', 'successful', 'errors', 'skipped', 'created', 'updated'
        ]);
        expect($result['total'])->toBe(3);
        expect($result['successful'])->toBe(1);
        expect($result['errors'])->toBe(1);
        expect($result['skipped'])->toBe(1);
    });
});