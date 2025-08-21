<?php

namespace App\Actions\Shopify\Sync;

use App\Actions\API\Shopify\ImportShopifyProduct;
use App\Actions\Base\BaseAction;
use App\Models\Product;
use App\Services\ShopifyConnectService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 📥💅 PULL SHOPIFY PRODUCTS ACTION - BRINGING THE EXTERNAL BEAUTIES HOME! 💅📥
 *
 * This action is serving MAJOR import energy! We're not just pulling products,
 * we're curating a collection of Shopify masterpieces! ✨
 */
class PullShopifyProductsAction extends BaseAction
{
    protected ShopifyConnectService $shopifyService;

    protected ImportShopifyProduct $importAction;

    private float $startTime;

    private float $executionTime;

    public function __construct(
        ShopifyConnectService $shopifyService,
        ImportShopifyProduct $importAction
    ) {
        parent::__construct();
        $this->shopifyService = $shopifyService;
        $this->importAction = $importAction;
    }

    /**
     * 🎭 PERFORM ACTION - The main performance!
     *
     * @return array Results with sass and statistics
     */
    protected function performAction(...$params): array
    {
        $this->startTiming();

        // Extract options from variadic params
        $options = $params[0] ?? [];

        try {
            // Extract options with defaults (because we have STANDARDS!)
            $filters = $options['filters'] ?? [];
            $limit = $options['limit'] ?? 50;
            $dryRun = $options['dry_run'] ?? false;
            $linkExisting = $options['link_existing'] ?? true;
            $matchingRules = $options['matching_rules'] ?? ['sku', 'barcode'];

            Log::info('🎪 Starting Shopify product pull operation', [
                'filters' => $filters,
                'limit' => $limit,
                'dry_run' => $dryRun,
                'link_existing' => $linkExisting,
            ]);

            // 🔍 Fetch products from Shopify
            $shopifyProducts = $this->fetchShopifyProducts($filters, $limit);

            if ($shopifyProducts->isEmpty()) {
                return $this->success([
                    'message' => 'No Shopify products found matching your exquisite criteria! 💫',
                    'total' => 0,
                    'imported' => 0,
                    'linked' => 0,
                    'skipped' => 0,
                    'errors' => [],
                ]);
            }

            // 🔗 Process each product (import or link)
            $results = $this->processProducts(
                $shopifyProducts,
                $dryRun,
                $linkExisting,
                $matchingRules
            );

            $this->stopTiming();

            return [
                'success' => true,
                'message' => $dryRun
                    ? "Would process {$shopifyProducts->count()} fabulous Shopify products! ✨"
                    : 'Successfully processed '.($results['imported'] + $results['linked']).' products! 🎉',
                'total' => $shopifyProducts->count(),
                'imported' => $results['imported'],
                'linked' => $results['linked'],
                'skipped' => $results['skipped'],
                'errors' => $results['errors'],
                'execution_time' => $this->getExecutionTime(),
            ];

        } catch (\Exception $e) {
            $this->stopTiming();

            Log::error('💔 Shopify product pull failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'execution_time' => $this->getExecutionTime(),
            ];
        }
    }

    /**
     * 🌟 FETCH SHOPIFY PRODUCTS - Get the external beauties
     */
    protected function fetchShopifyProducts(array $filters, int $limit): Collection
    {
        // ShopifyConnectService expects limit as first param, pageInfo as second
        $response = $this->shopifyService->getProducts($limit);

        if (! $response || ! isset($response['data']['products'])) {
            throw new \Exception('Failed to fetch products from Shopify API 💔');
        }

        return collect($response['data']['products']);
    }

    /**
     * 🎪 PROCESS PRODUCTS - The main show!
     */
    protected function processProducts(
        Collection $shopifyProducts,
        bool $dryRun,
        bool $linkExisting,
        array $matchingRules
    ): array {
        $results = [
            'imported' => 0,
            'linked' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($shopifyProducts as $shopifyProduct) {
            try {
                if ($linkExisting) {
                    // 🔗 Try to link to existing product first
                    $linkResult = $this->tryLinkExistingProduct($shopifyProduct, $matchingRules, $dryRun);

                    if ($linkResult['linked']) {
                        $results['linked']++;

                        continue;
                    }
                }

                // 📥 Import as new product
                $importResult = $this->importAction->execute($shopifyProduct, $dryRun);

                if ($importResult['success']) {
                    if ($importResult['action'] === 'created') {
                        $results['imported']++;
                    } else {
                        $results['skipped']++;
                    }
                } else {
                    $results['errors'][] = "Product {$shopifyProduct['title']}: ".
                        implode(', ', $importResult['errors']);
                }

            } catch (\Exception $e) {
                $results['errors'][] = "Product {$shopifyProduct['title']}: {$e->getMessage()}";
                Log::warning('Product processing failed', [
                    'shopify_id' => $shopifyProduct['id'],
                    'title' => $shopifyProduct['title'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * 🤝 TRY LINK EXISTING PRODUCT - Match making at its finest!
     */
    protected function tryLinkExistingProduct(array $shopifyProduct, array $matchingRules, bool $dryRun): array
    {
        $existingProduct = null;

        // Try each matching rule until we find a match
        foreach ($matchingRules as $rule) {
            $existingProduct = $this->findProductByRule($shopifyProduct, $rule);
            if ($existingProduct) {
                break;
            }
        }

        if (! $existingProduct) {
            return ['linked' => false, 'reason' => 'No matching product found'];
        }

        // Check if already linked to avoid duplicates
        $metadata = $existingProduct->metadata ?? [];
        if (isset($metadata['shopify_id'])) {
            return ['linked' => false, 'reason' => 'Already linked to Shopify'];
        }

        if ($dryRun) {
            return [
                'linked' => true,
                'reason' => 'Would link to existing product',
                'product_id' => $existingProduct->id,
            ];
        }

        // 💫 Update the product with Shopify metadata
        $existingProduct->update([
            'metadata' => array_merge($metadata, [
                'shopify_id' => $shopifyProduct['id'],
                'shopify_handle' => $shopifyProduct['handle'],
                'shopify_linked_at' => now()->toISOString(),
                'linked_via' => 'pull_action',
            ]),
        ]);

        Log::info('🔗 Linked existing product to Shopify', [
            'product_id' => $existingProduct->id,
            'shopify_id' => $shopifyProduct['id'],
            'title' => $shopifyProduct['title'],
        ]);

        return [
            'linked' => true,
            'reason' => 'Successfully linked',
            'product_id' => $existingProduct->id,
        ];
    }

    /**
     * 🔍 FIND PRODUCT BY RULE - The detective work!
     */
    protected function findProductByRule(array $shopifyProduct, string $rule): ?Product
    {
        return match ($rule) {
            'sku' => $this->findBySku($shopifyProduct),
            'barcode' => $this->findByBarcode($shopifyProduct),
            'title' => $this->findByTitle($shopifyProduct),
            'handle' => $this->findByHandle($shopifyProduct),
            default => null,
        };
    }

    /**
     * 🏷️ FIND BY SKU - Match by product SKU
     */
    protected function findBySku(array $shopifyProduct): ?Product
    {
        // Check first variant's SKU
        if (empty($shopifyProduct['variants'][0]['sku'])) {
            return null;
        }

        $sku = $shopifyProduct['variants'][0]['sku'];

        return Product::whereHas('variants', function ($q) use ($sku) {
            $q->where('sku', $sku);
        })->first();
    }

    /**
     * 🔢 FIND BY BARCODE - Match by barcode
     */
    protected function findByBarcode(array $shopifyProduct): ?Product
    {
        // Check first variant's barcode
        if (empty($shopifyProduct['variants'][0]['barcode'])) {
            return null;
        }

        $barcode = trim($shopifyProduct['variants'][0]['barcode'], "'\"");

        return Product::whereHas('variants.barcodes', function ($q) use ($barcode) {
            $q->where('barcode', $barcode);
        })->first();
    }

    /**
     * 📝 FIND BY TITLE - Match by product title
     */
    protected function findByTitle(array $shopifyProduct): ?Product
    {
        $title = $shopifyProduct['title'];

        return Product::where('name', 'LIKE', "%{$title}%")
            ->orWhere('name', 'LIKE', "%{$this->cleanTitle($title)}%")
            ->first();
    }

    /**
     * 🔗 FIND BY HANDLE - Match by Shopify handle in metadata
     */
    protected function findByHandle(array $shopifyProduct): ?Product
    {
        $handle = $shopifyProduct['handle'];

        return Product::whereJsonContains('metadata->shopify_handle', $handle)->first();
    }

    /**
     * 🧹 CLEAN TITLE - Remove extra words for better matching
     */
    protected function cleanTitle(string $title): string
    {
        // Remove common variations and colors for better matching
        $cleaned = preg_replace('/\s+(black|white|grey|blue|green|red|yellow)$/i', '', $title);

        return trim($cleaned);
    }

    /**
     * ⏱️ Start timing the execution
     */
    private function startTiming(): void
    {
        $this->startTime = microtime(true);
    }

    /**
     * ⏱️ Stop timing the execution
     */
    private function stopTiming(): void
    {
        $this->executionTime = microtime(true) - $this->startTime;
    }

    /**
     * ⏱️ Get formatted execution time
     */
    private function getExecutionTime(): string
    {
        return number_format($this->executionTime ?? 0, 2).'s';
    }
}
