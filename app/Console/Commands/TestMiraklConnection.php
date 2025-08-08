<?php

namespace App\Console\Commands;

use App\Services\MiraklConnectService;
use Exception;
use Illuminate\Console\Command;

class TestMiraklConnection extends Command
{
    protected $signature = 'mirakl:test-connection';

    protected $description = 'Test connection to Mirakl Connect API';

    public function handle()
    {
        $this->info('Testing Mirakl Connect API connection...');

        // Show configuration for debugging
        $baseUrl = config('services.mirakl.base_url');
        $clientId = config('services.mirakl.client_id');
        $sellerId = config('services.mirakl.seller_id');

        $this->line('Base URL: '.$baseUrl);
        $this->line('Client ID: '.(empty($clientId) ? 'NOT SET' : substr($clientId, 0, 8).'...'));
        $this->line('Seller ID: '.(empty($sellerId) ? 'NOT SET' : $sellerId));

        try {
            $service = new MiraklConnectService;
            $result = $service->testConnection();

            if ($result['success']) {
                $this->info('✅ '.$result['message']);
                if (isset($result['response'])) {
                    $this->line('Response: '.json_encode($result['response'], JSON_PRETTY_PRINT));
                }
            } else {
                $this->error('❌ '.$result['message']);
                if (isset($result['error'])) {
                    $this->error('Error: '.$result['error']);
                }
                if (isset($result['response'])) {
                    $this->error('API Response: '.json_encode($result['response'], JSON_PRETTY_PRINT));
                }
                if (isset($result['status_code'])) {
                    $this->error('Status Code: '.$result['status_code']);
                }
            }

        } catch (Exception $e) {
            $this->error('❌ Connection test failed: '.$e->getMessage());

            // Try to debug the authentication endpoints
            $this->line('');
            $this->info('Debugging authentication endpoints...');

            $authEndpoints = [
                'https://miraklconnect.com/api/auth/token',
                'https://miraklconnect.com/auth/token',
                'https://miraklconnect.com/oauth/token',
                'https://miraklconnect.com/api/oauth/token',
            ];

            foreach ($authEndpoints as $endpoint) {
                $this->line("Testing: $endpoint");
                try {
                    $response = \Illuminate\Support\Facades\Http::asForm()->post($endpoint, [
                        'client_id' => config('services.mirakl.client_id'),
                        'seller_id' => config('services.mirakl.seller_id'),
                        'grant_type' => 'client_credentials',
                    ]);

                    $this->line('  Status: '.$response->status());
                    $this->line('  Response: '.substr($response->body(), 0, 200).'...');
                } catch (\Exception $ex) {
                    $this->line('  Error: '.$ex->getMessage());
                }
            }
        }
    }
}
