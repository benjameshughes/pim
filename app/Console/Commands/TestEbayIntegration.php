<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EbayConnectService;
use App\Models\Marketplace;

class TestEbayIntegration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ebay:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test eBay integration and API connectivity';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing eBay Integration...');
        $this->newLine();

        try {
            // Test eBay connection service
            $ebayService = new EbayConnectService();
            
            $this->info('🔗 Testing eBay API connection...');
            $connectionResult = $ebayService->testConnection();
            
            if ($connectionResult['success']) {
                $this->info("✅ Successfully connected to eBay API");
                $this->info("Environment: {$connectionResult['environment']}");
            } else {
                $this->error("❌ eBay API connection failed: {$connectionResult['error']}");
                $this->warn('Make sure you have configured your eBay credentials in .env file:');
                $this->line('- EBAY_CLIENT_ID');
                $this->line('- EBAY_CLIENT_SECRET');
                $this->line('- EBAY_ENVIRONMENT (SANDBOX or PRODUCTION)');
            }
            
            $this->newLine();

            // Show eBay marketplace configuration
            $this->info('⚙️  eBay Configuration Status:');
            $this->line('============================');
            $config = config('services.ebay');
            $this->line("Environment: " . ($config['environment'] ?? 'Not configured'));
            $this->line("Client ID: " . (empty($config['client_id']) ? 'Not configured' : 'Configured ✅'));
            $this->line("Client Secret: " . (empty($config['client_secret']) ? 'Not configured' : 'Configured ✅'));
            $this->line("Fulfillment Policy: " . ($config['fulfillment_policy_id'] ?? 'Not configured'));
            $this->line("Payment Policy: " . ($config['payment_policy_id'] ?? 'Not configured'));
            $this->line("Return Policy: " . ($config['return_policy_id'] ?? 'Not configured'));
            
            $this->newLine();

            // Show marketplace data
            $ebayMarketplaces = Marketplace::where('platform', 'ebay')->get();
            $this->info('🏪 Available eBay Marketplaces:');
            $this->line('===============================');
            
            if ($ebayMarketplaces->count() > 0) {
                foreach ($ebayMarketplaces as $marketplace) {
                    $status = $marketplace->status === 'active' ? '✅' : '❌';
                    $this->line("- {$marketplace->name} (Code: {$marketplace->code}) {$status}");
                }
            } else {
                $this->warn('No eBay marketplaces found. Run: php artisan db:seed --class=MarketplaceSeeder');
            }
            
            $this->newLine();

            // Test data structure building
            $this->info('🔧 Testing data structure building...');
            
            $inventoryData = $ebayService->buildInventoryItemData([
                'title' => 'Test eBay Window Blind',
                'description' => 'Premium quality test blind',
                'inventory_quantity' => 10,
                'images' => [],
                'brand' => 'Test Brand',
                'color' => 'White',
                'attributes' => [
                    'material' => 'Fabric',
                    'room_type' => 'Living Room'
                ]
            ]);
            
            $this->info('✅ Inventory item data structure created');
            $this->line("Product title: {$inventoryData['product']['title']}");
            $this->line("Available quantity: {$inventoryData['availability']['shipToLocationAvailability']['quantity']}");
            $this->line("Product aspects: " . count($inventoryData['product']['aspects']) . " attributes");
            
            $this->newLine();
            
            if ($connectionResult['success']) {
                $this->info('🎉 eBay Integration is fully functional!');
                $this->info('You can now use the bulk operations to export products to eBay.');
            } else {
                $this->info('🎯 eBay Integration structure is ready!');
                $this->info('Configure credentials to enable live API functionality.');
            }
            
            $this->newLine();
            $this->info('Next Steps:');
            $this->line('1. Configure eBay credentials in .env file');
            $this->line('2. Set up eBay business policies (fulfillment, payment, return)');
            $this->line('3. Test with real product export using bulk operations UI');
            $this->line('4. Monitor exports in the marketplace variants table');

        } catch (\Exception $e) {
            $this->error("❌ Test failed: " . $e->getMessage());
            $this->line("Stack trace:");
            $this->line($e->getTraceAsString());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
