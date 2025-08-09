<?php

namespace App\Jobs;

use App\Models\ShopifyWebhookLog;
use App\Models\Product;
use App\Models\ShopifyProductSync;
use App\Services\Shopify\API\ShopifySyncStatusService;
use App\Services\Shopify\API\ShopifyDataComparatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * 🎭 LEGENDARY SHOPIFY WEBHOOK PROCESSOR 🎭
 * 
 * Processes Shopify webhooks with MAXIMUM SASS and intelligence!
 * Because webhook processing should be as smooth as my dance moves! 💅
 */
class ProcessShopifyWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;
    public $maxExceptions = 2;

    public function __construct(
        public string $webhookLogId,
        public string $topic,
        public array $data,
        public array $metadata = []
    ) {
        // Set queue based on topic priority
        $this->queue = $this->getQueueForTopic($topic);
    }

    /**
     * 🚀 LEGENDARY webhook processing - where the magic happens!
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        $webhookId = $this->metadata['webhook_id'] ?? 'unknown';
        
        Log::info("🎭 Processing LEGENDARY webhook", [
            'webhook_id' => $webhookId,
            'topic' => $this->topic,
            'webhook_log_id' => $this->webhookLogId,
            'attempt' => $this->attempts()
        ]);

        try {
            $webhookLog = ShopifyWebhookLog::findOrFail($this->webhookLogId);
            $webhookLog->update(['status' => 'processing']);

            // 🎪 Route to appropriate handler based on topic
            $result = match($this->topic) {
                'products/create' => $this->handleProductCreated(),
                'products/update' => $this->handleProductUpdated(),
                'products/delete' => $this->handleProductDeleted(),
                'inventory_levels/update' => $this->handleInventoryUpdated(),
                'inventory_levels/connect' => $this->handleInventoryConnected(),
                'inventory_levels/disconnect' => $this->handleInventoryDisconnected(),
                'orders/create' => $this->handleOrderCreated(),
                'orders/updated' => $this->handleOrderUpdated(),
                'app/uninstalled' => $this->handleAppUninstalled(),
                default => $this->handleUnknownTopic()
            };

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            // 📊 Update webhook log with LEGENDARY results
            $webhookLog->update([
                'status' => 'completed',
                'processed_at' => now(),
                'metadata->processing_result' => $result,
                'metadata->processing_time_ms' => $processingTime,
                'metadata->attempts' => $this->attempts()
            ]);

            Log::info("✨ Webhook processed with LEGENDARY success!", [
                'webhook_id' => $webhookId,
                'topic' => $this->topic,
                'processing_time_ms' => $processingTime,
                'result' => $result['action'] ?? 'unknown'
            ]);

        } catch (\Exception $e) {
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error("💥 Webhook processing DRAMA occurred!", [
                'webhook_id' => $webhookId,
                'topic' => $this->topic,
                'error' => $e->getMessage(),
                'processing_time_ms' => $processingTime,
                'attempt' => $this->attempts()
            ]);

            // Update webhook log with error
            if (isset($webhookLog)) {
                $webhookLog->update([
                    'status' => 'failed',
                    'metadata->error' => $e->getMessage(),
                    'metadata->processing_time_ms' => $processingTime,
                    'metadata->attempts' => $this->attempts()
                ]);
            }

            throw $e;
        }
    }

    /**
     * 🆕 Handle product creation webhook
     */
    private function handleProductCreated(): array
    {
        $shopifyProductId = $this->data['id'] ?? null;
        $title = $this->data['title'] ?? 'Unknown Product';
        
        Log::info("🆕 Product created in Shopify", [
            'shopify_product_id' => $shopifyProductId,
            'title' => $title
        ]);

        // Try to find matching local product
        $localProduct = $this->findLocalProductBySku($this->data);
        
        if ($localProduct) {
            // Update sync record
            $syncRecord = ShopifyProductSync::updateOrCreate([
                'product_id' => $localProduct->id,
                'color' => $this->extractColor($this->data)
            ], [
                'shopify_product_id' => $shopifyProductId,
                'sync_status' => 'synced',
                'last_synced_at' => now(),
                'sync_method' => 'webhook_created',
                'last_sync_data' => $this->data
            ]);

            return [
                'action' => 'sync_record_updated',
                'local_product_id' => $localProduct->id,
                'sync_record_id' => $syncRecord->id
            ];
        }

        return [
            'action' => 'no_local_match',
            'shopify_product_id' => $shopifyProductId,
            'message' => 'No matching local product found'
        ];
    }

    /**
     * ✏️ Handle product update webhook
     */
    private function handleProductUpdated(): array
    {
        $shopifyProductId = $this->data['id'] ?? null;
        $title = $this->data['title'] ?? 'Unknown Product';

        Log::info("✏️ Product updated in Shopify", [
            'shopify_product_id' => $shopifyProductId,
            'title' => $title
        ]);

        // Find existing sync record
        $syncRecord = ShopifyProductSync::where('shopify_product_id', $shopifyProductId)->first();
        
        if ($syncRecord) {
            // Calculate data drift using comparator service
            $comparator = app(ShopifyDataComparatorService::class);
            $product = Product::find($syncRecord->product_id);
            
            if ($product) {
                $comparison = $comparator->compareProductData($product, $this->data);
                
                $syncRecord->update([
                    'last_synced_at' => now(),
                    'last_sync_data' => $this->data,
                    'data_drift_score' => $comparison['drift_score'],
                    'sync_method' => 'webhook_updated',
                    'metadata->last_comparison' => $comparison
                ]);

                return [
                    'action' => 'sync_record_updated',
                    'product_id' => $product->id,
                    'drift_score' => $comparison['drift_score'],
                    'needs_sync' => $comparison['needs_sync']
                ];
            }
        }

        return [
            'action' => 'sync_record_not_found',
            'shopify_product_id' => $shopifyProductId
        ];
    }

    /**
     * 🗑️ Handle product deletion webhook
     */
    private function handleProductDeleted(): array
    {
        $shopifyProductId = $this->data['id'] ?? null;

        Log::info("🗑️ Product deleted in Shopify", [
            'shopify_product_id' => $shopifyProductId
        ]);

        // Update sync records
        $updatedRecords = ShopifyProductSync::where('shopify_product_id', $shopifyProductId)
            ->update([
                'sync_status' => 'deleted_in_shopify',
                'last_synced_at' => now(),
                'sync_method' => 'webhook_deleted',
                'metadata->deleted_at' => now()->toISOString()
            ]);

        return [
            'action' => 'marked_as_deleted',
            'shopify_product_id' => $shopifyProductId,
            'updated_records' => $updatedRecords
        ];
    }

    /**
     * 📦 Handle inventory update webhook
     */
    private function handleInventoryUpdated(): array
    {
        $inventoryItemId = $this->data['inventory_item_id'] ?? null;
        $available = $this->data['available'] ?? 0;

        Log::info("📦 Inventory updated in Shopify", [
            'inventory_item_id' => $inventoryItemId,
            'available' => $available
        ]);

        // Find sync records with matching inventory
        // This would need to be enhanced based on your inventory tracking needs
        
        return [
            'action' => 'inventory_noted',
            'inventory_item_id' => $inventoryItemId,
            'available' => $available
        ];
    }

    /**
     * 🔗 Handle inventory connection webhook
     */
    private function handleInventoryConnected(): array
    {
        return ['action' => 'inventory_connected', 'data' => $this->data];
    }

    /**
     * 🔌 Handle inventory disconnection webhook
     */
    private function handleInventoryDisconnected(): array
    {
        return ['action' => 'inventory_disconnected', 'data' => $this->data];
    }

    /**
     * 🛍️ Handle order creation webhook
     */
    private function handleOrderCreated(): array
    {
        $orderId = $this->data['id'] ?? null;
        $orderNumber = $this->data['order_number'] ?? null;

        Log::info("🛍️ Order created in Shopify", [
            'order_id' => $orderId,
            'order_number' => $orderNumber
        ]);

        // This could trigger inventory updates, analytics, etc.
        return [
            'action' => 'order_noted',
            'order_id' => $orderId,
            'order_number' => $orderNumber
        ];
    }

    /**
     * ✏️ Handle order update webhook
     */
    private function handleOrderUpdated(): array
    {
        $orderId = $this->data['id'] ?? null;
        
        return [
            'action' => 'order_update_noted',
            'order_id' => $orderId
        ];
    }

    /**
     * 💔 Handle app uninstalled webhook
     */
    private function handleAppUninstalled(): array
    {
        $shopDomain = $this->metadata['shop_domain'] ?? 'unknown';
        
        Log::warning("💔 App uninstalled from Shopify store", [
            'shop_domain' => $shopDomain
        ]);

        // Could trigger cleanup, analytics, etc.
        return [
            'action' => 'app_uninstalled',
            'shop_domain' => $shopDomain,
            'uninstalled_at' => now()->toISOString()
        ];
    }

    /**
     * ❓ Handle unknown webhook topics
     */
    private function handleUnknownTopic(): array
    {
        Log::warning("❓ Unknown webhook topic received", [
            'topic' => $this->topic,
            'data_keys' => array_keys($this->data)
        ]);

        return [
            'action' => 'unknown_topic',
            'topic' => $this->topic,
            'message' => 'Topic not recognized but logged for analysis'
        ];
    }

    /**
     * 🔍 Find local product by SKU matching
     */
    private function findLocalProductBySku(array $data): ?Product
    {
        $variants = $data['variants'] ?? [];
        
        foreach ($variants as $variant) {
            $sku = $variant['sku'] ?? null;
            if ($sku) {
                $product = Product::whereHas('variants', function($query) use ($sku) {
                    $query->where('sku', $sku);
                })->first();
                
                if ($product) {
                    return $product;
                }
            }
        }

        return null;
    }

    /**
     * 🎨 Extract color from product data
     */
    private function extractColor(array $data): string
    {
        $variants = $data['variants'] ?? [];
        
        foreach ($variants as $variant) {
            foreach (['option1', 'option2', 'option3'] as $option) {
                $value = $variant[$option] ?? null;
                if ($value && $this->looksLikeColor($value)) {
                    return strtolower($value);
                }
            }
        }

        return 'default';
    }

    /**
     * 🎨 Check if a value looks like a color
     */
    private function looksLikeColor(string $value): bool
    {
        $colors = ['red', 'blue', 'green', 'yellow', 'black', 'white', 'gray', 'pink', 'purple', 'orange', 'brown'];
        return in_array(strtolower($value), $colors);
    }

    /**
     * 🚦 Get appropriate queue for webhook topic
     */
    private function getQueueForTopic(string $topic): string
    {
        return match(true) {
            str_contains($topic, 'products/') => 'shopify-products',
            str_contains($topic, 'inventory_') => 'shopify-inventory', 
            str_contains($topic, 'orders/') => 'shopify-orders',
            default => 'shopify-webhooks'
        };
    }

    /**
     * 💥 Handle job failure with LEGENDARY grace
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("💥 LEGENDARY webhook job failed completely!", [
            'webhook_log_id' => $this->webhookLogId,
            'topic' => $this->topic,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Update webhook log as permanently failed
        try {
            ShopifyWebhookLog::where('id', $this->webhookLogId)->update([
                'status' => 'permanent_failure',
                'metadata->final_error' => $exception->getMessage(),
                'metadata->failed_at' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update webhook log on permanent failure", [
                'webhook_log_id' => $this->webhookLogId,
                'error' => $e->getMessage()
            ]);
        }
    }
}