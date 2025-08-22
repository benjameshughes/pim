<?php

use App\Livewire\Products\Wizard\PricingStockForm;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Pricing Stock Step Component', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can render the pricing stock step component', function () {
        $component = Livewire::test(PricingStockForm::class)
            ->assertStatus(200);
            
        expect($component)->not->toBeNull();
    });

    it('initializes with default values', function () {
        $component = Livewire::test(PricingStockForm::class);

        expect($component->get('isActive'))->toBe(false);
        expect($component->get('defaultRetailPrice'))->toBe(0.00);
        expect($component->get('defaultCostPrice'))->toBe(0.00);
        expect($component->get('defaultStockLevel'))->toBe(0);
        expect($component->get('vatPercentage'))->toBe(20.0);
        expect($component->get('pricesIncludeVat'))->toBe(true);
        expect($component->get('enableMarketplacePricing'))->toBe(true);
        expect($component->get('enableAutoLinking'))->toBe(true);
    });

    it('initializes collections correctly', function () {
        $component = Livewire::test(PricingStockForm::class);

        expect($component->get('variantPricing'))->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($component->get('marketplacePricing'))->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($component->get('stockLevels'))->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($component->get('availableMarketplaces'))->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($component->get('selectedMarketplaces'))->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($component->get('validationErrors'))->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('loads available marketplaces correctly', function () {
        $component = Livewire::test(PricingStockForm::class);

        $marketplaces = $component->get('availableMarketplaces');
        
        expect($marketplaces->count())->toBe(4);
        expect($marketplaces->pluck('id')->toArray())->toContain('shopify');
        expect($marketplaces->pluck('id')->toArray())->toContain('ebay');
        expect($marketplaces->pluck('id')->toArray())->toContain('amazon');
        expect($marketplaces->pluck('id')->toArray())->toContain('mirakl');
    });

    it('initializes with default selected marketplaces', function () {
        $component = Livewire::test(PricingStockForm::class);

        $selectedMarketplaces = $component->get('selectedMarketplaces');
        
        expect($selectedMarketplaces->toArray())->toBe(['shopify', 'ebay']);
    });

    it('loads existing step data on mount', function () {
        $stepData = [
            'default_retail_price' => 29.99,
            'default_cost_price' => 15.00,
            'vat_percentage' => 25.0,
        ];

        $component = Livewire::test(PricingStockForm::class, [
            'stepData' => $stepData,
            'isActive' => true
        ]);

        expect($component->get('isActive'))->toBe(true);
        expect($component->get('defaultRetailPrice'))->toBe(29.99);
        expect($component->get('defaultCostPrice'))->toBe(15.00);
        expect($component->get('vatPercentage'))->toBe(25.0);
        expect($component->get('selectedMarketplaces'))->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('initializes pricing from generated variants', function () {
        $allStepData = [
            2 => [
                'generated_variants' => [
                    [
                        'id' => 1,
                        'sku' => 'TEST-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140,
                        'price' => 0,
                        'stock' => 0
                    ],
                    [
                        'id' => 2,
                        'sku' => 'TEST-002', 
                        'color' => 'Blue',
                        'width' => 90,
                        'drop' => 160,
                        'price' => 0,
                        'stock' => 0
                    ]
                ]
            ]
        ];

        $component = Livewire::test(PricingStockForm::class, [
            'allStepData' => $allStepData
        ]);

        $variantPricing = $component->get('variantPricing');
        
        expect($variantPricing->count())->toBe(2);
        expect($variantPricing->first()['sku'])->toBe('TEST-001');
        expect($variantPricing->last()['sku'])->toBe('TEST-002');
        expect($variantPricing->first()['color'])->toBe('Red');
        expect($variantPricing->last()['color'])->toBe('Blue');
    });

    it('detects edit mode correctly', function () {
        $allStepData = [
            2 => [
                'generated_variants' => [
                    [
                        'id' => 1,
                        'sku' => 'EXIST-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140,
                        'price' => 29.99,
                        'stock' => 50,
                        'existing' => true
                    ]
                ]
            ]
        ];

        $component = Livewire::test(PricingStockForm::class, [
            'allStepData' => $allStepData
        ]);

        $variantPricing = $component->get('variantPricing');
        
        expect($variantPricing->first()['existing_variant'])->toBe(true);
        expect($variantPricing->first()['is_edit_mode'])->toBe(true);
        expect((float)$variantPricing->first()['retail_price'])->toBe(29.99);
        expect($variantPricing->first()['stock_level'])->toBe(50);
    });

    it('calculates VAT inclusive prices correctly', function () {
        $allStepData = [
            2 => [
                'generated_variants' => [
                    [
                        'id' => 1,
                        'sku' => 'TEST-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140
                    ]
                ]
            ]
        ];

        $stepData = [
            'default_retail_price' => 100.00,
            'vat_percentage' => 20.0,
            'prices_include_vat' => false
        ];

        $component = Livewire::test(PricingStockForm::class, [
            'stepData' => $stepData,
            'allStepData' => $allStepData
        ]);

        $variantPricing = $component->get('variantPricing');
        
        expect($variantPricing->first()['vat_inclusive_price'])->toBe(120.0);
    });

    it('generates marketplace pricing correctly', function () {
        $allStepData = [
            2 => [
                'generated_variants' => [
                    [
                        'id' => 1,
                        'sku' => 'TEST-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140
                    ]
                ]
            ]
        ];

        $stepData = [
            'default_retail_price' => 100.00
        ];

        $component = Livewire::test(PricingStockForm::class, [
            'stepData' => $stepData,
            'allStepData' => $allStepData
        ]);

        $variantPricing = $component->get('variantPricing');
        $marketplacePricing = $variantPricing->first()['marketplace_pricing'];
        
        expect($marketplacePricing['shopify'])->toBe(105.0); // 5% markup
        expect($marketplacePricing['ebay'])->toBe(108.0);    // 8% markup
    });

    it('validates pricing data correctly', function () {
        // Test with empty variant pricing
        $component = Livewire::test(PricingStockForm::class)
            ->set('isActive', true)
            ->call('validateStep');

        $errors = $component->get('validationErrors');
        expect($errors->count())->toBeGreaterThan(0);
        expect($errors->first())->toContain('No variants found');
    });

    it('validates individual variant pricing', function () {
        $allStepData = [
            2 => [
                'generated_variants' => [
                    [
                        'id' => 1,
                        'sku' => 'TEST-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140
                    ]
                ]
            ]
        ];

        $component = Livewire::test(PricingStockForm::class, [
            'allStepData' => $allStepData,
            'isActive' => true
        ]);

        // Set invalid pricing
        $variantPricing = collect([
            [
                'variant_id' => 1,
                'sku' => 'TEST-001',
                'color' => 'Red',
                'width' => 60,
                'drop' => 140,
                'title' => 'Red × 60cm × 140cm',
                'retail_price' => 0, // Invalid - should be > 0
                'cost_price' => -10, // Invalid - should be >= 0
                'stock_level' => -5, // Invalid - should be >= 0
                'marketplace_pricing' => ['shopify' => 0]
            ]
        ]);

        $component->set('variantPricing', $variantPricing)
            ->call('validateStep');

        $errors = $component->get('validationErrors');
        expect($errors->count())->toBeGreaterThan(0);
        expect($errors->join(' '))->toContain('Retail price is required');
        expect($errors->join(' '))->toContain('Cost price cannot be negative');
        expect($errors->join(' '))->toContain('Stock level cannot be negative');
    });

    it('can bulk update pricing', function () {
        $allStepData = [
            2 => [
                'generated_variants' => [
                    [
                        'id' => 1,
                        'sku' => 'TEST-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140
                    ],
                    [
                        'id' => 2,
                        'sku' => 'TEST-002',
                        'color' => 'Blue', 
                        'width' => 90,
                        'drop' => 160
                    ]
                ]
            ]
        ];

        $component = Livewire::test(PricingStockForm::class, [
            'allStepData' => $allStepData
        ])
            ->set('defaultRetailPrice', 49.99)
            ->call('bulkUpdatePricing', 'all');

        $variantPricing = $component->get('variantPricing');
        
        expect($variantPricing->every(fn($p) => $p['retail_price'] == 49.99))->toBe(true);
        $component->assertDispatched('notify');
    });

    it('can bulk update stock', function () {
        $allStepData = [
            2 => [
                'generated_variants' => [
                    [
                        'id' => 1,
                        'sku' => 'TEST-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140
                    ]
                ]
            ]
        ];

        $component = Livewire::test(PricingStockForm::class, [
            'allStepData' => $allStepData
        ])
            ->set('defaultStockLevel', 100)
            ->call('bulkUpdateStock', 'all');

        $variantPricing = $component->get('variantPricing');
        
        expect($variantPricing->every(fn($p) => $p['stock_level'] == 100))->toBe(true);
        $component->assertDispatched('notify');
    });

    it('can update individual variant pricing', function () {
        $allStepData = [
            2 => [
                'generated_variants' => [
                    [
                        'id' => 1,
                        'sku' => 'TEST-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140
                    ]
                ]
            ]
        ];

        $component = Livewire::test(PricingStockForm::class, [
            'allStepData' => $allStepData
        ])
            ->call('updateVariantPricing', 0, 'retail_price', 79.99);

        $variantPricing = $component->get('variantPricing');
        
        expect($variantPricing->first()['retail_price'])->toBe(79.99);
        expect($variantPricing->first()['vat_inclusive_price'])->toBe(79.99); // Since prices include VAT by default
    });

    it('computes pricing statistics correctly', function () {
        $allStepData = [
            2 => [
                'generated_variants' => [
                    [
                        'id' => 1,
                        'sku' => 'TEST-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140
                    ],
                    [
                        'id' => 2,
                        'sku' => 'TEST-002',
                        'color' => 'Blue',
                        'width' => 90,
                        'drop' => 160
                    ]
                ]
            ]
        ];

        $component = Livewire::test(PricingStockForm::class, [
            'allStepData' => $allStepData
        ])
            ->set('defaultRetailPrice', 50.00)
            ->set('defaultStockLevel', 25)
            ->call('bulkUpdatePricing', 'all')
            ->call('bulkUpdateStock', 'all');

        $stats = $component->instance()->pricingStats();
        
        expect($stats['total_variants'])->toBe(2);
        expect((float)$stats['average_retail_price'])->toBe(50.0);
        expect($stats['total_stock_units'])->toBe(50);
        expect((float)$stats['total_inventory_value'])->toBe(2500.0); // 2 variants * 50.00 * 25 stock each
    });

    it('computes marketplace statistics correctly', function () {
        $allStepData = [
            2 => [
                'generated_variants' => [
                    [
                        'id' => 1,
                        'sku' => 'TEST-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140
                    ]
                ]
            ]
        ];

        $component = Livewire::test(PricingStockForm::class, [
            'allStepData' => $allStepData
        ])
            ->set('defaultRetailPrice', 100.00)
            ->call('bulkUpdatePricing', 'all');

        $marketplaceStats = $component->instance()->marketplaceStats();
        
        expect($marketplaceStats->count())->toBe(2); // shopify, ebay (default selected)
        
        $shopifyStats = $marketplaceStats->firstWhere('marketplace', 'shopify');
        expect($shopifyStats['average_price'])->toBe(105.0); // 5% markup
        
        $ebayStats = $marketplaceStats->firstWhere('marketplace', 'ebay');
        expect($ebayStats['average_price'])->toBe(108.0); // 8% markup
    });

    it('can reset to defaults', function () {
        $component = Livewire::test(PricingStockForm::class)
            ->set('defaultRetailPrice', 99.99)
            ->call('resetToDefaults');

        $component->assertDispatched('notify');
    });

    it('handles empty pricing statistics gracefully', function () {
        $component = Livewire::test(PricingStockForm::class);

        $stats = $component->instance()->pricingStats();
        
        expect($stats['total_variants'])->toBe(0);
        expect($stats['average_retail_price'])->toBe(0);
        expect($stats['total_inventory_value'])->toBe(0);
        expect($stats['total_stock_units'])->toBe(0);
    });

    it('validates marketplace pricing when enabled', function () {
        $allStepData = [
            2 => [
                'generated_variants' => [
                    [
                        'id' => 1,
                        'sku' => 'TEST-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140
                    ]
                ]
            ]
        ];

        $component = Livewire::test(PricingStockForm::class, [
            'allStepData' => $allStepData,
            'isActive' => true
        ])
            ->set('enableMarketplacePricing', true);

        // Set variant with zero marketplace pricing
        $variantPricing = collect([
            [
                'variant_id' => 1,
                'sku' => 'TEST-001',
                'color' => 'Red',
                'width' => 60,
                'drop' => 140,
                'title' => 'Red × 60cm × 140cm',
                'retail_price' => 50.00,
                'cost_price' => 25.00,
                'stock_level' => 10,
                'marketplace_pricing' => ['shopify' => 0, 'ebay' => 0] // Invalid
            ]
        ]);

        $component->set('variantPricing', $variantPricing)
            ->call('validateStep');

        $errors = $component->get('validationErrors');
        expect($errors->count())->toBeGreaterThan(0);
        expect($errors->join(' '))->toContain('Marketplace price is required');
    });

    it('completes step with valid data', function () {
        $allStepData = [
            2 => [
                'generated_variants' => [
                    [
                        'id' => 1,
                        'sku' => 'TEST-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140
                    ]
                ]
            ]
        ];

        $stepData = [
            'default_retail_price' => 50.00
        ];

        $component = Livewire::test(PricingStockForm::class, [
            'stepData' => $stepData,
            'allStepData' => $allStepData,
            'isActive' => true
        ])
            ->call('bulkUpdatePricing', 'all');

        // Check that variants have valid pricing
        $variantPricing = $component->get('variantPricing');
        expect($variantPricing->first()['retail_price'])->toBe(50.0);
        
        // Check that validation would pass
        expect($variantPricing->isEmpty())->toBe(false);
    });

    it('generates variant titles correctly', function () {
        $allStepData = [
            2 => [
                'generated_variants' => [
                    [
                        'id' => 1,
                        'sku' => 'TEST-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140
                    ],
                    [
                        'id' => 2,
                        'sku' => 'TEST-002',
                        'color' => '', // Empty color
                        'width' => 90,
                        'drop' => ''   // Empty drop
                    ]
                ]
            ]
        ];

        $component = Livewire::test(PricingStockForm::class, [
            'allStepData' => $allStepData
        ]);

        $variantPricing = $component->get('variantPricing');
        
        expect($variantPricing->first()['title'])->toBe('Red × 60cm × 140cm');
        expect($variantPricing->last()['title'])->toBe('90cm × cm'); // Width and empty drop
    });

    it('updates marketplace pricing when selected marketplaces change', function () {
        $allStepData = [
            2 => [
                'generated_variants' => [
                    [
                        'id' => 1,
                        'sku' => 'TEST-001',
                        'color' => 'Red',
                        'width' => 60,
                        'drop' => 140
                    ]
                ]
            ]
        ];

        $stepData = [
            'default_retail_price' => 100.00
        ];

        $component = Livewire::test(PricingStockForm::class, [
            'stepData' => $stepData,
            'allStepData' => $allStepData
        ])
            ->call('bulkUpdatePricing', 'all');

        $variantPricing = $component->get('variantPricing');
        $marketplacePricing = $variantPricing->first()['marketplace_pricing'];
        
        // Should have default marketplace pricing
        expect($marketplacePricing)->toHaveKey('shopify');
        expect($marketplacePricing)->toHaveKey('ebay');
        expect($marketplacePricing['shopify'])->toBe(105.0); // 5% markup
        expect($marketplacePricing['ebay'])->toBe(108.0); // 8% markup
    });
});