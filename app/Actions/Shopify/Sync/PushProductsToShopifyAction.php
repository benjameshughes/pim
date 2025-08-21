<?php

namespace App\Actions\Shopify\Sync;

use App\Actions\API\Shopify\BuildShopifyProductData;
use App\Actions\Base\BaseAction;
use App\Models\Product;
use App\Models\ShopifySyncStatus;
use App\Services\ShopifyConnectService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 🚀💅 PUSH PRODUCTS TO SHOPIFY ACTION - SENDING OUR BEAUTIES TO STARDOM! 💅🚀
 *
 * This action is serving EXPORT EXCELLENCE! We're not just pushing products,
 * we're launching a couture collection into the Shopify stratosphere! ✨
 */
class PushProductsToShopifyAction extends BaseAction
{
    protected ShopifyConnectService $shopifyService;

    protected BuildShopifyProductData $buildDataAction;

    public function __construct(
        ShopifyConnectService $shopifyService,
        BuildShopifyProductData $buildDataAction
    ) {
        parent::__construct();
        $this->shopifyService = $shopifyService;
        $this->buildDataAction = $buildDataAction;
    }

    /**
     * Validate parameters before execution
     */
    protected function validate(array $params): bool
    {
        if (count($params) < 1 || ! is_array($params[0])) {
            throw new \InvalidArgumentException('First parameter must be an array of product IDs');
        }

        return true;
    }

    /**
     * 🎭 PERFORM ACTION - The grand performance!
     *
     * @param  array  $productIds  Products to export
     * @param  bool  $dryRun  Preview mode
     * @param  array  $options  Additional configuration
     * @return array Results with sass and statistics
     */
    protected function performAction(...$params): array
    {
        $productIds = $params[0];
        $dryRun = $params[1] ?? false;
        $options = $params[2] ?? [];

        Log::info('🎪 Starting Shopify product push operation', [
            'product_count' => count($productIds),
            'dry_run' => $dryRun,
            'options' => $options,
        ]);

        // 📋 Load products with all the necessary relationships
        $products = $this->loadProducts($productIds);

        if ($products->isEmpty()) {
            return $this->success('No products found to export! Time to create some fabulous ones! 💫', [
                'total' => 0,
                'successful' => 0,
                'failed' => 0,
                'errors' => [],
            ]);
        }

        // 🎯 Filter to only exportable products
        $exportableProducts = $products->syncable();

        if ($exportableProducts->isEmpty()) {
            return $this->success('No products are ready for their Shopify debut! ✨', [
                'total' => $products->count(),
                'successful' => 0,
                'failed' => $products->count(),
                'errors' => ['Products missing required data (variants, pricing, barcodes)'],
            ]);
        }

        // 🚀 Process each product for export
        $results = $this->processProductExports($exportableProducts, $dryRun, $options);

        return $this->success(
            $dryRun
                ? "Would export {$exportableProducts->count()} gorgeous products! ✨"
                : "Exported {$results['successful']} products to Shopify! 🎉",
            [
                'total' => $exportableProducts->count(),
                'successful' => $results['successful'],
                'failed' => $results['failed'],
                'errors' => $results['errors'],
            ]
        );
    }

    /**
     * 📋 LOAD PRODUCTS - Get products with all their fabulous details
     */
    protected function loadProducts(array $productIds): Collection
    {
        return Product::whereIn('id', $productIds)
            ->with([
                'variants.barcodes',
                'variants.pricing',
                'variants.attributes',
                'shopifySyncStatus',
            ])
            ->get();
    }

    /**
     * 🎪 PROCESS PRODUCT EXPORTS - The main show!
     */
    protected function processProductExports(Collection $products, bool $dryRun, array $options): array
    {
        $results = [
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($products as $product) {
            try {
                $result = $this->exportSingleProduct($product, $dryRun, $options);

                if ($result['success']) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Product {$product->name}: ".
                        implode(', ', $result['errors']);
                }

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Product {$product->name}: {$e->getMessage()}";

                Log::warning('Product export failed', [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * 🌟 EXPORT SINGLE PRODUCT - Individual star treatment!
     */
    protected function exportSingleProduct(Product $product, bool $dryRun, array $options): array
    {
        // 🔍 Check if product already exists on Shopify
        $existingShopifyId = $this->getExistingShopifyId($product);
        $isUpdate = $existingShopifyId !== null;

        // 🎨 Build Shopify product data
        $shopifyData = $this->buildDataAction->execute($product, $options);

        if ($dryRun) {
            return [
                'success' => true,
                'action' => $isUpdate ? 'would_update' : 'would_create',
                'shopify_id' => $existingShopifyId,
                'data' => $shopifyData,
            ];
        }

        // 🚀 Send to Shopify API
        if ($isUpdate) {
            $response = $this->updateShopifyProduct($existingShopifyId, $shopifyData);
        } else {
            $response = $this->createShopifyProduct($shopifyData);
        }

        if (! $response || ! isset($response['product'])) {
            throw new \Exception('Invalid response from Shopify API');
        }

        // 📊 Update sync status
        $this->updateSyncStatus($product, $response['product'], $isUpdate);

        Log::info('🎉 Product exported to Shopify', [
            'product_id' => $product->id,
            'shopify_id' => $response['product']['id'],
            'action' => $isUpdate ? 'updated' : 'created',
            'title' => $response['product']['title'],
        ]);

        return [
            'success' => true,
            'action' => $isUpdate ? 'updated' : 'created',
            'shopify_id' => $response['product']['id'],
            'shopify_data' => $response['product'],
        ];
    }

    /**
     * 🔍 GET EXISTING SHOPIFY ID - Check if product already exists
     */
    protected function getExistingShopifyId(Product $product): ?string
    {
        // Check metadata first
        $metadata = $product->metadata ?? [];
        if (isset($metadata['shopify_id'])) {
            return $metadata['shopify_id'];
        }

        // Check sync status
        $syncStatus = $product->shopifySyncStatus()
            ->where('sync_status', 'synced')
            ->first();

        return $syncStatus?->external_id;
    }

    /**
     * ✨ CREATE SHOPIFY PRODUCT - Birth of a star!
     */
    protected function createShopifyProduct(array $productData): ?array
    {
        return $this->shopifyService->createProduct($productData);
    }

    /**
     * 🔄 UPDATE SHOPIFY PRODUCT - Refresh the look!
     */
    protected function updateShopifyProduct(string $shopifyId, array $productData): ?array
    {
        return $this->shopifyService->updateProduct($shopifyId, $productData);
    }

    /**
     * 📊 UPDATE SYNC STATUS - Keep track of our stars!
     */
    protected function updateSyncStatus(Product $product, array $shopifyProduct, bool $wasUpdate): void
    {
        // Update product metadata
        $metadata = $product->metadata ?? [];
        $metadata['shopify_id'] = $shopifyProduct['id'];
        $metadata['shopify_handle'] = $shopifyProduct['handle'];
        $metadata['last_synced_at'] = now()->toISOString();

        $product->update(['metadata' => $metadata]);

        // Create or update sync status
        ShopifySyncStatus::updateOrCreate(
            [
                'product_id' => $product->id,
                'external_type' => 'shopify',
                'external_id' => $shopifyProduct['id'],
            ],
            [
                'sync_status' => 'synced',
                'last_synced_at' => now(),
                'sync_data' => [
                    'action' => $wasUpdate ? 'updated' : 'created',
                    'title' => $shopifyProduct['title'],
                    'handle' => $shopifyProduct['handle'],
                    'status' => $shopifyProduct['status'],
                    'variants_count' => count($shopifyProduct['variants'] ?? []),
                ],
                'error_message' => null,
            ]
        );
    }

    /**
     * 🔄 BULK UPDATE EXISTING - Refresh multiple products at once
     */
    public function bulkUpdateExisting(array $productIds, bool $dryRun = false): array
    {
        $products = Product::whereIn('id', $productIds)
            ->whereHas('shopifySyncStatus', function ($q) {
                $q->where('sync_status', 'synced')
                    ->whereNotNull('external_id');
            })
            ->with(['variants.barcodes', 'variants.pricing', 'shopifySyncStatus'])
            ->get();

        if ($products->isEmpty()) {
            return $this->success('No synced products found to update! 💫', [
                'total' => 0,
                'updated' => 0,
            ]);
        }

        return $this->execute($products->pluck('id')->toArray(), $dryRun, ['force_update' => true]);
    }

    /**
     * 🎯 SYNC SPECIFIC PRODUCT - Single product VIP treatment
     */
    public function syncSingle(int $productId, bool $dryRun = false, array $options = []): array
    {
        return $this->execute([$productId], $dryRun, $options);
    }
}
