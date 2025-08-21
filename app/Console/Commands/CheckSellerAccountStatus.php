<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

/**
 * 🔍 CHECK SELLER ACCOUNT STATUS
 *
 * Checks seller account status to see if accounts need activation/approval
 */
class CheckSellerAccountStatus extends Command
{
    protected $signature = 'check:seller-status';

    protected $description = 'Check seller account status across all marketplaces';

    public function handle(): int
    {
        $this->info('🔍 Checking Seller Account Status');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $this->checkSellerStatus('freemans');
        $this->newLine();
        $this->checkSellerStatus('debenhams');
        $this->newLine();
        $this->checkSellerStatus('bq');

        return 0;
    }

    protected function checkSellerStatus(string $marketplace): void
    {
        $this->info("🏬 Checking {$marketplace} seller account status");

        $config = $this->getMarketplaceConfig($marketplace);
        if (! $config) {
            $this->error("❌ Unknown marketplace: {$marketplace}");

            return;
        }

        $client = new Client([
            'base_uri' => $config['base_url'],
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => $config['api_key'],
            ],
        ]);

        // Try different endpoints to understand account status
        $this->checkShopInfo($client, $config);
        $this->checkShopStatus($client, $config);
        $this->checkApiAccess($client, $config);
    }

    protected function checkShopInfo(Client $client, array $config): void
    {
        try {
            $this->info('   🔍 Checking shop information...');

            $response = $client->request('GET', '/api/shops/'.$config['store_id']);
            $data = json_decode($response->getBody()->getContents(), true);

            $this->info('   ✅ Shop found!');
            $this->info('   📊 Shop ID: '.($data['shop_id'] ?? 'unknown'));
            $this->info('   🏢 Shop Name: '.($data['shop_name'] ?? 'unknown'));
            $this->info('   📍 State: '.($data['shop_state'] ?? 'unknown'));

            if (isset($data['shop_state'])) {
                $state = $data['shop_state'];
                if ($state === 'OPEN') {
                    $this->info('   ✅ Shop is OPEN - should be able to sell');
                } elseif ($state === 'CLOSED') {
                    $this->warn('   ⚠️  Shop is CLOSED - cannot sell yet');
                } elseif ($state === 'SUSPENDED') {
                    $this->error('   ❌ Shop is SUSPENDED');
                } else {
                    $this->warn("   ⚠️  Shop state: {$state}");
                }
            }

        } catch (GuzzleException $e) {
            if ($e->getCode() === 403) {
                $this->warn('   ⚠️  Access denied to shop info - may need different permissions');
            } else {
                $this->error("   ❌ Failed to get shop info: {$e->getMessage()}");
            }
        }
    }

    protected function checkShopStatus(Client $client, array $config): void
    {
        try {
            $this->info('   🔍 Checking shop evaluation status...');

            $response = $client->request('GET', '/api/shops/'.$config['store_id'].'/evaluations');
            $data = json_decode($response->getBody()->getContents(), true);

            if (! empty($data)) {
                $this->info('   📊 Evaluation data found:');
                foreach ($data as $key => $value) {
                    if (is_scalar($value)) {
                        $this->info("   📈 {$key}: {$value}");
                    }
                }
            } else {
                $this->warn('   ⚠️  No evaluation data available');
            }

        } catch (GuzzleException $e) {
            if ($e->getCode() === 404) {
                $this->warn('   ⚠️  No evaluation data available');
            } else {
                $this->warn('   ⚠️  Could not check evaluations: '.substr($e->getMessage(), 0, 100));
            }
        }
    }

    protected function checkApiAccess(Client $client, array $config): void
    {
        $this->info('   🔍 Testing API endpoint access...');

        $endpoints = [
            '/api/offers' => 'Offers API',
            '/api/orders' => 'Orders API',
            '/api/messages' => 'Messages API',
            '/api/shipping' => 'Shipping API',
        ];

        foreach ($endpoints as $endpoint => $name) {
            try {
                $response = $client->request('GET', $endpoint, [
                    'query' => ['shop' => $config['store_id'], 'limit' => 1],
                ]);

                $statusCode = $response->getStatusCode();
                $this->info("   ✅ {$name}: HTTP {$statusCode} (accessible)");

            } catch (GuzzleException $e) {
                $statusCode = $e->getCode();
                if ($statusCode === 403) {
                    $this->warn("   ⚠️  {$name}: Access denied (may require permissions)");
                } elseif ($statusCode === 404) {
                    $this->warn("   ⚠️  {$name}: Not found");
                } else {
                    $this->error("   ❌ {$name}: HTTP {$statusCode}");
                }
            }
        }
    }

    protected function getMarketplaceConfig(string $marketplace): ?array
    {
        switch ($marketplace) {
            case 'freemans':
                return [
                    'base_url' => config('services.mirakl_operators.freemans.base_url'),
                    'api_key' => config('services.mirakl_operators.freemans.api_key'),
                    'store_id' => config('services.mirakl_operators.freemans.store_id'),
                ];
            case 'debenhams':
                return [
                    'base_url' => config('services.mirakl_operators.debenhams.base_url'),
                    'api_key' => config('services.mirakl_operators.debenhams.api_key'),
                    'store_id' => config('services.mirakl_operators.debenhams.store_id'),
                ];
            case 'bq':
                return [
                    'base_url' => config('services.mirakl_operators.bq.base_url'),
                    'api_key' => config('services.mirakl_operators.bq.api_key'),
                    'store_id' => config('services.mirakl_operators.bq.store_id'),
                ];
            default:
                return null;
        }
    }
}
