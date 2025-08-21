<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Mirakl\Operators\FreemansOperatorClient;
use App\Services\Mirakl\ProductCsvGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 🧪 TEST COMPLETE UPSERT OR CREATE WORKFLOW
 *
 * Tests the complete dual API workflow:
 * 1. CSV upload for new products (catalog)
 * 2. JSON API for offers (pricing/inventory)
 */
class TestCompleteUpsertOrCreateWorkflow extends Command
{
    protected $signature = 'test:upsert-workflow {marketplace=freemans} {--dry-run : Preview what would be done}';

    protected $description = 'Test complete upsertOrCreate workflow with CSV + JSON APIs';

    public function handle(): int
    {
        $marketplace = $this->argument('marketplace');
        $dryRun = $this->option('dry-run');

        $this->info("🧪 Testing Complete UpsertOrCreate Workflow for {$marketplace}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual API calls will be made');
            $this->newLine();
        }

        // Step 1: Create test products for the workflow
        $products = $this->createTestProducts();
        $this->displayProductsSummary($products);

        if ($dryRun) {
            $this->previewWorkflow($products, $marketplace);

            return 0;
        }

        // Step 2: Execute the complete workflow
        $this->executeWorkflow($products, $marketplace);

        return 0;
    }

    /**
     * 🏗️ CREATE TEST PRODUCTS
     */
    protected function createTestProducts(): \Illuminate\Support\Collection
    {
        $this->info('🏗️ Creating test products for workflow testing...');

        return DB::transaction(function () {
            $products = collect();

            // Create 2 test products
            for ($i = 1; $i <= 2; $i++) {
                $product = Product::firstOrCreate([
                    'parent_sku' => "WORKFLOW-TEST-{$i}",
                ], [
                    'name' => "Workflow Test Product {$i}",
                    'description' => "Testing complete upsertOrCreate workflow with dual API approach - Product {$i}",
                    'brand' => 'Test Workflow Brand',
                    'status' => 'active',
                    'category_id' => null,
                ]);

                // Create variants for each product
                ProductVariant::firstOrCreate([
                    'product_id' => $product->id,
                    'sku' => "WORKFLOW-TEST-{$i}-WHITE",
                ], [
                    'title' => "Workflow Test Product {$i} - White",
                    'color' => 'White',
                    'width' => 120,
                    'drop' => 160,
                    'price' => 49.99 + ($i * 10),
                    'stock_level' => 10 + $i,
                ]);

                ProductVariant::firstOrCreate([
                    'product_id' => $product->id,
                    'sku' => "WORKFLOW-TEST-{$i}-CREAM",
                ], [
                    'title' => "Workflow Test Product {$i} - Cream",
                    'color' => 'Cream',
                    'width' => 120,
                    'drop' => 160,
                    'price' => 54.99 + ($i * 10),
                    'stock_level' => 8 + $i,
                ]);

                $products->push($product->load('variants'));
            }

            return $products;
        });
    }

    /**
     * 📊 DISPLAY PRODUCTS SUMMARY
     */
    protected function displayProductsSummary(\Illuminate\Support\Collection $products): void
    {
        $this->info('📊 Test Products Summary:');
        $this->info("   Products created: {$products->count()}");
        $this->info('   Total variants: '.$products->sum(fn ($p) => $p->variants->count()));

        foreach ($products as $product) {
            $this->info("   📦 {$product->parent_sku}: {$product->name}");
            foreach ($product->variants as $variant) {
                $this->info("      🏷️  {$variant->sku} - £{$variant->price} (Stock: {$variant->stock_level})");
            }
        }
        $this->newLine();
    }

    /**
     * 🔍 PREVIEW WORKFLOW
     */
    protected function previewWorkflow(\Illuminate\Support\Collection $products, string $marketplace): void
    {
        $this->info("🔍 Workflow Preview for {$marketplace}:");
        $this->newLine();

        // Preview CSV generation
        $this->info('📋 Step 1: Product Catalog (CSV Upload)');
        $csvGenerator = new ProductCsvGenerator;

        foreach ($products as $product) {
            $validation = $csvGenerator->validateProductForCsv($product);
            if ($validation['valid']) {
                $this->info("   ✅ {$product->parent_sku} - Ready for CSV");
            } else {
                $this->error("   ❌ {$product->parent_sku} - Validation errors:");
                foreach ($validation['errors'] as $error) {
                    $this->error("      • {$error}");
                }
            }
        }

        // Preview CSV content
        $this->newLine();
        $this->info('📄 CSV Preview (first 3 rows):');
        $preview = $csvGenerator->getCsvPreview($products, $marketplace, 2);

        foreach ($preview as $rowIndex => $row) {
            if ($rowIndex === 0) {
                $this->info('   📋 Headers: '.implode(' | ', $row));
            } else {
                $this->info("   📦 Row {$rowIndex}: ".implode(' | ', array_slice($row, 0, 4)).'...');
            }
        }

        $this->newLine();
        $this->info('💰 Step 2: Offers (JSON API)');
        $this->info("   📤 Would create offers for all {$products->sum(fn ($p) => $p->variants->count())} variants");

        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                $this->info("   🏷️  {$variant->sku} → £{$variant->price} (Qty: {$variant->stock_level})");
            }
        }

        $this->newLine();
        $this->info('⏱️ Estimated Timeline:');
        $this->info('   📋 CSV Upload: ~30 seconds');
        $this->info('   ⏳ Import Processing: 5-15 minutes');
        $this->info('   💰 Offers Creation: ~10 seconds');
        $this->info('   🎯 Total Time: ~6-16 minutes');
    }

    /**
     * 🚀 EXECUTE WORKFLOW
     */
    protected function executeWorkflow(\Illuminate\Support\Collection $products, string $marketplace): void
    {
        $this->info("🚀 Executing Complete Workflow for {$marketplace}");
        $this->newLine();

        try {
            $client = $this->getMarketplaceClient($marketplace);

            $this->info('📤 Starting dual API workflow...');
            $result = $client->pushProducts($products->all());

            if ($result['success']) {
                $this->info('✅ Workflow initiated successfully!');
                $this->newLine();

                $this->info('📊 Results Summary:');
                $this->info("   Operator: {$result['operator']}");
                $this->info("   Store ID: {$result['store_id']}");
                $this->info("   Links Created: {$result['links_created']}");
                $this->info("   Links Updated: {$result['links_updated']}");

                if (isset($result['results']['products_api'])) {
                    $productsApi = $result['results']['products_api'];
                    $this->info('   📋 Catalog Upload:');
                    $this->info('      Status: '.($productsApi['success'] ? '✅ Success' : '❌ Failed'));
                    if (isset($productsApi['import_id'])) {
                        $this->info("      Import ID: {$productsApi['import_id']}");
                    }
                    if (isset($productsApi['products_count'])) {
                        $this->info("      Products: {$productsApi['products_count']}");
                    }
                }

                if (isset($result['results']['offers_api'])) {
                    $offersApi = $result['results']['offers_api'];
                    $this->info('   💰 Offers Upload:');
                    $this->info('      Status: '.($offersApi['success'] ? '✅ Success' : '❌ Failed'));
                    if (isset($offersApi['import_id'])) {
                        $this->info("      Import ID: {$offersApi['import_id']}");
                    }
                    if (isset($offersApi['offers_count'])) {
                        $this->info("      Offers: {$offersApi['offers_count']}");
                    }
                }

                $this->newLine();
                $this->info('⏱️ Next Steps:');
                $this->info('   🔄 Queue jobs are running to monitor import status');
                $this->info('   📊 Check queue status with: php artisan queue:work');
                $this->info('   🔍 Monitor logs for progress updates');

            } else {
                $this->error("❌ Workflow failed: {$result['error']}");

                return;
            }

        } catch (\Exception $e) {
            $this->error("❌ Workflow execution failed: {$e->getMessage()}");

            return;
        }
    }

    /**
     * 🏭 GET MARKETPLACE CLIENT
     */
    protected function getMarketplaceClient(string $marketplace)
    {
        return match ($marketplace) {
            'freemans' => new FreemansOperatorClient,
            'debenhams' => new \App\Services\Mirakl\Operators\DebenhamsOperatorClient,
            'bq' => new \App\Services\Mirakl\Operators\BqOperatorClient,
            default => throw new \InvalidArgumentException("Unknown marketplace: {$marketplace}"),
        };
    }
}
