<?php

namespace App\Console\Commands;

use App\Models\MarketplaceTaxonomy;
use App\Models\SyncAccount;
use Illuminate\Console\Command;

/**
 * 🧪 CREATE TEST TAXONOMY DATA
 *
 * Creates basic taxonomy data for existing sync accounts so the
 * marketplace attributes system can be tested with real data.
 */
class CreateTestTaxonomyData extends Command
{
    protected $signature = 'create:test-taxonomy {--marketplace= : Specific marketplace name to create data for}';

    protected $description = '🧪 Create test taxonomy data for marketplace attributes testing';

    public function handle()
    {
        $this->info('🧪 Creating Test Taxonomy Data');
        $this->newLine();

        $marketplaceName = $this->option('marketplace');

        if ($marketplaceName) {
            $syncAccount = SyncAccount::where('name', $marketplaceName)->where('is_active', true)->first();
            if (! $syncAccount) {
                $this->error("❌ Marketplace '{$marketplaceName}' not found or not active");

                return;
            }
            $syncAccounts = collect([$syncAccount]);
        } else {
            $syncAccounts = SyncAccount::where('is_active', true)->get();
        }

        foreach ($syncAccounts as $syncAccount) {
            $this->createTaxonomyForMarketplace($syncAccount);
        }

        $this->newLine();
        $this->info('✅ Test taxonomy data creation completed!');
    }

    protected function createTaxonomyForMarketplace(SyncAccount $syncAccount): void
    {
        $this->line("🏗️ Creating taxonomy for: {$syncAccount->name} ({$syncAccount->channel})");

        // Skip if already has taxonomy data
        $existing = MarketplaceTaxonomy::where('sync_account_id', $syncAccount->id)->count();
        if ($existing > 0) {
            $this->line("  ⚠️  Already has {$existing} taxonomy items, skipping");

            return;
        }

        $taxonomyItems = [
            [
                'taxonomy_type' => 'attribute',
                'external_id' => 'attr_material',
                'name' => 'Material',
                'key' => 'material',
                'description' => 'Product material or fabric type',
                'data_type' => 'text',
                'is_required' => true,
            ],
            [
                'taxonomy_type' => 'attribute',
                'external_id' => 'attr_color',
                'name' => 'Color',
                'key' => 'color',
                'description' => 'Primary color of the product',
                'data_type' => 'text',
                'is_required' => true,
            ],
            [
                'taxonomy_type' => 'attribute',
                'external_id' => 'attr_size',
                'name' => 'Size',
                'key' => 'size',
                'description' => 'Product size or dimensions',
                'data_type' => 'text',
                'is_required' => false,
            ],
            [
                'taxonomy_type' => 'attribute',
                'external_id' => 'attr_style',
                'name' => 'Style',
                'key' => 'style',
                'description' => 'Product style or design type',
                'data_type' => 'list',
                'is_required' => false,
                'validation_rules' => ['choices' => ['Modern', 'Traditional', 'Contemporary', 'Minimalist', 'Industrial']],
            ],
            [
                'taxonomy_type' => 'attribute',
                'external_id' => 'attr_brand',
                'name' => 'Brand',
                'key' => 'brand',
                'description' => 'Product brand or manufacturer',
                'data_type' => 'text',
                'is_required' => false,
            ],
        ];

        foreach ($taxonomyItems as $item) {
            MarketplaceTaxonomy::create(array_merge($item, [
                'sync_account_id' => $syncAccount->id,
                'level' => 1,
                'is_leaf' => true,
                'metadata' => [],
                'properties' => [],
                'last_synced_at' => now(),
                'is_active' => true,
                'sync_version' => '1.0',
            ]));
        }

        $this->line('  ✅ Created '.count($taxonomyItems).' test taxonomy items');
    }
}
