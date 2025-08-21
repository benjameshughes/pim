<?php

namespace App\Console\Commands;

use App\Models\MarketplaceLink;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SkuLink;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 🔄 MIGRATE SKU LINKS TO MARKETPLACE LINKS
 *
 * Comprehensive migration command to transform existing sku_links data
 * to the new hierarchical marketplace_links structure while preserving
 * all existing relationships and data integrity.
 */
class MigrateSkuLinksToMarketplaceLinks extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sku-links:migrate-to-marketplace 
                            {--dry-run : Run migration in dry-run mode without making changes}
                            {--force : Skip confirmation prompts}
                            {--batch-size=100 : Number of records to process per batch}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate existing sku_links data to the new hierarchical marketplace_links structure';

    /**
     * Migration statistics
     */
    private array $stats = [
        'total_sku_links' => 0,
        'migrated_product_links' => 0,
        'created_variant_links' => 0,
        'skipped_links' => 0,
        'error_links' => 0,
        'errors' => [],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 SKU Links to Marketplace Links Migration');
        $this->newLine();

        // Check if migration is needed
        if (! $this->migrationNeeded()) {
            $this->info('✅ No migration needed - sku_links table is empty or marketplace_links already populated');

            return Command::SUCCESS;
        }

        // Display migration plan
        $this->displayMigrationPlan();

        // Confirm migration unless forced
        if (! $this->option('force') && ! $this->confirm('Do you want to proceed with the migration?')) {
            $this->info('Migration cancelled by user');

            return Command::SUCCESS;
        }

        // Run migration
        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        return $this->runMigration($isDryRun);
    }

    /**
     * 🔍 CHECK IF MIGRATION IS NEEDED
     */
    private function migrationNeeded(): bool
    {
        $skuLinksCount = SkuLink::count();
        $marketplaceLinksCount = MarketplaceLink::count();

        $this->stats['total_sku_links'] = $skuLinksCount;

        if ($skuLinksCount === 0) {
            return false;
        }

        // If marketplace_links has data, check if it's from this migration
        if ($marketplaceLinksCount > 0) {
            $this->warn("⚠️  marketplace_links table already contains {$marketplaceLinksCount} records");

            return $this->confirm('Continue migration anyway? (This may create duplicates)');
        }

        return true;
    }

    /**
     * 📋 DISPLAY MIGRATION PLAN
     */
    private function displayMigrationPlan(): void
    {
        $this->info('📋 Migration Plan:');
        $this->line("• Total SKU Links to migrate: {$this->stats['total_sku_links']}");
        $this->line('• Each sku_link will be converted to a product-level marketplace_link');
        $this->line('• Variant-level links will be created for products with multiple variants');
        $this->line('• All existing data and relationships will be preserved');
        $this->line('• Original sku_links will remain untouched for rollback safety');
        $this->newLine();

        $this->info('🏗️ Migration Process:');
        $this->line('1. Backup current state');
        $this->line('2. Create product-level marketplace links from sku_links');
        $this->line('3. Create variant-level marketplace links where applicable');
        $this->line('4. Validate data integrity');
        $this->line('5. Generate migration report');
        $this->newLine();
    }

    /**
     * 🚀 RUN MIGRATION
     */
    private function runMigration(bool $isDryRun): int
    {
        try {
            if (! $isDryRun) {
                $this->createBackup();
            }

            $this->migrateSkuLinksToMarketplaceLinks($isDryRun);
            $this->createVariantLinks($isDryRun);
            $this->validateMigration();
            $this->displayMigrationReport();

            if (! $isDryRun) {
                $this->info('✅ Migration completed successfully!');
            } else {
                $this->info('🧪 Dry run completed successfully!');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Migration failed: '.$e->getMessage());
            Log::error('SKU Links Migration Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'stats' => $this->stats,
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * 💾 CREATE BACKUP
     */
    private function createBackup(): void
    {
        $this->info('💾 Creating backup of current state...');

        $timestamp = now()->format('Y_m_d_H_i_s');
        $backupFile = storage_path("app/backups/sku_links_backup_{$timestamp}.sql");

        // Ensure backup directory exists
        if (! is_dir(dirname($backupFile))) {
            mkdir(dirname($backupFile), 0755, true);
        }

        // Create simple JSON backup of sku_links data
        $skuLinksData = SkuLink::with(['product', 'syncAccount'])->get()->toArray();
        file_put_contents($backupFile.'.json', json_encode($skuLinksData, JSON_PRETTY_PRINT));

        $this->info("✅ Backup created: {$backupFile}.json");
    }

    /**
     * 🔗 MIGRATE SKU LINKS TO MARKETPLACE LINKS
     */
    private function migrateSkuLinksToMarketplaceLinks(bool $isDryRun): void
    {
        $this->info('🔗 Migrating sku_links to marketplace_links (product level)...');

        $batchSize = (int) $this->option('batch-size');
        $progressBar = $this->output->createProgressBar($this->stats['total_sku_links']);

        SkuLink::chunk($batchSize, function ($skuLinks) use ($isDryRun, $progressBar) {
            foreach ($skuLinks as $skuLink) {
                try {
                    $this->migrateSkuLink($skuLink, $isDryRun);
                    $this->stats['migrated_product_links']++;
                } catch (\Exception $e) {
                    $this->stats['error_links']++;
                    $this->stats['errors'][] = [
                        'sku_link_id' => $skuLink->id,
                        'error' => $e->getMessage(),
                    ];
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();
        $this->info("✅ Migrated {$this->stats['migrated_product_links']} product-level links");
    }

    /**
     * 🔗 MIGRATE SINGLE SKU LINK
     */
    private function migrateSkuLink(SkuLink $skuLink, bool $isDryRun): void
    {
        if (! $skuLink->product) {
            throw new \Exception("SKU Link {$skuLink->id} has no associated product");
        }

        // Check if marketplace link already exists
        $existingLink = MarketplaceLink::where('linkable_type', Product::class)
            ->where('linkable_id', $skuLink->product_id)
            ->where('sync_account_id', $skuLink->sync_account_id)
            ->first();

        if ($existingLink) {
            $this->stats['skipped_links']++;

            return;
        }

        if ($isDryRun) {
            return;
        }

        // Create product-level marketplace link
        MarketplaceLink::create([
            'linkable_type' => Product::class,
            'linkable_id' => $skuLink->product_id,
            'sync_account_id' => $skuLink->sync_account_id,
            'parent_link_id' => null,
            'internal_sku' => $skuLink->internal_sku,
            'external_sku' => $skuLink->external_sku,
            'external_product_id' => $skuLink->external_product_id,
            'external_variant_id' => null,
            'link_status' => $skuLink->link_status,
            'link_level' => 'product',
            'marketplace_data' => $skuLink->marketplace_data,
            'linked_at' => $skuLink->linked_at,
            'linked_by' => $skuLink->linked_by,
            'created_at' => $skuLink->created_at,
            'updated_at' => $skuLink->updated_at,
        ]);
    }

    /**
     * 🎨 CREATE VARIANT LINKS
     */
    private function createVariantLinks(bool $isDryRun): void
    {
        $this->info('🎨 Creating variant-level marketplace links...');

        // Get all product-level marketplace links
        $productLinks = MarketplaceLink::where('link_level', 'product')
            ->with(['linkable.variants', 'syncAccount'])
            ->get();

        $progressBar = $this->output->createProgressBar($productLinks->count());

        foreach ($productLinks as $productLink) {
            try {
                $variantsCreated = $this->createVariantLinksForProduct($productLink, $isDryRun);
                $this->stats['created_variant_links'] += $variantsCreated;
            } catch (\Exception $e) {
                $this->stats['errors'][] = [
                    'product_link_id' => $productLink->id,
                    'error' => "Failed to create variant links: {$e->getMessage()}",
                ];
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("✅ Created {$this->stats['created_variant_links']} variant-level links");
    }

    /**
     * 🎨 CREATE VARIANT LINKS FOR PRODUCT
     */
    private function createVariantLinksForProduct(MarketplaceLink $productLink, bool $isDryRun): int
    {
        $product = $productLink->linkable;
        if (! $product || ! $product->variants) {
            return 0;
        }

        $variantsCreated = 0;

        foreach ($product->variants as $variant) {
            // Skip if variant link already exists
            $existingVariantLink = MarketplaceLink::where('linkable_type', ProductVariant::class)
                ->where('linkable_id', $variant->id)
                ->where('sync_account_id', $productLink->sync_account_id)
                ->first();

            if ($existingVariantLink) {
                continue;
            }

            if ($isDryRun) {
                $variantsCreated++;

                continue;
            }

            // Create variant-level marketplace link
            MarketplaceLink::create([
                'linkable_type' => ProductVariant::class,
                'linkable_id' => $variant->id,
                'sync_account_id' => $productLink->sync_account_id,
                'parent_link_id' => $productLink->id,
                'internal_sku' => $variant->sku,
                'external_sku' => $variant->external_sku ?: $variant->sku,
                'external_product_id' => $productLink->external_product_id,
                'external_variant_id' => "var_{$variant->id}_{$productLink->sync_account_id}",
                'link_status' => 'pending', // Variants need manual linking
                'link_level' => 'variant',
                'marketplace_data' => null,
                'linked_at' => null,
                'linked_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $variantsCreated++;
        }

        return $variantsCreated;
    }

    /**
     * ✅ VALIDATE MIGRATION
     */
    private function validateMigration(): void
    {
        $this->info('✅ Validating migration integrity...');

        // Check that product links count matches sku links
        $expectedProductLinks = $this->stats['migrated_product_links'];
        $actualProductLinks = MarketplaceLink::where('link_level', 'product')->count();

        if ($expectedProductLinks !== $actualProductLinks) {
            throw new \Exception("Product links mismatch: expected {$expectedProductLinks}, got {$actualProductLinks}");
        }

        // Check for orphaned variant links
        $orphanedVariants = MarketplaceLink::where('link_level', 'variant')
            ->whereNull('parent_link_id')
            ->count();

        if ($orphanedVariants > 0) {
            $this->warn("⚠️  Found {$orphanedVariants} orphaned variant links");
        }

        $this->info('✅ Migration validation completed');
    }

    /**
     * 📊 DISPLAY MIGRATION REPORT
     */
    private function displayMigrationReport(): void
    {
        $this->newLine();
        $this->info('📊 Migration Report:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total SKU Links', $this->stats['total_sku_links']],
                ['Migrated Product Links', $this->stats['migrated_product_links']],
                ['Created Variant Links', $this->stats['created_variant_links']],
                ['Skipped Links', $this->stats['skipped_links']],
                ['Error Links', $this->stats['error_links']],
            ]
        );

        if (! empty($this->stats['errors'])) {
            $this->newLine();
            $this->error('❌ Errors encountered:');
            foreach ($this->stats['errors'] as $error) {
                $this->line("• {$error['error']}");
            }
        }

        $this->newLine();
        $this->info('🎯 Next Steps:');
        $this->line('1. Test the new marketplace links functionality');
        $this->line('2. Update your application to use MarketplaceLink instead of SkuLink');
        $this->line('3. Once satisfied, you can optionally drop the sku_links table');
        $this->line('4. Run hierarchy validation: php artisan marketplace:validate-hierarchy');
    }
}
