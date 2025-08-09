<?php

namespace App\Console\Commands;

use App\Models\ShopifyTaxonomyCategory;
use App\Services\ShopifyConnectService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncShopifyTaxonomy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:sync-taxonomy {--force : Force re-sync even if data exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Shopify taxonomy categories and attributes to local database';

    private ShopifyConnectService $shopifyService;

    public function __construct(ShopifyConnectService $shopifyService)
    {
        parent::__construct();
        $this->shopifyService = $shopifyService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🛍️  Syncing Shopify taxonomy...');

        // Check if we should skip if data already exists
        if (! $this->option('force') && ShopifyTaxonomyCategory::count() > 0) {
            $this->info('Taxonomy data already exists. Use --force to re-sync.');

            return 0;
        }

        if ($this->option('force')) {
            $this->warn('Clearing existing taxonomy data...');
            ShopifyTaxonomyCategory::truncate();
        }

        try {
            // Use the ULTIMATE SASSY HIERARCHY method to get ALL categories AND subcategories!
            $this->syncCompleteTaxonomyHierarchy(250);

            $totalCategories = ShopifyTaxonomyCategory::count();
            $this->info("✅ Successfully synced {$totalCategories} taxonomy categories");

            // Show some statistics
            $this->showStatistics();

            // Find and display blind/window treatment categories
            $this->findBlindCategories();

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to sync taxonomy: {$e->getMessage()}");
            Log::error('Shopify taxonomy sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Sync the COMPLETE taxonomy hierarchy with ALL subcategories (ULTIMATE VERSION)
     */
    private function syncCompleteTaxonomyHierarchy(int $batchSize): void
    {
        $this->info("🌟 Fetching COMPLETE taxonomy hierarchy with ALL subcategories...");

        $response = $this->shopifyService->getCompleteTaxonomyHierarchy($batchSize);

        if (! $response['success']) {
            throw new \Exception('Failed to fetch complete taxonomy hierarchy: '.$response['error']);
        }

        $categories = $response['data']['taxonomy']['categories']['edges'] ?? [];
        $totalCategories = count($categories);
        $requestsMade = $response['requests_made'] ?? 1;
        $childCategories = $response['child_categories'] ?? 0;
        
        $this->info("🎊 Retrieved {$totalCategories} total categories ({$childCategories} subcategories) in {$requestsMade} requests!");
        $this->info('🔄 Processing all categories...');

        $bar = $this->output->createProgressBar($totalCategories);
        $bar->start();

        DB::beginTransaction();

        try {
            $processed = 0;
            foreach ($categories as $categoryEdge) {
                $node = $categoryEdge['node'];

                ShopifyTaxonomyCategory::updateOrCreate(
                    ['shopify_id' => $node['id']],
                    [
                        'name' => $node['name'],
                        'full_name' => $node['fullName'],
                        'level' => $node['level'],
                        'is_leaf' => $node['isLeaf'],
                        'is_root' => $node['level'] === 0,
                        'parent_id' => $node['parentId'],
                        'children_ids' => $node['childrenIds'] ?? [],
                        'ancestor_ids' => [], // Will be populated in second pass
                        'attributes' => [], // Will be populated separately
                    ]
                );

                $processed++;
                $bar->advance();
            }

            DB::commit();
            $bar->finish();
            $this->newLine();
            
            $this->info("✨ Processed {$processed} categories successfully!");

        } catch (\Exception $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine();
            throw $e;
        }

        // Second pass: populate ancestor relationships
        $this->populateAncestors();
    }

    /**
     * Sync ALL taxonomy categories using enhanced pagination (SASSY VERSION)
     */
    private function syncAllTaxonomyCategories(int $batchSize): void
    {
        $this->info("🚀 Fetching ALL taxonomy categories with enhanced pagination...");

        $response = $this->shopifyService->getAllTaxonomyCategories($batchSize);

        if (! $response['success']) {
            throw new \Exception('Failed to fetch taxonomy: '.$response['error']);
        }

        $categories = $response['data']['taxonomy']['categories']['edges'] ?? [];
        $totalCategories = count($categories);
        $requestsMade = $response['requests_made'] ?? 1;
        
        $this->info("💎 Retrieved {$totalCategories} categories in {$requestsMade} paginated requests!");
        $this->info('🔄 Processing categories...');

        $bar = $this->output->createProgressBar($totalCategories);
        $bar->start();

        DB::beginTransaction();

        try {
            $processed = 0;
            foreach ($categories as $categoryEdge) {
                $node = $categoryEdge['node'];

                ShopifyTaxonomyCategory::updateOrCreate(
                    ['shopify_id' => $node['id']],
                    [
                        'name' => $node['name'],
                        'full_name' => $node['fullName'],
                        'level' => $node['level'],
                        'is_leaf' => $node['isLeaf'],
                        'is_root' => $node['level'] === 0,
                        'parent_id' => $node['parentId'],
                        'children_ids' => $node['childrenIds'] ?? [],
                        'ancestor_ids' => [], // Will be populated in second pass
                        'attributes' => [], // Will be populated separately
                    ]
                );

                $processed++;
                $bar->advance();
            }

            DB::commit();
            $bar->finish();
            $this->newLine();
            
            $this->info("✨ Processed {$processed} categories successfully!");

        } catch (\Exception $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine();
            throw $e;
        }

        // Second pass: populate ancestor relationships
        $this->populateAncestors();
    }

    /**
     * Legacy sync method for backwards compatibility
     */
    private function syncTaxonomyBatch(int $batchSize): void
    {
        $this->warn("⚠️ Using legacy sync method - consider using syncAllTaxonomyCategories instead");
        
        $this->info("Fetching taxonomy categories (batch size: {$batchSize})...");

        $response = $this->shopifyService->getTaxonomyCategories($batchSize);

        if (! $response['success']) {
            throw new \Exception('Failed to fetch taxonomy: '.$response['error']);
        }

        $categories = $response['data']['taxonomy']['categories']['edges'] ?? [];
        $this->info('Processing '.count($categories).' categories...');

        $bar = $this->output->createProgressBar(count($categories));
        $bar->start();

        DB::beginTransaction();

        try {
            foreach ($categories as $categoryEdge) {
                $node = $categoryEdge['node'];

                ShopifyTaxonomyCategory::updateOrCreate(
                    ['shopify_id' => $node['id']],
                    [
                        'name' => $node['name'],
                        'full_name' => $node['fullName'],
                        'level' => $node['level'],
                        'is_leaf' => $node['isLeaf'],
                        'is_root' => $node['level'] === 0,
                        'parent_id' => $node['parentId'],
                        'children_ids' => $node['childrenIds'] ?? [],
                        'ancestor_ids' => [], // Will be populated in second pass
                        'attributes' => [], // Will be populated separately
                    ]
                );

                $bar->advance();
            }

            DB::commit();
            $bar->finish();
            $this->newLine();

        } catch (\Exception $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine();
            throw $e;
        }

        // Second pass: populate ancestor relationships
        $this->populateAncestors();
    }

    /**
     * Populate ancestor IDs for hierarchical relationships
     */
    private function populateAncestors(): void
    {
        $this->info('Building category hierarchy...');

        $categories = ShopifyTaxonomyCategory::all();

        foreach ($categories as $category) {
            $ancestors = [];
            $current = $category;

            // Walk up the tree to collect ancestors
            while ($current->parent_id) {
                $parent = $categories->where('shopify_id', $current->parent_id)->first();
                if ($parent) {
                    $ancestors[] = $parent->shopify_id;
                    $current = $parent;
                } else {
                    break;
                }
            }

            $category->update(['ancestor_ids' => array_reverse($ancestors)]);
        }
    }

    /**
     * Show taxonomy statistics
     */
    private function showStatistics(): void
    {
        $this->newLine();
        $this->info('📊 Taxonomy Statistics:');

        $rootCount = ShopifyTaxonomyCategory::roots()->count();
        $leafCount = ShopifyTaxonomyCategory::leaves()->count();
        $maxLevel = ShopifyTaxonomyCategory::max('level');

        $this->line("   Root categories: {$rootCount}");
        $this->line("   Leaf categories: {$leafCount}");
        $this->line("   Maximum depth: {$maxLevel}");

        // Show root categories
        $this->newLine();
        $this->info('🗂️  Root Categories:');
        $roots = ShopifyTaxonomyCategory::roots()->orderBy('name')->get();
        foreach ($roots as $root) {
            $this->line("   • {$root->name}");
        }
    }

    /**
     * Find and display blind/window treatment categories
     */
    private function findBlindCategories(): void
    {
        $this->newLine();
        $this->info('🪟 Window Treatment Categories:');

        $keywords = ['blind', 'shade', 'window', 'treatment', 'curtain'];
        $blindCategories = ShopifyTaxonomyCategory::findByKeywords($keywords);

        if ($blindCategories->isEmpty()) {
            $this->warn('   No window treatment categories found');

            return;
        }

        foreach ($blindCategories as $category) {
            $marker = $category->is_leaf ? '🎯' : '📁';
            $this->line("   {$marker} {$category->shopify_id} - {$category->full_name}");
        }

        // Show the best match for our products
        $this->newLine();
        $this->info('🎯 Best Matches for Our Products:');
        $testProducts = ['Blackout Blind', 'Roller Blind', 'Venetian Blind', 'Day Night Blind'];

        foreach ($testProducts as $productName) {
            $match = ShopifyTaxonomyCategory::getBestMatchForProduct($productName);
            if ($match) {
                $this->line("   • {$productName} → {$match->full_name}");
            } else {
                $this->line("   • {$productName} → No match found");
            }
        }
    }
}
