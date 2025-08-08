<?php

namespace App\Actions\API\Shopify;

use App\Services\ShopifyConnectService;
use Illuminate\Support\Facades\Log;

class ImportMultipleShopifyProducts
{
    public function __construct(
        private ImportShopifyProduct $importShopifyProduct,
        private ShopifyConnectService $shopifyService
    ) {}

    /**
     * Import multiple products from Shopify
     */
    public function execute(array $options = []): array
    {
        $limit = $options['limit'] ?? 50;
        $dryRun = $options['dry_run'] ?? false;
        $filterStatus = $options['status'] ?? null;

        Log::info('Starting Shopify import', [
            'limit' => $limit,
            'dry_run' => $dryRun,
            'filter_status' => $filterStatus,
        ]);

        // Fetch products from Shopify
        $shopifyResult = $this->shopifyService->getProducts($limit);

        if (! $shopifyResult['success']) {
            return [
                'success' => false,
                'error' => 'Failed to fetch products from Shopify: '.$shopifyResult['error'],
                'results' => [],
            ];
        }

        $shopifyProducts = $shopifyResult['data']['products'] ?? [];

        // Filter by status if specified
        if ($filterStatus) {
            $shopifyProducts = array_filter($shopifyProducts, function ($product) use ($filterStatus) {
                return $product['status'] === $filterStatus;
            });
        }

        $results = [];
        $summary = [
            'total_processed' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors' => 0,
            'total_variants' => 0,
        ];

        foreach ($shopifyProducts as $shopifyProduct) {
            $result = $this->importShopifyProduct->execute($shopifyProduct, $dryRun);

            $results[$shopifyProduct['id']] = [
                'shopify_id' => $shopifyProduct['id'],
                'title' => $shopifyProduct['title'],
                'handle' => $shopifyProduct['handle'],
                'status' => $shopifyProduct['status'],
                'variants_count' => count($shopifyProduct['variants']),
                'result' => $result,
            ];

            // Update summary
            $summary['total_processed']++;
            $summary['total_variants'] += $result['variants_imported'];

            switch ($result['action']) {
                case 'created':
                case 'would_create':
                    $summary['created']++;
                    break;
                case 'skipped':
                    $summary['skipped']++;
                    break;
            }

            if (! $result['success'] || ! empty($result['errors'])) {
                $summary['errors']++;
            }

            // Add small delay to avoid overwhelming the system
            usleep(100000); // 100ms
        }

        Log::info('Shopify import completed', $summary);

        return [
            'success' => true,
            'summary' => $summary,
            'results' => $results,
        ];
    }
}
