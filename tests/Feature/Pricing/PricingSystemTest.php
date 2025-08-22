<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Pricing;
use App\Models\User;
use App\Services\Pricing\PriceCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Pricing System', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can create pricing for product variant', function () {
        $variant = ProductVariant::factory()->create();

        $component = Livewire::test('pricing.pricing-form', ['variant' => $variant])
            ->set('pricing', [
                'cost_price' => 10.00,
                'wholesale_price' => 15.00,
                'retail_price' => 25.00,
                'sale_price' => 20.00,
            ])
            ->call('save');

        expect(Pricing::count())->toBe(1);
        $pricing = Pricing::first();
        expect($pricing->cost_price)->toBe(10.00);
        expect($pricing->retail_price)->toBe(25.00);
    });

    it('can calculate profit margins', function () {
        $pricing = Pricing::factory()->create([
            'cost_price' => 10.00,
            'retail_price' => 25.00,
        ]);

        $calculator = new PriceCalculatorService();
        $analysis = $calculator->calculateProfitAnalysis($pricing);

        expect($analysis->grossProfit)->toBe(15.00);
        expect($analysis->marginPercentage)->toBe(60.0); // (25-10)/25 * 100
    });

    it('validates pricing relationships', function () {
        $variant = ProductVariant::factory()->create();

        $component = Livewire::test('pricing.pricing-form', ['variant' => $variant])
            ->set('pricing', [
                'cost_price' => 25.00,
                'wholesale_price' => 20.00, // Less than cost
                'retail_price' => 15.00,    // Less than wholesale
            ])
            ->call('save')
            ->assertHasErrors(['pricing.wholesale_price', 'pricing.retail_price']);
    });

    it('can apply bulk pricing updates', function () {
        $product = Product::factory()->create();
        $variants = ProductVariant::factory()->count(3)->create(['product_id' => $product->id]);
        
        foreach ($variants as $variant) {
            Pricing::factory()->create([
                'product_variant_id' => $variant->id,
                'retail_price' => 20.00,
            ]);
        }

        $component = Livewire::test('bulk-operations.bulk-pricing-operation')
            ->set('selectedVariants', $variants->pluck('id')->toArray())
            ->set('updateType', 'percentage')
            ->set('percentage', 10) // 10% increase
            ->call('applyPricingUpdate');

        $updatedPricing = Pricing::whereIn('product_variant_id', $variants->pluck('id'))->get();
        expect($updatedPricing->avg('retail_price'))->toBe(22.00);
    });

    it('can set competitive pricing', function () {
        $variant = ProductVariant::factory()->create();
        $pricing = Pricing::factory()->create(['product_variant_id' => $variant->id]);

        $competitorPrices = [
            'amazon' => 23.99,
            'ebay' => 24.50,
            'shopify' => 22.99,
        ];

        $calculator = new PriceCalculatorService();
        $suggestedPrice = $calculator->calculateCompetitivePrice($competitorPrices, 'undercut');

        expect($suggestedPrice)->toBe(22.89); // 10 cents below lowest competitor
    });

    it('handles currency conversions', function () {
        $variant = ProductVariant::factory()->create();
        
        $component = Livewire::test('pricing.pricing-form', ['variant' => $variant])
            ->set('pricing.retail_price', 25.00)
            ->set('currency', 'USD')
            ->call('convertCurrency', 'EUR');

        // Assuming 1 USD = 0.85 EUR exchange rate
        $component->assertSet('pricing.retail_price', 21.25);
    });

    it('can manage price tiers', function () {
        $variant = ProductVariant::factory()->create();

        $tiers = [
            ['quantity' => 1, 'price' => 25.00],
            ['quantity' => 10, 'price' => 22.50],
            ['quantity' => 100, 'price' => 20.00],
        ];

        $component = Livewire::test('pricing.pricing-form', ['variant' => $variant])
            ->set('priceTiers', $tiers)
            ->call('saveTierPricing');

        $pricing = Pricing::where('product_variant_id', $variant->id)->first();
        expect($pricing->price_tiers)->toBe($tiers);
    });

    it('can track pricing history', function () {
        $variant = ProductVariant::factory()->create();
        $pricing = Pricing::factory()->create([
            'product_variant_id' => $variant->id,
            'retail_price' => 20.00,
        ]);

        // Update price
        $component = Livewire::test('pricing.pricing-form', ['variant' => $variant])
            ->set('pricing.retail_price', 25.00)
            ->call('save');

        expect($pricing->fresh()->retail_price)->toBe(25.00);
        expect($pricing->fresh()->price_history)->toContain('20.00');
    });

    it('can generate pricing reports', function () {
        $variants = ProductVariant::factory()->count(5)->create();
        
        foreach ($variants as $i => $variant) {
            Pricing::factory()->create([
                'product_variant_id' => $variant->id,
                'cost_price' => 10.00,
                'retail_price' => 20.00 + ($i * 5), // Varying prices
            ]);
        }

        $component = Livewire::test('pricing.pricing-dashboard');

        $report = $component->get('pricingReport');
        expect($report['averageMargin'])->toBeGreaterThan(0);
        expect($report['totalProducts'])->toBe(5);
    });
});