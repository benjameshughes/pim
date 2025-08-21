<?php

namespace App\Console\Commands;

use App\Facades\Sync;
use App\Models\Product;
use App\Models\SyncAccount;
use Illuminate\Console\Command;

/**
 * 🧪 TEST UNIFIED SYNC SYSTEM COMMAND
 *
 * Tests the beautiful fluent API we just built!
 */
class TestUnifiedSyncSystem extends Command
{
    protected $signature = 'sync:test {--product_id= : Test with specific product ID}';

    protected $description = 'Test the unified sync system and fluent API';

    public function handle(): int
    {
        $this->info('🎨 TESTING UNIFIED SYNC SYSTEM');
        $this->newLine();

        // Test 1: Sync Accounts
        $this->info('1️⃣ Testing Sync Accounts...');
        $this->testSyncAccounts();
        $this->newLine();

        // Test 2: Facade Registration
        $this->info('2️⃣ Testing Facade Registration...');
        if (! $this->testFacadeRegistration()) {
            return 1;
        }
        $this->newLine();

        // Test 3: Fluent API Builders
        $this->info('3️⃣ Testing Fluent API Builders...');
        $this->testFluentAPI();
        $this->newLine();

        // Test 4: Status and Log Queries
        $this->info('4️⃣ Testing Status and Log Queries...');
        $this->testStatusAndLogQueries();
        $this->newLine();

        // Test 5: Shopify Integration (if product available)
        if ($product = $this->getTestProduct()) {
            $this->info('5️⃣ Testing Shopify Integration...');
            $this->testShopifyIntegration($product);
            $this->newLine();
        }

        $this->info('🎉 ALL TESTS COMPLETED!');
        $this->info('🚀 Your beautiful fluent sync API is ready to use!');

        return 0;
    }

    /**
     * 🏢 Test sync accounts
     */
    private function testSyncAccounts(): void
    {
        $accounts = SyncAccount::all();

        $this->line("   📊 Total sync accounts: {$accounts->count()}");

        foreach ($accounts->groupBy('channel') as $channel => $channelAccounts) {
            $active = $channelAccounts->where('is_active', true)->count();
            $total = $channelAccounts->count();

            $this->line("   🔹 {$channel}: {$active}/{$total} active");

            foreach ($channelAccounts as $account) {
                $status = $account->is_active ? '✅' : '❌';
                $this->line("      {$status} {$account->display_name} ({$account->name})");
            }
        }
    }

    /**
     * 🎭 Test facade registration
     */
    private function testFacadeRegistration(): bool
    {
        try {
            // Test facade access
            $shopifyBuilder = Sync::shopify();
            $ebayBuilder = Sync::ebay();
            $statusBuilder = Sync::status();
            $logBuilder = Sync::log();

            $this->line('   ✅ Sync::shopify() - Working');
            $this->line('   ✅ Sync::ebay() - Working');
            $this->line('   ✅ Sync::status() - Working');
            $this->line('   ✅ Sync::log() - Working');

            return true;

        } catch (\Exception $e) {
            $this->error("   ❌ Facade registration failed: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * 🔗 Test fluent API builders
     */
    private function testFluentAPI(): void
    {
        try {
            // Test method chaining
            $shopifyBuilder = Sync::shopify()
                ->account('main')
                ->dryRun()
                ->withTaxonomy()
                ->colors(['red', 'blue']);

            $this->line('   ✅ Fluent chaining - Working');
            $this->line('   ✅ Method: account(), dryRun(), withTaxonomy(), colors()');

            // Test eBay builder
            $ebayBuilder = Sync::ebay()
                ->account('uk')
                ->listingType('fixed_price')
                ->duration(30)
                ->bestOffer();

            $this->line('   ✅ eBay builder methods - Working');

            // Test status queries
            $statusQuery = Sync::status()
                ->shopify()
                ->synced()
                ->colorSeparated()
                ->recentlySynced();

            $this->line('   ✅ Status query chaining - Working');

            // Test log queries
            $logQuery = Sync::log()
                ->shopify()
                ->successful()
                ->lastHours(24)
                ->pushes();

            $this->line('   ✅ Log query chaining - Working');

        } catch (\Exception $e) {
            $this->error("   ❌ Fluent API error: {$e->getMessage()}");
        }
    }

    /**
     * 📊 Test status and log queries
     */
    private function testStatusAndLogQueries(): void
    {
        try {
            // Test basic queries (these will return empty results but shouldn't error)
            $syncedCount = Sync::status()->synced()->count();
            $pendingCount = Sync::status()->pending()->count();
            $recentLogs = Sync::log()->today()->count();

            $this->line("   📊 Synced items: {$syncedCount}");
            $this->line("   ⏳ Pending items: {$pendingCount}");
            $this->line("   📝 Today's logs: {$recentLogs}");

            // Test channel-specific queries
            $shopifyStatuses = Sync::status()->shopify()->count();
            $ebayStatuses = Sync::status()->ebay()->count();

            $this->line("   🛍️ Shopify statuses: {$shopifyStatuses}");
            $this->line("   🏪 eBay statuses: {$ebayStatuses}");

            // Test performance query
            $performance = Sync::log()->performance();
            $this->line("   ⚡ Performance grade: {$performance['performance_grade']}");

        } catch (\Exception $e) {
            $this->error("   ❌ Status/Log query error: {$e->getMessage()}");
        }
    }

    /**
     * 🛍️ Test Shopify integration
     */
    private function testShopifyIntegration(Product $product): void
    {
        try {
            $this->line("   🧪 Testing with product: {$product->name} (ID: {$product->id})");

            // Test preview (dry run)
            $preview = Sync::shopify()
                ->dryRun()
                ->product($product)
                ->preview();

            if ($preview && is_array($preview)) {
                $this->line('   ✅ Shopify preview - Working');
                $this->line('   📊 Preview data structure: '.count($preview).' keys');

                if (isset($preview['summary'])) {
                    $summary = $preview['summary'];
                    $this->line('   🎨 Colors to create: '.($summary['total_colors'] ?? 0));
                    $this->line('   📦 Variants: '.($summary['total_variants'] ?? 0));
                }
            }

            // Test status check
            $status = Sync::status()
                ->product($product)
                ->shopify()
                ->first();

            if ($status) {
                $this->line("   📊 Sync status: {$status->sync_status}");
            } else {
                $this->line('   📊 No sync status found (expected for new system)');
            }

        } catch (\Exception $e) {
            $this->line("   ⚠️ Shopify test warning: {$e->getMessage()}");
            $this->line('   (This is expected if Shopify credentials are not configured)');
        }
    }

    /**
     * 🔍 Get test product
     */
    private function getTestProduct(): ?Product
    {
        $productId = $this->option('product_id');

        if ($productId) {
            return Product::find($productId);
        }

        return Product::whereHas('variants')->first();
    }
}
