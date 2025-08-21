<?php

namespace App\Console\Commands;

use App\Models\MarketplaceTaxonomy;
use App\Models\Product;
use App\Models\SyncAccount;
use App\Services\Marketplace\MarketplaceAttributeService;
use App\Services\Marketplace\MarketplaceTaxonomyService;
use App\Services\Marketplace\TaxonomySyncService;
use Illuminate\Console\Command;

/**
 * 🧪 TEST MARKETPLACE ATTRIBUTES SYSTEM
 *
 * Comprehensive test command to verify the complete marketplace attributes system.
 * Tests taxonomy sync, cache management, attribute assignment, and validation.
 */
class TestMarketplaceAttributesSystem extends Command
{
    protected $signature = 'test:marketplace-attributes {--demo-data : Create demo data for testing}';

    protected $description = '🧪 Test the complete marketplace attributes system functionality';

    public function handle()
    {
        $this->info('🧪 Testing Marketplace Attributes System');
        $this->newLine();

        if ($this->option('demo-data')) {
            $this->createDemoData();
        }

        $this->testTaxonomyService();
        $this->testAttributeService();
        $this->testSystemIntegration();

        $this->newLine();
        $this->info('✅ All marketplace attributes system tests completed!');
    }

    /**
     * 🏗️ Create demo data for testing
     */
    protected function createDemoData(): void
    {
        $this->info('🏗️ Creating demo data...');

        // Create demo sync account if not exists
        $syncAccount = SyncAccount::firstOrCreate([
            'name' => 'Demo Shopify Store',
            'channel' => 'shopify',
        ], [
            'display_name' => 'Demo Shopify Store',
            'credentials' => [
                'store_url' => 'demo-store.myshopify.com',
                'access_token' => 'demo_token',
            ],
            'is_active' => true,
        ]);

        // Create demo taxonomy items
        $taxonomyItems = [
            [
                'sync_account_id' => $syncAccount->id,
                'taxonomy_type' => 'attribute',
                'external_id' => 'attr_material',
                'name' => 'Material',
                'key' => 'material',
                'description' => 'Product material or fabric type',
                'data_type' => 'text',
                'is_required' => true,
                'is_active' => true,
                'last_synced_at' => now(),
            ],
            [
                'sync_account_id' => $syncAccount->id,
                'taxonomy_type' => 'attribute',
                'external_id' => 'attr_color',
                'name' => 'Color',
                'key' => 'color',
                'description' => 'Primary color of the product',
                'data_type' => 'text',
                'is_required' => true,
                'is_active' => true,
                'last_synced_at' => now(),
            ],
            [
                'sync_account_id' => $syncAccount->id,
                'taxonomy_type' => 'attribute',
                'external_id' => 'attr_style',
                'name' => 'Style',
                'key' => 'style',
                'description' => 'Product style or design type',
                'data_type' => 'list',
                'is_required' => false,
                'validation_rules' => ['choices' => ['Modern', 'Traditional', 'Contemporary', 'Minimalist']],
                'is_active' => true,
                'last_synced_at' => now(),
            ],
        ];

        foreach ($taxonomyItems as $item) {
            MarketplaceTaxonomy::updateOrCreate(
                [
                    'sync_account_id' => $item['sync_account_id'],
                    'taxonomy_type' => $item['taxonomy_type'],
                    'external_id' => $item['external_id'],
                ],
                $item
            );
        }

        $this->line("✅ Created demo sync account: {$syncAccount->name}");
        $this->line('✅ Created '.count($taxonomyItems).' demo taxonomy items');
    }

    /**
     * 🏷️ Test TaxonomyService functionality
     */
    protected function testTaxonomyService(): void
    {
        $this->info('🏷️ Testing TaxonomyService...');

        $service = new MarketplaceTaxonomyService;

        // Test getting sync accounts
        $syncAccounts = SyncAccount::where('is_active', true)->get();
        $this->line("📊 Found {$syncAccounts->count()} active sync accounts");

        if ($syncAccounts->isEmpty()) {
            $this->warn('⚠️  No active sync accounts found. Run with --demo-data to create test data.');

            return;
        }

        $syncAccount = $syncAccounts->first();
        $this->line("🔍 Testing with: {$syncAccount->name} ({$syncAccount->channel})");

        // Test getting attributes
        $attributes = $service->getAttributes($syncAccount);
        $this->line("✅ Retrieved {$attributes->count()} attributes");

        foreach ($attributes as $attribute) {
            $this->line("   - {$attribute->name} ({$attribute->key}) ".
                       ($attribute->is_required ? '[Required]' : '[Optional]'));
        }

        // Test taxonomy stats
        $stats = $service->getTaxonomyStats($syncAccount);
        $this->line('📊 Taxonomy Stats:');
        $this->line("   - Categories: {$stats['categories']['total']} total, {$stats['categories']['active']} active");
        $this->line("   - Attributes: {$stats['attributes']['total']} total, {$stats['attributes']['active']} active");
        $this->line("   - Values: {$stats['values']['total']} total, {$stats['values']['active']} active");

        // Test health report
        $health = $service->getHealthReport($syncAccount);
        $this->line("🏥 Health Report: {$health['health_score']}% ({$health['status']})");

        if (! empty($health['issues'])) {
            $this->line('⚠️  Issues found:');
            foreach ($health['issues'] as $issue) {
                $this->line("   - {$issue}");
            }
        }
    }

    /**
     * 🔧 Test AttributeService functionality
     */
    protected function testAttributeService(): void
    {
        $this->info('🔧 Testing MarketplaceAttributeService...');

        $service = new MarketplaceAttributeService;
        $syncAccount = SyncAccount::where('is_active', true)->first();

        if (! $syncAccount) {
            $this->warn('⚠️  No sync account available for testing');

            return;
        }

        // Get test product
        $product = Product::with('variants')->first();
        if (! $product) {
            $this->warn('⚠️  No products available for testing');

            return;
        }

        $this->line("🔍 Testing with product: {$product->name} (ID: {$product->id})");

        // Test attribute assignment
        try {
            $attribute = $service->assignAttribute(
                $product,
                $syncAccount,
                'material',
                'Cotton',
                [
                    'display_value' => '100% Cotton',
                    'assigned_via' => 'test',
                ]
            );

            $this->line("✅ Assigned attribute: {$attribute->attribute_name} = {$attribute->attribute_value}");
        } catch (\Exception $e) {
            $this->line("⚠️  Attribute assignment test skipped: {$e->getMessage()}");
        }

        // Test getting product attributes
        $productAttributes = $service->getProductAttributes($product, $syncAccount);
        $this->line("📋 Product has {$productAttributes->count()} marketplace attributes");

        foreach ($productAttributes as $attr) {
            $this->line("   - {$attr->attribute_name}: {$attr->getDisplayValue()} ".
                       ($attr->is_valid ? '✅' : '❌'));
        }

        // Test completion percentage
        $completion = $service->getCompletionPercentage($product, $syncAccount);
        $this->line("📊 Marketplace completion: {$completion}%");

        // Test readiness report
        $readiness = $service->getMarketplaceReadinessReport($product, $syncAccount);
        $this->line("🎯 Readiness score: {$readiness['readiness_score']}% ({$readiness['status']})");

        if (! empty($readiness['recommendations'])) {
            $this->line('💡 Recommendations:');
            foreach ($readiness['recommendations'] as $rec) {
                $this->line("   - {$rec['message']}");
            }
        }
    }

    /**
     * 🔄 Test system integration
     */
    protected function testSystemIntegration(): void
    {
        $this->info('🔄 Testing System Integration...');

        // Test sync service
        $syncService = new TaxonomySyncService;

        $this->line('📊 Sync service initialized successfully');

        // Test cache status for all sync accounts
        $syncAccounts = SyncAccount::where('is_active', true)->get();

        foreach ($syncAccounts as $syncAccount) {
            $status = $syncService->getCacheStatus($syncAccount);

            $this->line("🔍 Cache status for {$syncAccount->name}:");
            $this->line("   - Categories: {$status['categories']['total']} ({$status['categories']['active']} active)");
            $this->line("   - Attributes: {$status['attributes']['total']} ({$status['attributes']['active']} active)");
            $this->line("   - Values: {$status['values']['total']} ({$status['values']['active']} active)");

            if ($status['last_sync']) {
                $this->line("   - Last sync: {$status['last_sync']}");
            } else {
                $this->line('   - ⚠️  Never synced');
            }
        }

        // Test form field building
        $taxonomyService = new MarketplaceTaxonomyService;
        $syncAccount = $syncAccounts->first();

        if ($syncAccount) {
            $formFields = $taxonomyService->buildFormFields($syncAccount);
            $this->line('📝 Generated '.count($formFields).' form fields for dynamic UI');

            foreach (array_slice($formFields, 0, 3) as $field) {
                $this->line("   - {$field['name']} ({$field['type']}) ".
                           ($field['required'] ? '[Required]' : '[Optional]'));
            }
        }
    }
}
