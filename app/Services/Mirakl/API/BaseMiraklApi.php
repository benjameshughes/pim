<?php

namespace App\Services\Mirakl\API;

use App\Models\SyncAccount;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * 🏗️ BASE MIRAKL API CLIENT
 *
 * Foundation class for all Mirakl API clients.
 * Provides common functionality, configuration, and utilities.
 */
abstract class BaseMiraklApi
{
    protected Client $client;

    protected array $config;

    protected string $operator;

    public function __construct(string $operator)
    {
        $this->operator = $operator;
        $this->config = $this->getOperatorConfig($operator);

        $this->client = new Client([
            'base_uri' => $this->config['base_url'],
            'timeout' => 60,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => $this->config['api_key'],
            ],
        ]);
    }

    /**
     * ⚙️ GET OPERATOR CONFIG
     *
     * Enhanced to work with both SyncAccount format and legacy config
     */
    protected function getOperatorConfig(string $operator): array
    {
        // Try to get from SyncAccount first (enhanced lookup with auto-detection support)
        try {
            // Enhanced lookup to support auto-detected operators:
            // 1. Look for "mirakl" channel with marketplace_subtype matching operator
            // 2. Look for legacy "mirakl_{operator}" channel pattern
            // 3. Look for "mirakl" channel where auto-detected operator matches
            $syncAccount = SyncAccount::where(function ($query) use ($operator) {
                $query->where('channel', 'mirakl')
                    ->where('marketplace_subtype', $operator);
            })
                ->orWhere(function ($query) use ($operator) {
                    $query->where('channel', "mirakl_{$operator}");
                })
                ->orWhere(function ($query) use ($operator) {
                    $query->where('channel', 'mirakl')
                        ->whereJsonContains('settings->operator_detection->detected_operator', $operator);
                })
                ->where('is_active', true)
                ->first();

            if ($syncAccount) {
                $config = [
                    'name' => $syncAccount->display_name,
                    'base_url' => $syncAccount->credentials['base_url'] ?? '',
                    'api_key' => $syncAccount->credentials['api_key'] ?? '',
                ];

                // Get shop_id from multiple possible sources (auto-fetched data preferred)
                $shopId = $syncAccount->settings['auto_fetched_data']['shop_id']
                       ?? $syncAccount->credentials['shop_id']
                       ?? $syncAccount->credentials['store_id']
                       ?? '';

                $config['store_id'] = $shopId;

                // Get shop name from auto-fetched data if available
                $shopName = $syncAccount->settings['auto_fetched_data']['shop_name']
                         ?? $syncAccount->credentials['shop_name']
                         ?? '';

                if (! empty($shopName)) {
                    $config['shop_name'] = $shopName;
                }

                Log::info('✅ Using SyncAccount config for API client', [
                    'operator' => $operator,
                    'sync_account_id' => $syncAccount->id,
                    'display_name' => $syncAccount->display_name,
                    'channel' => $syncAccount->channel,
                    'marketplace_subtype' => $syncAccount->marketplace_subtype,
                    'has_auto_detection' => ! empty($syncAccount->settings['operator_detection']),
                ]);

                return $config;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get SyncAccount config for API client, falling back to legacy', [
                'operator' => $operator,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback to legacy config format
        Log::info('🔄 Using legacy config fallback for API client', ['operator' => $operator]);

        return match ($operator) {
            'freemans' => [
                'name' => 'Freemans',
                'base_url' => config('services.mirakl_operators.freemans.base_url'),
                'api_key' => config('services.mirakl_operators.freemans.api_key'),
                'store_id' => config('services.mirakl_operators.freemans.store_id'),
            ],
            'debenhams' => [
                'name' => 'Debenhams',
                'base_url' => config('services.mirakl_operators.debenhams.base_url'),
                'api_key' => config('services.mirakl_operators.debenhams.api_key'),
                'store_id' => config('services.mirakl_operators.debenhams.store_id'),
            ],
            'bq' => [
                'name' => 'B&Q',
                'base_url' => config('services.mirakl_operators.bq.base_url'),
                'api_key' => config('services.mirakl_operators.bq.api_key'),
                'store_id' => config('services.mirakl_operators.bq.store_id'),
            ],
            default => throw new \InvalidArgumentException("Unknown operator: {$operator}"),
        };
    }

    /**
     * 📄 ARRAY TO CSV
     *
     * Convert array data to CSV format
     *
     * @param  array<array<mixed>>  $data  Array of arrays for CSV rows
     * @return string CSV content
     */
    protected function arrayToCsv(array $data): string
    {
        $output = fopen('php://temp', 'w+');

        foreach ($data as $row) {
            fputcsv($output, $row, ',', '"', '\\');
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    /**
     * 🏭 STATIC FACTORY METHOD
     *
     * Create API client for specific operator
     *
     * @param  string  $operator  Operator code
     * @return static API client instance
     */
    public static function for(string $operator): static
    {
        return new static($operator);
    }

    /**
     * 🔍 GET OPERATOR INFO
     *
     * Get basic information about the configured operator
     *
     * @return array<string, mixed>
     */
    public function getOperatorInfo(): array
    {
        return [
            'operator' => $this->operator,
            'name' => $this->config['name'] ?? ucfirst($this->operator),
            'base_url' => $this->config['base_url'] ?? '',
            'store_id' => $this->config['store_id'] ?? '',
            'shop_name' => $this->config['shop_name'] ?? '',
        ];
    }

    /**
     * ✅ TEST API CONNECTION
     *
     * Test basic connectivity to the Mirakl API
     *
     * @return array<string, mixed>
     */
    public function testConnection(): array
    {
        try {
            $response = $this->client->request('GET', '/api/account');

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody()->getContents(), true);

                Log::info('✅ Mirakl API connection successful', [
                    'operator' => $this->operator,
                    'response_status' => $response->getStatusCode(),
                ]);

                return [
                    'success' => true,
                    'message' => 'API connection successful',
                    'operator' => $this->operator,
                    'account_data' => $data,
                ];
            }
        } catch (\Exception $e) {
            Log::error('❌ Mirakl API connection failed', [
                'operator' => $this->operator,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'operator' => $this->operator,
            ];
        }

        return [
            'success' => false,
            'error' => 'Unknown connection error',
            'operator' => $this->operator,
        ];
    }
}
