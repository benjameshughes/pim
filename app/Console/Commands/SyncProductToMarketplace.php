<?php

namespace App\Console\Commands;

use App\Actions\Shopify\Sync\SimplifiedSyncProductToShopifyAction;
use App\Jobs\SyncProductToMarketplaceJob;
use App\Jobs\UpdateMarketplaceListingJob;
use App\Models\Product;
use App\Models\SyncAccount;
use Illuminate\Console\Command;

/**
 * 🚀 SYNC PRODUCT TO MARKETPLACE COMMAND
 *
 * Demonstrates how to reuse your existing Actions from anywhere in the app:
 * - Directly via Actions (synchronous)
 * - Via Jobs (asynchronous)
 * - With different options and configurations
 */
class SyncProductToMarketplace extends Command
{
    protected $signature = 'sync:product
                           {product : Product ID to sync}
                           {--channel=shopify : Marketplace channel (shopify, ebay, amazon, mirakl)}
                           {--account= : Specific sync account name}
                           {--async : Use job queue instead of direct sync}
                           {--update : Update existing listing instead of create/sync}
                           {--force : Force sync even if product appears up-to-date}
                           {--force-graphql : Force use of GraphQL API}
                           {--force-rest : Force use of REST API}';

    protected $description = 'Sync a product to marketplace using existing Actions - demonstrates code reusability';

    public function handle(): int
    {
        $productId = $this->argument('product');
        $channel = $this->option('channel');
        $accountName = $this->option('account');
        $useAsync = $this->option('async');
        $isUpdate = $this->option('update');
        $force = $this->option('force');
        $forceGraphQL = $this->option('force-graphql');
        $forceREST = $this->option('force-rest');

        // Find the product
        $product = Product::with(['variants', 'syncStatuses.syncAccount'])
            ->find($productId);

        if (! $product) {
            $this->error("❌ Product with ID {$productId} not found.");
            return 1;
        }

        // Find the sync account
        $syncAccount = $accountName
            ? SyncAccount::findByChannelAndName($channel, $accountName)
            : SyncAccount::getDefaultForChannel($channel);

        if (! $syncAccount) {
            $this->error("❌ No sync account found for channel '{$channel}'" . 
                        ($accountName ? " with name '{$accountName}'" : ''));
            return 1;
        }

        $this->info("🚀 Starting {$channel} sync for product: {$product->name}");
        $this->info("   Account: {$syncAccount->name}");
        $this->info("   Method: " . ($useAsync ? 'Async (Job Queue)' : 'Direct (Action)'));
        $this->info("   Type: " . ($isUpdate ? 'Update Listing' : 'Sync/Create'));
        $this->newLine();

        try {
            if ($useAsync) {
                // Use Jobs for async processing
                $result = $this->syncViaJobs($product, $syncAccount, $isUpdate, $force, $forceGraphQL, $forceREST);
            } else {
                // Use Actions directly for immediate processing
                $result = $this->syncViaActions($product, $syncAccount, $isUpdate, $force, $forceGraphQL, $forceREST);
            }

            if ($result['success']) {
                $this->info("✅ {$result['message']}");
                if (isset($result['details'])) {
                    $this->displaySyncDetails($result['details']);
                }
            } else {
                $this->error("❌ {$result['message']}");
            }

            return $result['success'] ? 0 : 1;

        } catch (\Exception $e) {
            $this->error("❌ Sync failed with exception: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Sync using Jobs (async)
     */
    protected function syncViaJobs(Product $product, SyncAccount $syncAccount, bool $isUpdate, bool $force, bool $forceGraphQL, bool $forceREST): array
    {
        $options = [
            'method' => 'cli',
            'force' => $force,
            'initiated_by' => 'sync_command',
            'force_graphql' => $forceGraphQL,
            'force_rest' => $forceREST,
        ];

        if ($isUpdate) {
            UpdateMarketplaceListingJob::dispatch($product, $syncAccount, $options);
            return [
                'success' => true,
                'message' => 'Update job dispatched to queue successfully',
                'details' => [
                    'job_type' => 'UpdateMarketplaceListingJob',
                    'queue' => "update-{$syncAccount->channel}",
                    'options' => $options,
                ],
            ];
        } else {
            SyncProductToMarketplaceJob::dispatch($product, $syncAccount, $options);
            return [
                'success' => true,
                'message' => 'Sync job dispatched to queue successfully',
                'details' => [
                    'job_type' => 'SyncProductToMarketplaceJob',
                    'queue' => "sync-{$syncAccount->channel}",
                    'options' => $options,
                ],
            ];
        }
    }

    /**
     * Sync using Actions directly (synchronous)
     */
    protected function syncViaActions(Product $product, SyncAccount $syncAccount, bool $isUpdate, bool $force, bool $forceGraphQL, bool $forceREST): array
    {
        $startTime = microtime(true);

        // Currently only Shopify is implemented
        if ($syncAccount->channel !== 'shopify') {
            return [
                'success' => false,
                'message' => "Direct sync for {$syncAccount->channel} not yet implemented. Use --async for job queue.",
            ];
        }

        // Use your existing Shopify Action directly
        $action = app(SimplifiedSyncProductToShopifyAction::class);

        $options = [
            'method' => 'cli_direct',
            'force' => $force,
            'sync_account_id' => $syncAccount->id,
            'initiated_by' => 'sync_command',
            'force_graphql' => $forceGraphQL,
            'force_rest' => $forceREST,
        ];

        $result = $action->execute($product, $options);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'success' => $result['success'],
            'message' => $result['message'] ?? ($result['success'] ? 'Sync completed successfully' : 'Sync failed'),
            'details' => array_merge($result['data'] ?? [], [
                'duration_ms' => $duration,
                'method' => 'direct_action',
                'action' => 'SimplifiedSyncProductToShopifyAction',
            ]),
        ];
    }

    /**
     * Display sync details in a nice format
     */
    protected function displaySyncDetails(array $details): void
    {
        $this->newLine();
        $this->line('<comment>📊 Sync Details:</comment>');

        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_PRETTY_PRINT);
            }
            $this->line("   <info>{$key}:</info> {$value}");
        }
    }
}