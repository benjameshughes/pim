<?php

namespace App\Console\Commands;

use App\Livewire\Marketplace\AddIntegrationWizard;
use Illuminate\Console\Command;

/**
 * 🧪 TEST MIRAKL INTEGRATION WIZARD
 *
 * Test the enhanced AddIntegrationWizard with Mirakl store auto-fetch functionality
 */
class TestMiraklIntegrationWizard extends Command
{
    protected $signature = 'test:mirakl-integration-wizard';

    protected $description = 'Test the enhanced Mirakl integration wizard with auto-fetch store info';

    public function handle(): int
    {
        $this->info('🧪 Testing Mirakl Integration Wizard Auto-Fetch');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Create wizard instance
        $wizard = new AddIntegrationWizard;
        $wizard->mount();

        // Simulate Mirakl marketplace selection
        $this->info('1. Selecting Mirakl marketplace...');
        $wizard->selectedMarketplace = 'mirakl';
        $wizard->selectedOperator = 'freemans';

        // Set up test credentials
        $this->info('2. Setting up test credentials...');
        $wizard->credentials = [
            'base_url' => 'https://freemansuk-prod.mirakl.net',
            'api_key' => 'bee1ad57-e41a-49b7-aaeb-6ddf1e1b3e9e',
        ];

        // Test the fetch store info method
        $this->info('3. Testing fetchMiraklStoreInfo method...');

        try {
            $wizard->fetchMiraklStoreInfo();

            // Check if store info was populated
            if (! empty($wizard->credentials['shop_name']) && ! empty($wizard->credentials['shop_id'])) {
                $this->info('   ✅ Store info fetched successfully!');
                $this->info("   🏪 Shop Name: {$wizard->credentials['shop_name']}");
                $this->info("   🆔 Shop ID: {$wizard->credentials['shop_id']}");
                $this->info("   📋 Display Name: {$wizard->displayName}");

                // Check settings
                if (! empty($wizard->settings['auto_fetched_data'])) {
                    $autoData = $wizard->settings['auto_fetched_data'];
                    $this->info('   💾 Auto-fetched data stored:');
                    $this->info('      - Currency: '.($autoData['currency'] ?? 'N/A'));
                    $this->info('      - Shop State: '.($autoData['shop_state'] ?? 'N/A'));
                    $this->info('      - Fetched At: '.($autoData['fetched_at'] ?? 'N/A'));
                }

                $this->newLine();
                $this->info('🎉 Mirakl Integration Wizard auto-fetch functionality is working perfectly!');

                return 0;
            } else {
                $this->error('❌ Store info was not populated correctly');

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Test failed: {$e->getMessage()}");

            return 1;
        }
    }
}
