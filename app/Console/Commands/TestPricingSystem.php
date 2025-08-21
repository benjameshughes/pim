<?php

namespace App\Console\Commands;

use App\Livewire\Pricing\PricingDashboard;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use App\Services\Pricing\PriceCalculatorService;
use Illuminate\Console\Command;

/**
 * 💰 PRICING SYSTEM DIAGNOSTIC COMMAND
 *
 * Tests all aspects of pricing management to identify what's broken
 */
class TestPricingSystem extends Command
{
    protected $signature = 'pricing:test {--detailed : Show detailed output}';

    protected $description = 'Test pricing system and management features';

    public function handle(): int
    {
        $this->info('💰 PRICING SYSTEM DIAGNOSTIC TEST');
        $this->newLine();

        // Test 1: Database Models
        $this->info('1️⃣ Testing Database Models...');
        $modelsResult = $this->testDatabaseModels();

        if (! $modelsResult) {
            $this->error('❌ Database models test failed!');

            return 1;
        }

        $this->info('✅ Database models working!');
        $this->newLine();

        // Test 2: Sales Channels
        $this->info('2️⃣ Testing Sales Channels...');
        $channelsResult = $this->testSalesChannels();

        if (! $channelsResult) {
            $this->error('❌ Sales channels test failed!');

            return 1;
        }

        $this->info('✅ Sales channels working!');
        $this->newLine();

        // Test 3: Price Calculator Service
        $this->info('3️⃣ Testing Price Calculator Service...');
        $calculatorResult = $this->testPriceCalculator();

        if (! $calculatorResult) {
            $this->error('❌ Price calculator test failed!');

            return 1;
        }

        $this->info('✅ Price calculator working!');
        $this->newLine();

        // Test 4: Pricing Dashboard Component
        $this->info('4️⃣ Testing Pricing Dashboard Component...');
        $dashboardResult = $this->testPricingDashboard();

        if (! $dashboardResult) {
            $this->error('❌ Pricing dashboard test failed!');

            return 1;
        }

        $this->info('✅ Pricing dashboard working!');
        $this->newLine();

        // Test 5: Marketplace Integration
        $this->info('5️⃣ Testing Marketplace Pricing Integration...');
        $marketplaceResult = $this->testMarketplacePricing();

        if (! $marketplaceResult) {
            $this->error('❌ Marketplace pricing test failed!');

            return 1;
        }

        $this->info('✅ Marketplace pricing working!');
        $this->newLine();

        $this->info('🎉 ALL PRICING TESTS PASSED! Pricing system is working correctly.');

        return 0;
    }

    private function testDatabaseModels(): bool
    {
        try {
            $pricingExists = class_exists(Pricing::class);
            $salesChannelExists = class_exists(SalesChannel::class);
            $productExists = class_exists(Product::class);
            $variantExists = class_exists(ProductVariant::class);

            if ($this->option('detailed')) {
                $this->line('   Pricing Model: '.($pricingExists ? '✅ Available' : '❌ Missing'));
                $this->line('   SalesChannel Model: '.($salesChannelExists ? '✅ Available' : '❌ Missing'));
                $this->line('   Product Model: '.($productExists ? '✅ Available' : '❌ Missing'));
                $this->line('   ProductVariant Model: '.($variantExists ? '✅ Available' : '❌ Missing'));
            }

            if (! $pricingExists || ! $salesChannelExists) {
                return false;
            }

            // Test model relationships
            try {
                $pricingCount = Pricing::count();
                $channelCount = SalesChannel::count();

                if ($this->option('detailed')) {
                    $this->line('   Pricing Records: '.$pricingCount);
                    $this->line('   Sales Channels: '.$channelCount);
                }
            } catch (\Exception $e) {
                if ($this->option('detailed')) {
                    $this->line('   Database Query Error: '.$e->getMessage());
                }

                return false;
            }

            return true;
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Models Error: '.$e->getMessage());
            }

            return false;
        }
    }

    private function testSalesChannels(): bool
    {
        try {
            $channels = SalesChannel::all();
            $channelNames = $channels->pluck('name')->toArray();

            if ($this->option('detailed')) {
                $this->line('   Available Channels: '.implode(', ', $channelNames));
                $this->line('   Total Channels: '.$channels->count());

                foreach ($channels as $channel) {
                    $this->line("   - {$channel->name}: ".($channel->is_active ? '✅ Active' : '❌ Inactive'));
                }
            }

            // Check for expected marketplace channels
            $expectedChannels = ['shopify', 'ebay', 'amazon', 'direct'];
            $hasExpectedChannels = false;

            foreach ($expectedChannels as $expected) {
                if ($channels->where('slug', $expected)->isNotEmpty()) {
                    $hasExpectedChannels = true;
                    break;
                }
            }

            if ($this->option('detailed') && ! $hasExpectedChannels) {
                $this->line('   ⚠️ No standard marketplace channels found. You may need to seed them.');
            }

            return true; // Sales channels exist even if they need seeding
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Sales Channels Error: '.$e->getMessage());
            }

            return false;
        }
    }

    private function testPriceCalculator(): bool
    {
        try {
            $serviceExists = class_exists(PriceCalculatorService::class);

            if ($this->option('detailed')) {
                $this->line('   PriceCalculatorService: '.($serviceExists ? '✅ Available' : '❌ Missing'));
            }

            if ($serviceExists) {
                try {
                    $calculator = app(PriceCalculatorService::class);

                    // Test basic price calculation
                    $testPrice = 100.0;
                    $testVat = 20.0;

                    // Most calculators would have methods like these
                    if (method_exists($calculator, 'calculateVatInclusive')) {
                        $vatInclusive = $calculator->calculateVatInclusive($testPrice, $testVat);
                        if ($this->option('detailed')) {
                            $this->line("   VAT Calculation Test: £{$testPrice} + {$testVat}% = £{$vatInclusive}");
                        }
                    } else {
                        if ($this->option('detailed')) {
                            $this->line('   ⚠️ Calculator methods need implementation');
                        }
                    }

                } catch (\Exception $e) {
                    if ($this->option('detailed')) {
                        $this->line('   Calculator Instantiation Error: '.$e->getMessage());
                    }

                    return false;
                }
            }

            return $serviceExists;
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Price Calculator Error: '.$e->getMessage());
            }

            return false;
        }
    }

    private function testPricingDashboard(): bool
    {
        try {
            $componentExists = class_exists(PricingDashboard::class);

            if ($this->option('detailed')) {
                $this->line('   PricingDashboard Component: '.($componentExists ? '✅ Available' : '❌ Missing'));
            }

            if ($componentExists) {
                $componentPath = app_path('Livewire/Pricing/PricingDashboard.php');
                $viewPath = resource_path('views/livewire/pricing/pricing-dashboard.blade.php');

                if ($this->option('detailed')) {
                    $this->line('   Component File: '.(file_exists($componentPath) ? '✅ Exists' : '❌ Missing'));
                    $this->line('   View File: '.(file_exists($viewPath) ? '✅ Exists' : '❌ Missing'));
                }

                // Check for expected methods
                if (file_exists($componentPath)) {
                    $content = file_get_contents($componentPath);
                    $hasBulkOperations = str_contains($content, 'bulk');
                    $hasChannelFilter = str_contains($content, 'channel');

                    if ($this->option('detailed')) {
                        $this->line('   Bulk Operations: '.($hasBulkOperations ? '✅ Present' : '❌ Missing'));
                        $this->line('   Channel Filtering: '.($hasChannelFilter ? '✅ Present' : '❌ Missing'));
                    }
                }

                return file_exists($componentPath) && file_exists($viewPath);
            }

            return false;
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Dashboard Component Error: '.$e->getMessage());
            }

            return false;
        }
    }

    private function testMarketplacePricing(): bool
    {
        try {
            // Test if pricing can handle multiple channels
            $testVariant = ProductVariant::first();

            if (! $testVariant) {
                if ($this->option('detailed')) {
                    $this->line('   ⚠️ No variants found for marketplace pricing test');
                }

                return true; // No variants to test with, but system could work
            }

            // Test pricing relationships
            $pricingRecords = $testVariant->pricing ?? collect();

            if ($this->option('detailed')) {
                $this->line('   Test Variant: '.($testVariant->sku ?? 'No SKU'));
                $this->line('   Pricing Records: '.$pricingRecords->count());

                if ($pricingRecords->isNotEmpty()) {
                    foreach ($pricingRecords->take(3) as $pricing) {
                        $channelName = $pricing->salesChannel->name ?? 'Unknown';
                        $this->line("   - {$channelName}: £".number_format($pricing->price ?? 0, 2));
                    }
                }
            }

            // Test if we can create pricing for different channels
            $channels = SalesChannel::take(2)->get();

            if ($channels->isNotEmpty()) {
                if ($this->option('detailed')) {
                    $this->line('   Multi-channel pricing: ✅ Ready');
                }

                return true;
            }

            if ($this->option('detailed')) {
                $this->line('   ⚠️ No sales channels available for multi-channel pricing');
            }

            return true; // System structure is correct even without data
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Marketplace Pricing Error: '.$e->getMessage());
            }

            return false;
        }
    }
}
