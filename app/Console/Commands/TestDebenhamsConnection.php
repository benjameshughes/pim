<?php

namespace App\Console\Commands;

use App\Models\SyncAccount;
use App\Services\Mirakl\Operators\DebenhamsOperatorClient;
use Illuminate\Console\Command;

/**
 * 🏬 TEST DEBENHAMS CONNECTION
 *
 * Tests the Debenhams marketplace API connection and displays results
 */
class TestDebenhamsConnection extends Command
{
    protected $signature = 'debenhams:test';

    protected $description = 'Test Debenhams marketplace API connection and functionality';

    public function handle(): int
    {
        $this->info('🏬 Testing Debenhams Marketplace Integration');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Find Debenhams sync account
        $account = SyncAccount::where('channel', 'mirakl_debenhams')
            ->where('name', 'debenhams')
            ->first();

        if (! $account) {
            $this->error('❌ Debenhams sync account not found. Run: php artisan db:seed --class=SyncAccountSeeder');

            return 1;
        }

        $this->info("📋 Account: {$account->display_name}");
        $this->info("🔧 Channel: {$account->channel}");
        $this->info('✅ Status: '.($account->is_active ? 'Active' : 'Inactive'));
        $this->newLine();

        // Initialize Debenhams client
        $client = new DebenhamsOperatorClient;

        // Test 1: Display requirements
        $this->info('📋 STEP 1: Debenhams Requirements');
        $requirements = $client->getRequirements();
        $this->info("   Operator: {$requirements['name']}");
        $this->info("   Currency: {$requirements['currency']}");
        $this->info("   API Structure: {$requirements['api_structure']}");
        $this->info("   Store ID: {$requirements['store_id']}");
        $this->newLine();

        // Test 2: Connection test
        $this->info('🔌 STEP 2: Testing API Connection');
        $this->info('   Testing offers API endpoint...');

        $connectionResult = $client->testConnection($account);

        if ($connectionResult['success']) {
            $this->info('   ✅ Connection successful!');
            $this->info("   📊 Base URL: {$connectionResult['base_url']}");
            $this->info("   🏪 Store ID: {$connectionResult['store_id']}");
            $this->info("   🕐 Connected at: {$connectionResult['connection_time']}");
        } else {
            $this->error('   ❌ Connection failed!');
            $this->error("   💥 Error: {$connectionResult['error']}");
            if (isset($connectionResult['status_code'])) {
                $this->error("   🔢 Status Code: {$connectionResult['status_code']}");
            }
            $this->newLine();
            $this->info('🔧 Troubleshooting:');
            foreach ($connectionResult['troubleshooting'] ?? [] as $step) {
                $this->info("   • {$step}");
            }
        }
        $this->newLine();

        // Test 3: Display integration status
        $this->info('📊 STEP 3: Integration Status');
        if ($connectionResult['success']) {
            $this->info('   🟢 Status: Ready for product sync');
            $this->info('   💰 Offers API: Available');
            $this->info('   📋 Products API: Implementation pending (may require file upload)');
            $this->info('   🔗 Family SKU Pattern: Supported (parent_sku → variant_sku)');
        } else {
            $this->warn('   🟡 Status: Connection issues detected');
            $this->info('   💡 Recommendation: Check API credentials and network connectivity');
        }
        $this->newLine();

        // Test 4: Show next steps
        $this->info('🚀 NEXT STEPS:');
        if ($connectionResult['success']) {
            $this->info('   1. Test product/offer data transformation');
            $this->info('   2. Implement catalog upload (if file-based)');
            $this->info('   3. Add MarketplaceLink tracking');
            $this->info('   4. Test end-to-end product sync');
        } else {
            $this->info('   1. Verify API credentials in .env file');
            $this->info('   2. Check network connectivity to Debenhams API');
            $this->info('   3. Contact Debenhams support if issues persist');
        }

        return $connectionResult['success'] ? 0 : 1;
    }
}
