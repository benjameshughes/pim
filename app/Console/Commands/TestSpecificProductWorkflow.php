<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Mirakl\Operators\FreemansOperatorClient;
use App\Services\Mirakl\ProductCsvUploader;
use Illuminate\Console\Command;

/**
 * 🎯 TEST SPECIFIC PRODUCT WORKFLOW
 *
 * Tests the complete workflow with a specific existing product
 */
class TestSpecificProductWorkflow extends Command
{
    protected $signature = 'test:specific-product {sku} {marketplace=freemans} {--monitor : Monitor the import progress}';

    protected $description = 'Test complete workflow with a specific product SKU';

    public function handle(): int
    {
        $sku = $this->argument('sku');
        $marketplace = $this->argument('marketplace');
        $monitor = $this->option('monitor');

        echo "🎯 Testing Specific Product Workflow\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "SKU: {$sku}\n";
        echo "Marketplace: {$marketplace}\n\n";

        // Step 1: Find the product
        $product = Product::where('parent_sku', $sku)->first();

        if (! $product) {
            echo "❌ Product not found with SKU: {$sku}\n";

            return 1;
        }

        // Load variants
        $product->load('variants');

        echo "📦 Product Found:\n";
        echo "   Name: {$product->name}\n";
        echo '   Description: '.substr($product->description ?? 'None', 0, 100)."...\n";
        echo "   Variants: {$product->variants->count()}\n";

        foreach ($product->variants as $variant) {
            echo "   🏷️  {$variant->sku} - £{$variant->price} (Stock: {$variant->stock_level})\n";
        }
        echo "\n";

        // Step 2: Check current marketplace status
        $this->checkMarketplaceStatus($product, $marketplace);

        // Step 3: Execute workflow
        echo "🚀 Executing Workflow...\n";
        $result = $this->executeWorkflow($product, $marketplace);

        if ($result['success']) {
            echo "✅ Workflow executed successfully!\n\n";
            $this->displayResults($result);

            if ($monitor && isset($result['results']['products_api']['import_id'])) {
                $this->monitorImport($result['results']['products_api']['import_id'], $marketplace);
            }
        } else {
            echo "❌ Workflow failed: {$result['error']}\n";

            return 1;
        }

        return 0;
    }

    /**
     * 🔍 CHECK MARKETPLACE STATUS
     */
    protected function checkMarketplaceStatus(Product $product, string $marketplace): void
    {
        echo "🔍 Checking current marketplace status...\n";

        $client = $this->getMarketplaceClient($marketplace);

        // Check if product exists in catalog
        $productExists = $this->checkProductInCatalog($product, $marketplace);
        echo '   📋 Catalog Status: '.($productExists ? '✅ Exists' : '❌ Not Found')."\n";

        // Check for existing offers
        $offers = $this->checkExistingOffers($product, $marketplace);
        echo "   💰 Offers Status: {$offers['count']} offers found\n";

        if ($offers['count'] > 0) {
            foreach ($offers['offers'] as $offer) {
                echo "      🏷️  {$offer['sku']} - £{$offer['price']} ({$offer['state']})\n";
            }
        }

        echo "\n";
    }

    /**
     * 🔍 CHECK PRODUCT IN CATALOG
     */
    protected function checkProductInCatalog(Product $product, string $marketplace): bool
    {
        try {
            $config = $this->getMarketplaceConfig($marketplace);
            $uploader = \App\Services\Mirakl\ProductCsvUploader::forMarketplace($marketplace);

            // Use the uploader's client to check products
            $client = new \GuzzleHttp\Client([
                'base_uri' => $config['base_url'],
                'timeout' => 30,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $config['api_key'],
                ],
            ]);

            $response = $client->request('GET', '/api/products', [
                'query' => [
                    'shop' => $config['store_id'],
                    'product_references' => $product->parent_sku.'|SHOP_SKU',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return ! empty($data['products'] ?? []);

        } catch (\Exception $e) {
            echo "   ⚠️  Could not check catalog: {$e->getMessage()}\n";

            return false;
        }
    }

    /**
     * 💰 CHECK EXISTING OFFERS
     */
    protected function checkExistingOffers(Product $product, string $marketplace): array
    {
        try {
            $config = $this->getMarketplaceConfig($marketplace);

            $client = new \GuzzleHttp\Client([
                'base_uri' => $config['base_url'],
                'timeout' => 30,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $config['api_key'],
                ],
            ]);

            $response = $client->request('GET', '/api/offers', [
                'query' => [
                    'shop' => $config['store_id'],
                    'limit' => 100,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $allOffers = $data['offers'] ?? [];

            // Filter for our product's variants
            $variantSkus = $product->variants->pluck('sku')->toArray();
            $productOffers = [];

            foreach ($allOffers as $offer) {
                if (in_array($offer['shop_sku'] ?? '', $variantSkus)) {
                    $productOffers[] = [
                        'sku' => $offer['shop_sku'],
                        'price' => $offer['price'] ?? 'N/A',
                        'state' => $offer['state'] ?? 'N/A',
                        'quantity' => $offer['quantity'] ?? 'N/A',
                    ];
                }
            }

            return [
                'count' => count($productOffers),
                'offers' => $productOffers,
            ];

        } catch (\Exception $e) {
            echo "   ⚠️  Could not check offers: {$e->getMessage()}\n";

            return ['count' => 0, 'offers' => []];
        }
    }

    /**
     * 🚀 EXECUTE WORKFLOW
     */
    protected function executeWorkflow(Product $product, string $marketplace): array
    {
        try {
            $client = $this->getMarketplaceClient($marketplace);

            return $client->pushProducts([$product]);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 📊 DISPLAY RESULTS
     */
    protected function displayResults(array $result): void
    {
        echo "📊 Detailed Results:\n";
        echo "   Operator: {$result['operator']}\n";
        echo "   Store ID: {$result['store_id']}\n";
        echo "   Message: {$result['message']}\n";
        echo "   Links Created: {$result['links_created']}\n";
        echo "   Links Updated: {$result['links_updated']}\n\n";

        if (isset($result['results']['products_api'])) {
            $productsApi = $result['results']['products_api'];
            echo "📋 Catalog API Results:\n";
            echo '   Success: '.($productsApi['success'] ? '✅ Yes' : '❌ No')."\n";
            echo "   Message: {$productsApi['message']}\n";

            if (isset($productsApi['import_id'])) {
                echo "   Import ID: {$productsApi['import_id']}\n";
            }
            if (isset($productsApi['products_count'])) {
                echo "   Products Count: {$productsApi['products_count']}\n";
            }
            if (isset($productsApi['skipped_count'])) {
                echo "   Skipped Count: {$productsApi['skipped_count']}\n";
            }
            echo "\n";
        }

        if (isset($result['results']['offers_api'])) {
            $offersApi = $result['results']['offers_api'];
            echo "💰 Offers API Results:\n";
            echo '   Success: '.($offersApi['success'] ? '✅ Yes' : '❌ No')."\n";
            echo "   Message: {$offersApi['message']}\n";

            if (isset($offersApi['import_id'])) {
                echo "   Import ID: {$offersApi['import_id']}\n";
            }
            if (isset($offersApi['offers_count'])) {
                echo "   Offers Count: {$offersApi['offers_count']}\n";
            }
            echo "\n";
        }
    }

    /**
     * 📊 MONITOR IMPORT
     */
    protected function monitorImport(int $importId, string $marketplace): void
    {
        echo "📊 Monitoring Import Progress (Import ID: {$importId})\n";
        echo "Press Ctrl+C to stop monitoring...\n\n";

        $uploader = ProductCsvUploader::forMarketplace($marketplace);
        $attempts = 0;
        $maxAttempts = 20; // ~100 minutes max

        while ($attempts < $maxAttempts) {
            $status = $uploader->checkImportStatus($importId);
            $currentStatus = $status['status'] ?? 'unknown';
            $timestamp = now()->format('H:i:s');

            echo "[{$timestamp}] Import Status: {$currentStatus}\n";

            if (in_array($currentStatus, ['COMPLETE', 'FAILED', 'CANCELLED'])) {
                echo "\n🎯 Import finished with status: {$currentStatus}\n";

                if ($currentStatus === 'FAILED' && ($status['has_error_report'] ?? false)) {
                    echo "📋 Downloading error report...\n";
                    $errorReport = $uploader->downloadErrorReport($importId);
                    if ($errorReport['success']) {
                        echo "Error details:\n".substr($errorReport['error_report'], 0, 500)."...\n";
                    }
                }
                break;
            }

            $attempts++;
            echo "   ⏳ Waiting 30 seconds before next check...\n";
            sleep(30); // Check every 30 seconds for demo purposes
        }

        if ($attempts >= $maxAttempts) {
            echo "\n⏰ Monitoring timeout reached. Import may still be processing.\n";
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

    /**
     * ⚙️ GET MARKETPLACE CONFIG
     */
    protected function getMarketplaceConfig(string $marketplace): array
    {
        return match ($marketplace) {
            'freemans' => [
                'base_url' => config('services.mirakl_operators.freemans.base_url'),
                'api_key' => config('services.mirakl_operators.freemans.api_key'),
                'store_id' => config('services.mirakl_operators.freemans.store_id'),
            ],
            'debenhams' => [
                'base_url' => config('services.mirakl_operators.debenhams.base_url'),
                'api_key' => config('services.mirakl_operators.debenhams.api_key'),
                'store_id' => config('services.mirakl_operators.debenhams.store_id'),
            ],
            'bq' => [
                'base_url' => config('services.mirakl_operators.bq.base_url'),
                'api_key' => config('services.mirakl_operators.bq.api_key'),
                'store_id' => config('services.mirakl_operators.bq.store_id'),
            ],
            default => throw new \InvalidArgumentException("Unknown marketplace: {$marketplace}"),
        };
    }
}
