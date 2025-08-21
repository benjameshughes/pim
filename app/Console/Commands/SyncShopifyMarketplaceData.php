<?php

namespace App\Console\Commands;

use App\Models\MarketplaceLink;
use App\Models\Product;
use App\Models\SyncAccount;
use App\Services\Shopify\API\Client\ShopifyClient;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🛍️ SHOPIFY MARKETPLACE SYNC COMMAND
 *
 * Fetches all products and variants from Shopify API and creates/updates
 * MarketplaceLink records for SKU matching and hierarchy management.
 */
class SyncShopifyMarketplaceData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:sync-marketplace 
                           {--store=* : Specific Shopify store to sync}
                           {--incremental : Only sync products updated since last run}
                           {--dry-run : Show what would be synced without making changes}
                           {--batch-size=50 : Number of products to process per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all Shopify products and variants to MarketplaceLink records for SKU matching';

    protected ShopifyClient $shopifyClient;

    protected int $batchSize;

    protected bool $dryRun;

    protected bool $incremental;

    protected array $stats = [
        'products_processed' => 0,
        'variants_processed' => 0,
        'links_created' => 0,
        'links_updated' => 0,
        'errors' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🛍️ Starting Shopify Marketplace Data Sync...');

        try {
            $this->initializeParameters();
            $syncAccounts = $this->getSyncAccounts();

            if ($syncAccounts->isEmpty()) {
                $this->warn('No Shopify sync accounts found. Please configure Shopify integration first.');

                return Command::FAILURE;
            }

            foreach ($syncAccounts as $syncAccount) {
                $this->syncShopifyStore($syncAccount);
            }

            $this->displaySummary();

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("Sync failed: {$e->getMessage()}");
            Log::error('Shopify marketplace sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Initialize command parameters
     */
    protected function initializeParameters(): void
    {
        $this->batchSize = (int) $this->option('batch-size');
        $this->dryRun = $this->option('dry-run');
        $this->incremental = $this->option('incremental');

        if ($this->dryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
        }
    }

    /**
     * Get Shopify sync accounts to process
     */
    protected function getSyncAccounts(): \Illuminate\Database\Eloquent\Collection
    {
        $query = SyncAccount::where('channel', 'shopify')->where('is_active', true);

        if ($stores = $this->option('store')) {
            $query->whereIn('store_identifier', $stores);
        }

        return $query->get();
    }

    /**
     * Sync a single Shopify store
     */
    protected function syncShopifyStore(SyncAccount $syncAccount): void
    {
        $this->info("📦 Syncing store: {$syncAccount->display_name}");

        try {
            // Transform credentials to ShopifyClient format
            $shopifyConfig = $this->transformCredentials($syncAccount->credentials);
            $this->shopifyClient = new ShopifyClient($shopifyConfig);
            $lastSyncTime = $this->getLastSyncTime($syncAccount);

            $this->info('Fetching products from Shopify...');
            $products = $this->fetchShopifyProducts($lastSyncTime);

            $this->info("Processing {$products->count()} products...");
            $this->processProducts($products, $syncAccount);

            if (! $this->dryRun) {
                $this->updateLastSyncTime($syncAccount);
            }

        } catch (Exception $e) {
            $this->error("Failed to sync store {$syncAccount->display_name}: {$e->getMessage()}");
            $this->stats['errors']++;
        }
    }

    /**
     * Fetch products from Shopify API with variants
     */
    protected function fetchShopifyProducts(?\Carbon\Carbon $since = null): \Illuminate\Support\Collection
    {
        return $this->shopifyClient->fetchAllProductsWithVariants($since);
    }

    /**
     * Process products and create/update MarketplaceLink records
     */
    protected function processProducts(\Illuminate\Support\Collection $products, SyncAccount $syncAccount): void
    {
        $progressBar = $this->output->createProgressBar($products->count());
        $progressBar->start();

        foreach ($products->chunk($this->batchSize) as $batch) {
            DB::transaction(function () use ($batch, $syncAccount, $progressBar) {
                foreach ($batch as $shopifyProduct) {
                    $this->processProduct($shopifyProduct, $syncAccount);
                    $progressBar->advance();
                }
            });
        }

        $progressBar->finish();
        $this->newLine();
    }

    /**
     * Process a single product and its variants
     */
    protected function processProduct(array $shopifyProduct, SyncAccount $syncAccount): void
    {
        try {
            // Process product-level link
            $this->processProductLink($shopifyProduct, $syncAccount);
            $this->stats['products_processed']++;

            // Process variant-level links
            foreach ($shopifyProduct['variants'] ?? [] as $variant) {
                $this->processVariantLink($variant, $shopifyProduct, $syncAccount);
                $this->stats['variants_processed']++;
            }

        } catch (Exception $e) {
            $this->error("Error processing product {$shopifyProduct['id']}: {$e->getMessage()}");
            $this->stats['errors']++;
        }
    }

    /**
     * Process product-level MarketplaceLink
     */
    protected function processProductLink(array $shopifyProduct, SyncAccount $syncAccount): void
    {
        $linkData = [
            'sync_account_id' => $syncAccount->id,
            'external_product_id' => (string) $shopifyProduct['id'],
            'external_sku' => $shopifyProduct['handle'], // Shopify handle as external SKU
            'link_level' => 'product',
            'link_status' => 'pending', // Will be 'linked' when matched to internal product
            'marketplace_data' => [
                'title' => $shopifyProduct['title'],
                'status' => $shopifyProduct['status'],
                'product_type' => $shopifyProduct['product_type'] ?? null,
                'vendor' => $shopifyProduct['vendor'] ?? null,
                'created_at' => $shopifyProduct['created_at'],
                'updated_at' => $shopifyProduct['updated_at'],
            ],
        ];

        if ($this->dryRun) {
            $this->line("Would create/update product link: {$shopifyProduct['handle']}");

            return;
        }

        $link = MarketplaceLink::updateOrCreate(
            [
                'sync_account_id' => $syncAccount->id,
                'external_product_id' => (string) $shopifyProduct['id'],
                'link_level' => 'product',
            ],
            $linkData
        );

        $this->stats[$link->wasRecentlyCreated ? 'links_created' : 'links_updated']++;
    }

    /**
     * Process variant-level MarketplaceLink
     */
    protected function processVariantLink(array $variant, array $shopifyProduct, SyncAccount $syncAccount): void
    {
        // Find the parent product link
        $parentLink = MarketplaceLink::where('sync_account_id', $syncAccount->id)
            ->where('external_product_id', (string) $shopifyProduct['id'])
            ->where('link_level', 'product')
            ->first();

        $linkData = [
            'sync_account_id' => $syncAccount->id,
            'external_product_id' => (string) $shopifyProduct['id'],
            'external_variant_id' => (string) $variant['id'],
            'external_sku' => $variant['sku'] ?: "var-{$variant['id']}", // Use SKU or fallback
            'parent_link_id' => $parentLink?->id,
            'link_level' => 'variant',
            'link_status' => 'pending', // Will be 'linked' when matched to internal variant
            'marketplace_data' => [
                'title' => $variant['title'],
                'price' => $variant['price'],
                'inventory_quantity' => $variant['inventory_quantity'],
                'option1' => $variant['option1'] ?? null,
                'option2' => $variant['option2'] ?? null,
                'option3' => $variant['option3'] ?? null,
                'weight' => $variant['weight'] ?? null,
                'weight_unit' => $variant['weight_unit'] ?? null,
                'created_at' => $variant['created_at'],
                'updated_at' => $variant['updated_at'],
            ],
        ];

        if ($this->dryRun) {
            $this->line("Would create/update variant link: {$variant['sku']} (Product: {$shopifyProduct['handle']})");

            return;
        }

        $link = MarketplaceLink::updateOrCreate(
            [
                'sync_account_id' => $syncAccount->id,
                'external_variant_id' => (string) $variant['id'],
                'link_level' => 'variant',
            ],
            $linkData
        );

        $this->stats[$link->wasRecentlyCreated ? 'links_created' : 'links_updated']++;
    }

    /**
     * Get last sync time for incremental syncing
     */
    protected function getLastSyncTime(SyncAccount $syncAccount): ?\Carbon\Carbon
    {
        if (! $this->incremental) {
            return null;
        }

        // Get the most recent sync time from MarketplaceLink records
        $lastSync = MarketplaceLink::where('sync_account_id', $syncAccount->id)
            ->latest('updated_at')
            ->value('updated_at');

        return $lastSync ? \Carbon\Carbon::parse($lastSync) : null;
    }

    /**
     * Update last sync time
     */
    protected function updateLastSyncTime(SyncAccount $syncAccount): void
    {
        $syncAccount->update([
            'settings' => array_merge(
                $syncAccount->settings ?? [],
                ['last_marketplace_sync' => now()->toISOString()]
            ),
        ]);
    }

    /**
     * Transform SyncAccount credentials to ShopifyClient format
     */
    protected function transformCredentials(array $credentials): array
    {
        return [
            'ShopUrl' => $credentials['store_url'] ?? $credentials['shop_url'] ?? null,
            'AccessToken' => $credentials['access_token'] ?? null,
            'ApiVersion' => $credentials['api_version'] ?? '2024-07',
        ];
    }

    /**
     * Display sync summary
     */
    protected function displaySummary(): void
    {
        $this->newLine();
        $this->info('📊 Sync Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Products Processed', number_format($this->stats['products_processed'])],
                ['Variants Processed', number_format($this->stats['variants_processed'])],
                ['Links Created', number_format($this->stats['links_created'])],
                ['Links Updated', number_format($this->stats['links_updated'])],
                ['Errors', number_format($this->stats['errors'])],
            ]
        );

        if ($this->stats['errors'] === 0) {
            $this->info('✅ Sync completed successfully!');
        } else {
            $this->warn("⚠️  Sync completed with {$this->stats['errors']} errors. Check logs for details.");
        }
    }
}
