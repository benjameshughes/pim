<?php

namespace App\Console\Commands;

use App\Services\ShopifyConnectService;
use Exception;
use Illuminate\Console\Command;

class TestShopifyConnection extends Command
{
    protected $signature = 'shopify:test-connection';

    protected $description = 'Test connection to Shopify Admin API';

    public function handle()
    {
        try {
            $service = new ShopifyConnectService;

            $this->info('🛍️  Testing Shopify connection...');

            $result = $service->testConnection();

            if ($result['success']) {
                $this->info('✅ Successfully connected to Shopify!');

                if (isset($result['response']['shop'])) {
                    $shop = $result['response']['shop'];
                    $this->line("Store: {$shop['name']}");
                    $this->line("Domain: {$shop['domain']}");
                    $this->line("Email: {$shop['email']}");
                    $this->line("Currency: {$shop['currency']}");
                    $this->line("Plan: {$shop['plan_name']}");
                }
            } else {
                $this->error('❌ Connection failed: '.$result['message']);
                if (isset($result['response'])) {
                    $this->line('Response: '.json_encode($result['response'], JSON_PRETTY_PRINT));
                }
            }

        } catch (Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());

            return 1;
        }

        return 0;
    }
}
