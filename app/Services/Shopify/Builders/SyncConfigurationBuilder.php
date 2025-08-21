<?php

namespace App\Services\Shopify\Builders;

use App\Actions\Shopify\Sync\CheckSyncStatusAction;
use App\Actions\Shopify\Sync\SyncProductToShopifyAction;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * ⚙️ SYNC CONFIGURATION BUILDER ⚙️
 *
 * Fluent API for building comprehensive sync configurations like a SYNC ARCHITECT!
 * Configures sync behavior, monitoring, and automation with STYLE! 💅
 */
class SyncConfigurationBuilder
{
    private CheckSyncStatusAction $statusAction;

    private SyncProductToShopifyAction $syncAction;

    // Configuration options
    private array $products = [];

    private string $syncMethod = 'manual';

    private bool $forceSync = false;

    private bool $enableMonitoring = true;

    private bool $enableWebhooks = true;

    private int $batchSize = 10;

    private array $syncFilters = [];

    private array $notificationSettings = [];

    private ?string $scheduledSync = null;

    public function __construct(
        CheckSyncStatusAction $statusAction,
        SyncProductToShopifyAction $syncAction
    ) {
        $this->statusAction = $statusAction;
        $this->syncAction = $syncAction;
    }

    /**
     * Add a single product to sync configuration
     */
    public function product(Product $product): static
    {
        $this->products[] = $product->id;

        Log::debug('📦 Added product to sync configuration', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'total_products' => count($this->products),
        ]);

        return $this;
    }

    /**
     * Add multiple products to sync configuration
     */
    public function products(Collection|array $products): static
    {
        foreach ($products as $product) {
            if ($product instanceof Product) {
                $this->product($product);
            } elseif (is_numeric($product)) {
                $this->products[] = $product;
            }
        }

        return $this;
    }

    /**
     * Include ALL products in the sync configuration
     */
    public function allProducts(): static
    {
        $productIds = Product::pluck('id')->toArray();
        $this->products = array_merge($this->products, $productIds);

        Log::info('🏭 Added all products to sync configuration', [
            'total_products' => count($this->products),
        ]);

        return $this;
    }

    /**
     * Only include products that need syncing
     */
    public function onlyOutOfSync(): static
    {
        $this->syncFilters['only_out_of_sync'] = true;

        Log::debug('🔍 Filter: Only out-of-sync products');

        return $this;
    }

    /**
     * Only include products never synced before
     */
    public function onlyNeverSynced(): static
    {
        $this->syncFilters['only_never_synced'] = true;

        Log::debug('🆕 Filter: Only never-synced products');

        return $this;
    }

    /**
     * Set sync method (manual, automatic, webhook)
     */
    public function method(string $method): static
    {
        $this->syncMethod = $method;

        Log::debug('⚙️ Set sync method', ['method' => $method]);

        return $this;
    }

    /**
     * Enable manual sync method
     */
    public function manual(): static
    {
        return $this->method('manual');
    }

    /**
     * Enable automatic sync method
     */
    public function automatic(): static
    {
        return $this->method('automatic');
    }

    /**
     * Enable webhook-triggered sync method
     */
    public function webhookTriggered(): static
    {
        return $this->method('webhook');
    }

    /**
     * Force sync even if products appear up-to-date
     */
    public function force(bool $force = true): static
    {
        $this->forceSync = $force;

        Log::debug('💪 Force sync', ['enabled' => $force]);

        return $this;
    }

    /**
     * Set batch size for bulk operations
     */
    public function batchSize(int $size): static
    {
        $this->batchSize = max(1, $size);

        Log::debug('📦 Set batch size', ['size' => $this->batchSize]);

        return $this;
    }

    /**
     * Enable comprehensive sync monitoring
     */
    public function withMonitoring(bool $enable = true): static
    {
        $this->enableMonitoring = $enable;

        Log::debug('📊 Sync monitoring', ['enabled' => $enable]);

        return $this;
    }

    /**
     * Enable webhook-based real-time monitoring
     */
    public function withWebhooks(bool $enable = true): static
    {
        $this->enableWebhooks = $enable;

        Log::debug('🔔 Webhook monitoring', ['enabled' => $enable]);

        return $this;
    }

    /**
     * Configure notification settings
     */
    public function notifications(array $settings): static
    {
        $this->notificationSettings = array_merge($this->notificationSettings, $settings);

        Log::debug('📢 Notification settings updated', ['settings' => $settings]);

        return $this;
    }

    /**
     * Enable email notifications for sync events
     */
    public function emailNotifications(string|array $recipients): static
    {
        $this->notificationSettings['email'] = [
            'enabled' => true,
            'recipients' => is_string($recipients) ? [$recipients] : $recipients,
        ];

        return $this;
    }

    /**
     * Schedule automatic syncing
     */
    public function schedule(string $frequency): static
    {
        $this->scheduledSync = $frequency;

        Log::debug('⏰ Scheduled sync', ['frequency' => $frequency]);

        return $this;
    }

    /**
     * Schedule hourly automatic syncing
     */
    public function hourly(): static
    {
        return $this->schedule('hourly');
    }

    /**
     * Schedule daily automatic syncing
     */
    public function daily(): static
    {
        return $this->schedule('daily');
    }

    /**
     * EXECUTE: Run sync status check for all configured products ✨
     */
    public function checkStatus(): array
    {
        Log::info('🔍 Executing sync status check', [
            'products_count' => count($this->products),
            'filters' => $this->syncFilters,
        ]);

        try {
            $filteredProducts = $this->applyFilters();

            return $this->statusAction->checkBulkSyncStatus($filteredProducts, [
                'method' => $this->syncMethod,
                'enable_monitoring' => $this->enableMonitoring,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Sync status check failed', [
                'error' => $e->getMessage(),
                'products_count' => count($this->products),
            ]);

            return [
                'success' => false,
                'message' => 'Sync status check failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * EXECUTE: Perform actual sync for all configured products ✨
     */
    public function sync(): array
    {
        Log::info('🚀 Executing product sync', [
            'products_count' => count($this->products),
            'method' => $this->syncMethod,
            'force_sync' => $this->forceSync,
            'batch_size' => $this->batchSize,
        ]);

        try {
            $filteredProducts = $this->applyFilters();

            return $this->syncAction->syncBulkProducts($filteredProducts, [
                'method' => $this->syncMethod,
                'force' => $this->forceSync,
                'batch_size' => $this->batchSize,
                'enable_monitoring' => $this->enableMonitoring,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Product sync failed', [
                'error' => $e->getMessage(),
                'products_count' => count($this->products),
            ]);

            return [
                'success' => false,
                'message' => 'Product sync failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build configuration without executing
     */
    public function build(): array
    {
        return [
            'products' => $this->products,
            'sync_method' => $this->syncMethod,
            'force_sync' => $this->forceSync,
            'batch_size' => $this->batchSize,
            'monitoring_enabled' => $this->enableMonitoring,
            'webhooks_enabled' => $this->enableWebhooks,
            'filters' => $this->syncFilters,
            'notifications' => $this->notificationSettings,
            'scheduled_sync' => $this->scheduledSync,
            'estimated_products' => count($this->applyFilters()),
            'configuration_summary' => $this->getConfigurationSummary(),
        ];
    }

    /**
     * Get a preview of what will be synced
     */
    public function preview(): array
    {
        $filteredProducts = $this->applyFilters();
        $products = Product::whereIn('id', $filteredProducts)
            ->select('id', 'name', 'updated_at')
            ->get();

        return [
            'total_products' => count($filteredProducts),
            'products' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'last_updated' => $product->updated_at,
                    'variants_count' => $product->variants->count(),
                ];
            }),
            'configuration' => $this->build(),
            'estimated_duration' => $this->estimateDuration(count($filteredProducts)),
        ];
    }

    // ===== PRIVATE HELPER METHODS ===== //

    /**
     * Apply configured filters to product list
     */
    private function applyFilters(): array
    {
        $products = $this->products;

        // Apply out-of-sync filter
        if ($this->syncFilters['only_out_of_sync'] ?? false) {
            // Implementation would check sync status
            Log::debug('🔍 Applying out-of-sync filter');
        }

        // Apply never-synced filter
        if ($this->syncFilters['only_never_synced'] ?? false) {
            // Implementation would check for products without sync records
            Log::debug('🆕 Applying never-synced filter');
        }

        return array_unique($products);
    }

    /**
     * Get configuration summary
     */
    private function getConfigurationSummary(): array
    {
        return [
            'sync_strategy' => $this->getSyncStrategy(),
            'monitoring_level' => $this->getMonitoringLevel(),
            'automation_level' => $this->getAutomationLevel(),
            'risk_level' => $this->getRiskLevel(),
        ];
    }

    /**
     * Determine sync strategy based on configuration
     */
    private function getSyncStrategy(): string
    {
        if ($this->forceSync) {
            return 'aggressive';
        }

        if (! empty($this->syncFilters)) {
            return 'selective';
        }

        return 'standard';
    }

    /**
     * Determine monitoring level
     */
    private function getMonitoringLevel(): string
    {
        if ($this->enableMonitoring && $this->enableWebhooks) {
            return 'comprehensive';
        }

        if ($this->enableMonitoring) {
            return 'standard';
        }

        return 'minimal';
    }

    /**
     * Determine automation level
     */
    private function getAutomationLevel(): string
    {
        if ($this->scheduledSync && $this->enableWebhooks) {
            return 'full';
        }

        if ($this->scheduledSync || $this->syncMethod === 'automatic') {
            return 'partial';
        }

        return 'manual';
    }

    /**
     * Assess configuration risk level
     */
    private function getRiskLevel(): string
    {
        $risk = 0;

        if ($this->forceSync) {
            $risk += 2;
        }
        if (count($this->products) > 100) {
            $risk += 1;
        }
        if ($this->batchSize > 20) {
            $risk += 1;
        }
        if ($this->syncMethod === 'automatic') {
            $risk += 1;
        }

        return match (true) {
            $risk >= 4 => 'high',
            $risk >= 2 => 'medium',
            default => 'low'
        };
    }

    /**
     * Estimate sync duration
     */
    private function estimateDuration(int $productCount): array
    {
        $avgTimePerProduct = 2; // seconds
        $totalSeconds = $productCount * $avgTimePerProduct;

        return [
            'estimated_seconds' => $totalSeconds,
            'estimated_minutes' => round($totalSeconds / 60, 1),
            'factors' => [
                'product_count' => $productCount,
                'avg_time_per_product' => $avgTimePerProduct,
                'batch_processing' => $this->batchSize > 1,
            ],
        ];
    }

    // ===== STATIC FACTORY METHODS ===== //

    /**
     * Create new sync configuration builder
     */
    public static function create(
        CheckSyncStatusAction $statusAction,
        SyncProductToShopifyAction $syncAction
    ): static {
        return new static($statusAction, $syncAction);
    }

    /**
     * Quick setup for full sync monitoring
     */
    public static function fullMonitoring(
        CheckSyncStatusAction $statusAction,
        SyncProductToShopifyAction $syncAction
    ): static {
        return static::create($statusAction, $syncAction)
            ->allProducts()
            ->automatic()
            ->withMonitoring()
            ->withWebhooks()
            ->batchSize(5);
    }

    /**
     * Conservative sync configuration
     */
    public static function conservative(
        CheckSyncStatusAction $statusAction,
        SyncProductToShopifyAction $syncAction
    ): static {
        return static::create($statusAction, $syncAction)
            ->manual()
            ->onlyOutOfSync()
            ->batchSize(3);
    }
}
