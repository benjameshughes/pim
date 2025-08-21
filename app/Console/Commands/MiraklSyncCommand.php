<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Mirakl\MiraklSyncService;
use Illuminate\Console\Command;

class MiraklSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mirakl:sync 
                            {--operator= : Sync to specific operator (freemans, debenhams, bq) or "all"}
                            {--products= : Product SKU pattern (e.g., TEST-005, 002*, etc.)}
                            {--category= : Override default category for the operator}
                            {--dry-run : Show what would be synced without actually syncing}
                            {--test-connections : Test connections to all operators}
                            {--stats : Show sync statistics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products to Mirakl operators (Universal Mirakl Integration)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Universal Mirakl Sync Command');
        $this->newLine();

        // Handle different command modes
        if ($this->option('test-connections')) {
            return $this->testConnections();
        }

        if ($this->option('stats')) {
            return $this->showStats();
        }

        // Main sync operation
        return $this->performSync();
    }

    /**
     * 🎯 PERFORM SYNC OPERATION
     */
    protected function performSync(): int
    {
        $operator = $this->option('operator');
        $productPattern = $this->option('products');
        $category = $this->option('category');
        $dryRun = $this->option('dry-run');

        // Validate inputs
        if (! $operator) {
            $this->error('❌ Please specify an operator with --operator=');
            $this->info('Available operators: freemans, debenhams, bq, all');

            return 1;
        }

        if (! $productPattern) {
            $this->error('❌ Please specify products with --products=');
            $this->info('Examples: --products=TEST-005, --products=002*, --products=all');

            return 1;
        }

        // Find products
        $this->info("🔍 Finding products matching pattern: {$productPattern}");
        $products = $this->findProducts($productPattern);

        if ($products->isEmpty()) {
            $this->error("❌ No products found matching pattern: {$productPattern}");

            return 1;
        }

        $this->info("✅ Found {$products->count()} product(s)");
        $this->displayProductSummary($products);

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual sync will be performed');

            return 0;
        }

        // Confirm sync
        if (! $this->confirm("Sync {$products->count()} product(s) to {$operator}?")) {
            $this->info('❌ Sync cancelled by user');

            return 0;
        }

        // Perform sync
        $this->info('🚀 Starting sync operation...');
        $this->newLine();

        $options = [];
        if ($category) {
            $options['category'] = $category;
        }

        if ($operator === 'all') {
            $result = MiraklSyncService::syncToAll($products, $options);
        } else {
            $result = MiraklSyncService::syncToOperator($operator, $products, $options);
        }

        // Display results
        $this->displaySyncResults($result);

        return $result['success'] ? 0 : 1;
    }

    /**
     * ✅ TEST CONNECTIONS
     */
    protected function testConnections(): int
    {
        $this->info('✅ Testing connections to all Mirakl operators...');
        $this->newLine();

        $results = MiraklSyncService::testAllConnections();

        $this->displayConnectionResults($results);

        return $results['success'] ? 0 : 1;
    }

    /**
     * 📊 SHOW STATISTICS
     */
    protected function showStats(): int
    {
        $this->info('📊 Mirakl Sync Statistics');
        $this->newLine();

        $stats = MiraklSyncService::getSyncStatistics();

        $this->displayStatistics($stats);

        return 0;
    }

    /**
     * 🔍 FIND PRODUCTS
     */
    protected function findProducts(string $pattern): \Illuminate\Support\Collection
    {
        if ($pattern === 'all') {
            return Product::with('variants')->get();
        }

        return MiraklSyncService::findProductsByPattern($pattern);
    }

    /**
     * 📋 DISPLAY PRODUCT SUMMARY
     */
    protected function displayProductSummary($products): void
    {
        $this->newLine();
        $this->info('📋 Products to sync:');

        foreach ($products->take(10) as $product) {
            $variantCount = $product->variants->count();
            $this->line("  • {$product->parent_sku} - {$product->name} ({$variantCount} variants)");
        }

        if ($products->count() > 10) {
            $remaining = $products->count() - 10;
            $this->line("  ... and {$remaining} more products");
        }

        $this->newLine();
    }

    /**
     * 📊 DISPLAY SYNC RESULTS
     */
    protected function displaySyncResults(array $result): void
    {
        if ($result['success']) {
            $this->info('✅ '.$result['message']);
        } else {
            $this->error('❌ '.$result['message']);
        }

        $this->newLine();

        // Show summary for batch operations
        if (isset($result['summary'])) {
            $summary = $result['summary'];
            $this->info('📊 Sync Summary:');
            $this->line("  Total operators: {$summary['total_operators']}");
            $this->line("  Successful syncs: {$summary['successful_syncs']}");
            $this->line("  Failed syncs: {$summary['failed_syncs']}");
            $this->line("  Total products uploaded: {$summary['total_products_uploaded']}");

            $this->newLine();
            $this->info('📋 Operator Results:');

            foreach ($summary['operator_results'] as $operator => $operatorResult) {
                $status = $operatorResult['success'] ? '✅' : '❌';
                $uploadCount = $operatorResult['products_uploaded'];
                $importId = $operatorResult['import_id'] ? " (Import: {$operatorResult['import_id']})" : '';

                $this->line("  {$status} {$operator}: {$uploadCount} products{$importId}");
            }
        }

        // Show individual operation details
        if (isset($result['import_id'])) {
            $this->info("🆔 Import ID: {$result['import_id']}");
        }

        if (isset($result['new_products_count'])) {
            $this->info("📦 Products uploaded: {$result['new_products_count']}");
        }

        if (isset($result['estimated_completion'])) {
            $this->info("⏰ Estimated completion: {$result['estimated_completion']}");
        }
    }

    /**
     * 🔗 DISPLAY CONNECTION RESULTS
     */
    protected function displayConnectionResults(array $results): void
    {
        $summary = $results['summary'];

        $this->info('📊 Connection Test Summary:');
        $this->line("  Total operators: {$summary['total_operators']}");
        $this->line("  Successful connections: {$summary['successful_connections']}");
        $this->line("  Failed connections: {$summary['failed_connections']}");

        $this->newLine();
        $this->info('🔗 Individual Results:');

        foreach ($summary['operators_tested'] as $operator => $test) {
            $status = $test['success'] ? '✅' : '❌';
            $message = $test['message'];
            $baseUrl = $test['base_url'];

            $this->line("  {$status} {$operator}: {$message}");
            $this->line("    Base URL: {$baseUrl}");
        }
    }

    /**
     * 📈 DISPLAY STATISTICS
     */
    protected function displayStatistics(array $stats): void
    {
        $overall = $stats['overall_stats'];

        $this->info('📊 Overall Statistics:');
        $this->line("  Total operators: {$stats['total_operators']}");
        $this->line("  Total marketplace links: {$overall['total_marketplace_links']}");
        $this->line("  Linked products: {$overall['linked_products']}");
        $this->line("  Pending products: {$overall['pending_products']}");
        $this->line("  Failed products: {$overall['failed_products']}");

        $this->newLine();
        $this->info('📋 Operator Breakdown:');

        foreach ($stats['operators'] as $operator => $operatorStats) {
            $this->line("  🏪 {$operator} ({$operatorStats['display_name']}):");
            $this->line("    Status: {$operatorStats['status']}");
            $this->line("    Marketplace links: {$operatorStats['marketplace_links']}");
            $this->line("    Linked: {$operatorStats['linked_products']} | Pending: {$operatorStats['pending_products']} | Failed: {$operatorStats['failed_products']}");
        }
    }
}
