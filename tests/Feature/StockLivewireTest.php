<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->product = Product::factory()->create();
    $this->variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
    $this->stock = Stock::factory()->create([
        'product_variant_id' => $this->variant->id,
        'quantity' => 50,
        'reserved' => 10,
        'minimum_level' => 5,
        'status' => 'available',
        'track_stock' => true,
    ]);
});

describe('Stock Service Integration', function () {

    test('stock service creates stock record', function () {
        $newVariant = ProductVariant::factory()->create(['product_id' => $this->product->id]);

        $stockService = app(StockService::class);
        $stock = $stockService->createStock([
            'product_variant_id' => $newVariant->id,
            'quantity' => 100,
            'minimum_level' => 10,
            'status' => 'available',
        ]);

        expect($stock)->toBeInstanceOf(Stock::class);
        expect($stock->quantity)->toBe(100);
        expect($stock->product_variant_id)->toBe($newVariant->id);
    });

    test('stock service gets stock for variant', function () {
        $stockService = app(StockService::class);
        $stock = $stockService->getStockForVariant($this->variant->id);

        expect($stock)->toBeInstanceOf(Stock::class);
        expect($stock->id)->toBe($this->stock->id);
    });

    test('stock service calculates available quantity', function () {
        $stockService = app(StockService::class);
        $availableStock = $stockService->getAvailableStockForVariant($this->variant->id);

        expect($availableStock)->toBe(40); // 50 - 10 reserved
    });

    test('stock service adjusts stock levels', function () {
        $stockService = app(StockService::class);
        $stock = $stockService->adjustStock($this->variant->id, 25, 'Restocked');

        expect($stock->quantity)->toBe(75); // 50 + 25
        expect($stock->notes)->toContain('Restocked');
    });

    test('stock service reserves stock', function () {
        $stockService = app(StockService::class);
        $result = $stockService->reserveStock($this->variant->id, 20);

        expect($result)->toBeTrue();
        $this->stock->refresh();
        expect($this->stock->reserved)->toBe(30); // 10 + 20
    });

});

describe('Stock Model Accessors', function () {

    test('variant stock accessor returns stock record', function () {
        $stock = $this->variant->stock;

        expect($stock)->toBeInstanceOf(Stock::class);
        expect($stock->id)->toBe($this->stock->id);
    });

    test('variant stock level accessor returns quantity', function () {
        $stockLevel = $this->variant->stock_level;

        expect($stockLevel)->toBe(50);
    });

    test('variant in stock method works via service', function () {
        expect($this->variant->inStock())->toBeTrue();

        // Set stock to zero
        $this->stock->update(['quantity' => 0]);
        expect($this->variant->inStock())->toBeFalse();
    });

    test('variant handles missing stock gracefully', function () {
        $newVariant = ProductVariant::factory()->create(['product_id' => $this->product->id]);

        expect($newVariant->stock)->toBeNull();
        expect($newVariant->stock_level)->toBe(0);
        expect($newVariant->inStock())->toBeFalse();
    });

});

describe('Stock Model Operations', function () {

    test('stock model adjusts quantity with reason', function () {
        $originalQuantity = $this->stock->quantity;
        $this->stock->adjustStock(20, 'Manual adjustment');

        expect($this->stock->quantity)->toBe($originalQuantity + 20);
        expect($this->stock->notes)->toContain('Manual adjustment');
    });

    test('stock model sets specific quantity', function () {
        $this->stock->setStock(100, 'Inventory count');

        expect($this->stock->quantity)->toBe(100);
        expect($this->stock->notes)->toContain('Inventory count');
    });

    test('stock model calculates available quantity', function () {
        $available = $this->stock->getAvailableQuantity();

        expect($available)->toBe(40); // 50 - 10 reserved
    });

    test('stock model detects low stock', function () {
        $this->stock->update(['quantity' => 3, 'minimum_level' => 5]);

        expect($this->stock->isLowStock())->toBeTrue();
    });

    test('stock model reserves and releases stock', function () {
        $result = $this->stock->reserveStock(15);
        expect($result)->toBeTrue();
        expect($this->stock->reserved)->toBe(25); // 10 + 15

        $this->stock->releaseReserved(5);
        expect($this->stock->reserved)->toBe(20); // 25 - 5
    });

});

describe('Stock Scopes and Queries', function () {

    test('stock available scope works', function () {
        $newVariant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
        Stock::factory()->create([
            'product_variant_id' => $newVariant->id,
            'status' => 'damaged',
        ]);

        $availableStock = Stock::available()->get();
        expect($availableStock)->toHaveCount(1);
        expect($availableStock->first()->status)->toBe('available');
    });

    test('stock low stock scope works', function () {
        $this->stock->update(['quantity' => 3, 'minimum_level' => 5]);

        $lowStock = Stock::lowStock()->get();
        expect($lowStock)->toHaveCount(1);
        expect($lowStock->first()->id)->toBe($this->stock->id);
    });

    test('stock tracked scope works', function () {
        // Create untracked stock
        Stock::factory()->create([
            'product_variant_id' => $this->variant->id,
            'track_stock' => false,
        ]);

        // Should only return the original tracked stock (this->stock has track_stock = true by default)
        $trackedStock = Stock::tracked()->get();
        expect($trackedStock)->toHaveCount(1);
        expect($trackedStock->first()->track_stock)->toBeTrue();
        expect($trackedStock->first()->id)->toBe($this->stock->id);
    });

});

describe('Stock Statistics', function () {

    test('stock service calculates variant stats', function () {
        // Create another stock record for same variant (different location)
        Stock::factory()->create([
            'product_variant_id' => $this->variant->id,
            'quantity' => 30,
            'reserved' => 5,
            'location' => 'warehouse_c', // Use different location to avoid unique constraint
        ]);

        $stockService = app(StockService::class);
        $stats = $stockService->getVariantStockStats($this->variant->id);

        expect($stats['total_quantity'])->toBe(80); // 50 + 30
        expect($stats['total_reserved'])->toBe(15); // 10 + 5
        expect($stats['location_count'])->toBe(2);
    });

    test('stock service handles variants with no stock', function () {
        $newVariant = ProductVariant::factory()->create(['product_id' => $this->product->id]);

        $stockService = app(StockService::class);
        $stats = $stockService->getVariantStockStats($newVariant->id);

        expect($stats['total_quantity'])->toBe(0);
        expect($stats['location_count'])->toBe(0);
    });

});
