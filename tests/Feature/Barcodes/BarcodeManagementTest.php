<?php

use App\Models\Barcode;
use App\Models\BarcodePool;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\BarcodeAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Barcode Management', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can create barcode pool', function () {
        $component = Livewire::test('barcodes.barcode-pool-dashboard')
            ->set('poolName', 'Test Pool')
            ->set('startRange', '1000000000000')
            ->set('endRange', '1000000001000')
            ->call('createPool');

        expect(BarcodePool::count())->toBe(1);
        $pool = BarcodePool::first();
        expect($pool->name)->toBe('Test Pool');
        expect($pool->start_range)->toBe('1000000000000');
    });

    it('generates barcodes from pool', function () {
        $pool = BarcodePool::factory()->create([
            'start_range' => '1000000000000',
            'end_range' => '1000000000010',
        ]);

        $component = Livewire::test('barcodes.barcode-pool-dashboard')
            ->call('generateBarcodes', $pool->id, 5);

        expect(Barcode::where('barcode_pool_id', $pool->id)->count())->toBe(5);
    });

    it('validates barcode range limits', function () {
        $pool = BarcodePool::factory()->create([
            'start_range' => '1000000000000',
            'end_range' => '1000000000002',
        ]);

        $component = Livewire::test('barcodes.barcode-pool-dashboard')
            ->call('generateBarcodes', $pool->id, 5)
            ->assertHasErrors(['quantity']);
    });

    it('can assign barcode to product variant', function () {
        $variant = ProductVariant::factory()->create();
        $barcode = Barcode::factory()->create(['status' => 'available']);

        $service = new BarcodeAssignmentService();
        $result = $service->assignToVariant($variant, $barcode);

        expect($result['success'])->toBeTrue();
        $barcode->refresh();
        expect($barcode->status)->toBe('assigned');
        expect($barcode->product_variant_id)->toBe($variant->id);
    });

    it('cannot assign already assigned barcode', function () {
        $variant1 = ProductVariant::factory()->create();
        $variant2 = ProductVariant::factory()->create();
        $barcode = Barcode::factory()->create(['status' => 'assigned', 'product_variant_id' => $variant1->id]);

        $service = new BarcodeAssignmentService();
        $result = $service->assignToVariant($variant2, $barcode);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('already assigned');
    });

    it('can release barcode from variant', function () {
        $variant = ProductVariant::factory()->create();
        $barcode = Barcode::factory()->create(['status' => 'assigned', 'product_variant_id' => $variant->id]);

        $service = new BarcodeAssignmentService();
        $result = $service->releaseFromVariant($variant);

        expect($result['success'])->toBeTrue();
        $barcode->refresh();
        expect($barcode->status)->toBe('available');
        expect($barcode->product_variant_id)->toBeNull();
    });

    it('can search available barcodes', function () {
        Barcode::factory()->create(['code' => '1234567890123', 'status' => 'available']);
        Barcode::factory()->create(['code' => '1234567890124', 'status' => 'assigned']);
        Barcode::factory()->create(['code' => '9876543210123', 'status' => 'available']);

        $component = Livewire::test('barcodes.barcode-index')
            ->set('search', '123456789012')
            ->set('statusFilter', 'available')
            ->call('render');

        expect($component->get('barcodes'))->toHaveCount(1);
    });

    it('validates barcode format', function () {
        $component = Livewire::test('barcodes.barcode-form')
            ->set('barcode.code', '123') // Too short
            ->call('save')
            ->assertHasErrors(['barcode.code']);
    });

    it('can bulk import barcodes', function () {
        $pool = BarcodePool::factory()->create();
        
        $barcodes = [
            '1000000000001',
            '1000000000002',
            '1000000000003',
        ];

        $component = Livewire::test('barcodes.barcode-pool-dashboard')
            ->set('importCodes', implode("\n", $barcodes))
            ->set('selectedPoolId', $pool->id)
            ->call('bulkImport');

        expect(Barcode::count())->toBe(3);
        expect(Barcode::where('barcode_pool_id', $pool->id)->count())->toBe(3);
    });

    it('tracks barcode usage statistics', function () {
        $pool = BarcodePool::factory()->create();
        Barcode::factory()->count(10)->create(['barcode_pool_id' => $pool->id, 'status' => 'available']);
        Barcode::factory()->count(5)->create(['barcode_pool_id' => $pool->id, 'status' => 'assigned']);

        $component = Livewire::test('barcodes.barcode-pool-dashboard');

        $stats = $component->get('poolStats')[$pool->id];
        expect($stats['total'])->toBe(15);
        expect($stats['available'])->toBe(10);
        expect($stats['assigned'])->toBe(5);
    });
});