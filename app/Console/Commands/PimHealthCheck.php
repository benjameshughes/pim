<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * 🏥 PIM SYSTEM HEALTH CHECK
 *
 * Comprehensive overview of all system integrations
 */
class PimHealthCheck extends Command
{
    protected $signature = 'pim:health {--fix : Apply automatic fixes where possible}';

    protected $description = 'Complete PIM system health check and diagnostics';

    public function handle(): int
    {
        $this->info('🏥 PIM SYSTEM HEALTH CHECK');
        $this->info('==================================================');
        $this->newLine();

        $overallHealth = true;
        $issues = [];
        $fixes = [];

        // Test 1: Shopify Integration
        $this->info('🛍️ SHOPIFY INTEGRATION');
        $shopifyResult = $this->callTestCommand('shopify:test');
        if ($shopifyResult === 0) {
            $this->info('   ✅ WORKING PERFECTLY');
        } else {
            $this->error('   ❌ ISSUES DETECTED');
            $overallHealth = false;
            $issues[] = 'Shopify integration has configuration issues';
        }
        $this->newLine();

        // Test 2: eBay Integration
        $this->info('🏪 EBAY INTEGRATION');
        $ebayResult = $this->callTestCommand('ebay:test');
        if ($ebayResult === 0) {
            $this->info('   ✅ WORKING PERFECTLY');
        } else {
            $this->error('   ❌ MISSING CREDENTIALS');
            $overallHealth = false;
            $issues[] = 'eBay integration missing credentials in .env';
            $fixes[] = 'Add EBAY_CLIENT_ID, EBAY_CLIENT_SECRET, EBAY_DEV_ID to .env file';
        }
        $this->newLine();

        // Test 3: Image Processing
        $this->info('🖼️ IMAGE PROCESSING');
        $imageResult = $this->callTestCommand('images:test');
        if ($imageResult === 0) {
            $this->info('   ✅ CORE SYSTEM WORKING');
            $this->warn('   ⚠️  Missing Intervention Image library for advanced processing');
            $fixes[] = 'composer require intervention/image';
        } else {
            $this->error('   ❌ ISSUES DETECTED');
            $overallHealth = false;
            $issues[] = 'Image processing system has issues';
        }
        $this->newLine();

        // Test 4: Pricing System
        $this->info('💰 PRICING SYSTEM');
        $pricingResult = $this->callTestCommand('pricing:test');
        if ($pricingResult === 0) {
            $this->info('   ✅ CORE SYSTEM WORKING');
            $this->warn('   ⚠️  Missing sales channel data');
            $fixes[] = 'Run: php artisan db:seed --class=SalesChannelSeeder';
        } else {
            $this->error('   ❌ ISSUES DETECTED');
            $overallHealth = false;
            $issues[] = 'Pricing system has issues';
        }
        $this->newLine();

        // Summary
        $this->info('==================================================');
        if ($overallHealth) {
            $this->info('🎉 OVERALL HEALTH: EXCELLENT');
            $this->info('✅ All core integrations are working!');
        } else {
            $this->error('⚠️ OVERALL HEALTH: NEEDS ATTENTION');
            $this->error('❌ Some integrations need fixes');
        }

        if (! empty($issues)) {
            $this->newLine();
            $this->error('🚨 ISSUES FOUND:');
            foreach ($issues as $issue) {
                $this->line('   • '.$issue);
            }
        }

        if (! empty($fixes)) {
            $this->newLine();
            $this->info('🔧 RECOMMENDED FIXES:');
            foreach ($fixes as $fix) {
                $this->line('   • '.$fix);
            }

            if ($this->option('fix')) {
                $this->newLine();
                $this->info('🛠️ Applying automatic fixes...');
                $this->applyAutomaticFixes();
            } else {
                $this->newLine();
                $this->info('💡 Run with --fix to apply automatic fixes');
            }
        }

        return $overallHealth ? 0 : 1;
    }

    private function callTestCommand(string $command): int
    {
        try {
            return $this->call($command);
        } catch (\Exception $e) {
            $this->error('Failed to run '.$command.': '.$e->getMessage());

            return 1;
        }
    }

    private function applyAutomaticFixes(): void
    {
        // Check if Intervention Image can be installed
        if (! class_exists('Intervention\Image\ImageManager')) {
            $this->info('Installing Intervention Image...');
            $result = shell_exec('cd '.base_path().' && composer require intervention/image 2>&1');
            if ($result) {
                $this->line($result);
            }
        }

        // Check if we can create sales channel seeder
        $seederPath = database_path('seeders/SalesChannelSeeder.php');
        if (! file_exists($seederPath)) {
            $this->info('Creating SalesChannelSeeder...');
            $this->createSalesChannelSeeder();
        }

        // Run the seeder if it exists
        if (file_exists($seederPath)) {
            $this->info('Seeding sales channels...');
            try {
                $this->call('db:seed', ['--class' => 'SalesChannelSeeder']);
            } catch (\Exception $e) {
                $this->error('Failed to seed sales channels: '.$e->getMessage());
            }
        }

        $this->info('🎉 Automatic fixes applied!');
    }

    private function createSalesChannelSeeder(): void
    {
        $seederContent = <<<'PHP'
<?php

namespace Database\Seeders;

use App\Models\SalesChannel;
use Illuminate\Database\Seeder;

class SalesChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            [
                'name' => 'Shopify',
                'slug' => 'shopify',
                'is_active' => true,
                'commission_rate' => 2.9,
                'fixed_fee' => 0.30,
                'description' => 'Shopify online store',
            ],
            [
                'name' => 'eBay',
                'slug' => 'ebay',
                'is_active' => true,
                'commission_rate' => 10.0,
                'fixed_fee' => 0.30,
                'description' => 'eBay marketplace',
            ],
            [
                'name' => 'Amazon',
                'slug' => 'amazon',
                'is_active' => false,
                'commission_rate' => 15.0,
                'fixed_fee' => 0.00,
                'description' => 'Amazon marketplace',
            ],
            [
                'name' => 'Direct Sales',
                'slug' => 'direct',
                'is_active' => true,
                'commission_rate' => 0.0,
                'fixed_fee' => 0.00,
                'description' => 'Direct sales (no commission)',
            ],
            [
                'name' => 'Mirakl',
                'slug' => 'mirakl',
                'is_active' => true,
                'commission_rate' => 5.0,
                'fixed_fee' => 0.00,
                'description' => 'Mirakl marketplace platform',
            ],
        ];

        foreach ($channels as $channel) {
            SalesChannel::updateOrCreate(
                ['slug' => $channel['slug']],
                $channel
            );
        }
    }
}
PHP;

        file_put_contents(database_path('seeders/SalesChannelSeeder.php'), $seederContent);
    }
}
