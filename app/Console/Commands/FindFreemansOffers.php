<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

/**
 * 🔍 FIND FREEMANS OFFERS
 *
 * Searches through Freemans offers to find specific SKUs or patterns
 */
class FindFreemansOffers extends Command
{
    protected $signature = 'find:freemans-offers {--search= : Search pattern or SKU}';

    protected $description = 'Find offers in Freemans marketplace by SKU pattern';

    public function handle(): int
    {
        $searchPattern = $this->option('search') ?? '011';

        $this->info("🔍 Searching Freemans offers for pattern: {$searchPattern}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $config = [
            'base_url' => config('services.mirakl_operators.freemans.base_url'),
            'api_key' => config('services.mirakl_operators.freemans.api_key'),
            'store_id' => config('services.mirakl_operators.freemans.store_id'),
        ];

        $client = new Client([
            'base_uri' => $config['base_url'],
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => $config['api_key'],
            ],
        ]);

        $this->searchOffers($client, $config, $searchPattern);

        return 0;
    }

    protected function searchOffers(Client $client, array $config, string $searchPattern): void
    {
        try {
            $page = 0;
            $limit = 100;
            $totalChecked = 0;
            $foundOffers = [];

            do {
                $offset = $page * $limit;

                $response = $client->request('GET', '/api/offers', [
                    'query' => [
                        'shop' => $config['store_id'],
                        'limit' => $limit,
                        'offset' => $offset,
                    ],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                $offers = $data['offers'] ?? [];
                $totalCount = $data['total_count'] ?? 0;

                $totalChecked += count($offers);

                echo '   📋 Page '.($page + 1).': Checking '.count($offers)." offers (Total checked: {$totalChecked}/{$totalCount})\n";

                // Search through this batch
                foreach ($offers as $offer) {
                    if (isset($offer['shop_sku']) && strpos($offer['shop_sku'], $searchPattern) !== false) {
                        $foundOffers[] = $offer;
                        echo "   ✅ Found: {$offer['shop_sku']} - Price: £".($offer['price'] ?? 'N/A').' - State: '.($offer['state'] ?? 'N/A')."\n";

                        // If exact match found, show details
                        if ($offer['shop_sku'] === $searchPattern) {
                            echo "\n📋 EXACT MATCH - Offer Details:\n";
                            echo '   🏷️  SKU: '.($offer['shop_sku'] ?? 'N/A')."\n";
                            echo '   📦 Product ID: '.($offer['product_id'] ?? 'N/A')."\n";
                            echo '   💰 Price: £'.($offer['price'] ?? 'N/A')."\n";
                            echo '   📊 Quantity: '.($offer['quantity'] ?? 'N/A')."\n";
                            echo '   🔄 State: '.($offer['state'] ?? 'N/A')."\n";

                            if (isset($offer['description'])) {
                                echo '   📝 Description: '.substr($offer['description'], 0, 100)."...\n";
                            }
                            echo "\n";
                        }
                    }
                }

                $page++;

                // Stop if we've checked all offers
                if (count($offers) < $limit || $totalChecked >= $totalCount) {
                    break;
                }

            } while ($page < 50); // Safety limit

            echo "\n🎯 Search Results:\n";
            echo '   Found '.count($foundOffers)." offers matching pattern '{$searchPattern}'\n";
            echo "   Checked {$totalChecked} total offers\n";

            if (empty($foundOffers)) {
                echo "   ⚠️  No offers found matching pattern '{$searchPattern}'\n";
                echo "\n💡 Try searching for a broader pattern like '011' instead of '011-023'\n";
            }

        } catch (GuzzleException $e) {
            echo "❌ Failed to search offers: {$e->getMessage()}\n";
        }
    }
}
