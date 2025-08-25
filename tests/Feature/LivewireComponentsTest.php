<?php

use App\Livewire\Dashboard;
use App\Livewire\LogDashboard;
use App\Livewire\Marketplace\IdentifiersDashboard;
use App\Livewire\Pricing\PricingDashboard;
use App\Livewire\Products\ProductShow;
use App\Livewire\Shopify\ShopifyDashboard;
use App\Models\Image;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create test data for components that need it
    $this->product = Product::factory()->create();
    $this->variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
    $this->salesChannel = SalesChannel::factory()->create();
    $this->pricing = Pricing::factory()->create([
        'product_variant_id' => $this->variant->id,
        'sales_channel_id' => $this->salesChannel->id,
    ]);
    $this->image = Image::factory()->create();
});

describe('Core Livewire Components', function () {

    test('Dashboard component renders', function () {
        Livewire::test(Dashboard::class)
            ->assertStatus(200)
            ->assertSee('Dashboard Under Maintenance');
    });

    test('ProductShow component renders', function () {
        Livewire::test(ProductShow::class, ['product' => $this->product])
            ->assertStatus(200)
            ->assertSee($this->product->name);
    });

    test('ShopifyDashboard component renders', function () {
        Livewire::test(ShopifyDashboard::class)
            ->assertStatus(200);
    });

    test('PricingDashboard component renders', function () {
        Livewire::test(PricingDashboard::class)
            ->assertStatus(200);
    });

    test('IdentifiersDashboard component renders', function () {
        Livewire::test(IdentifiersDashboard::class)
            ->assertStatus(200);
    });

    test('LogDashboard component renders', function () {
        Livewire::test(LogDashboard::class)
            ->assertStatus(200);
    });

});

describe('Livewire Component Interactions', function () {

    test('Dashboard component has no PHP errors', function () {
        expect(function () {
            Livewire::test(Dashboard::class);
        })->not->toThrow(Exception::class);
    });

    test('ProductShow component handles product data', function () {
        $component = Livewire::test(ProductShow::class, ['product' => $this->product]);

        expect($component->get('product'))->toBeInstanceOf(Product::class);
        expect($component->get('product')->id)->toBe($this->product->id);
    });

});
