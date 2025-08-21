<?php

namespace App\Console\Commands;

use App\Services\Mirakl\MiraklOperatorRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class MiraklAddOperatorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mirakl:add-operator 
                            {operator? : Operator name}
                            {--base-url= : Mirakl base URL}
                            {--api-key= : API key}
                            {--store-id= : Store ID}
                            {--display-name= : Display name}
                            {--category= : Default category}
                            {--auto-sync : Enable auto-sync}
                            {--interactive : Interactive mode (default)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a new Mirakl operator to the system (80% automated setup)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏭 Add New Mirakl Operator');
        $this->info('80% automated setup via SyncAccount integration');
        $this->newLine();

        // Get operator configuration
        $config = $this->gatherOperatorConfig();

        if (! $config) {
            $this->error('❌ Operator setup cancelled');

            return 1;
        }

        // Display configuration summary
        $this->displayConfigSummary($config);

        // Confirm setup
        if (! $this->confirm('Proceed with operator setup?', true)) {
            $this->info('❌ Setup cancelled by user');

            return 0;
        }

        // Register the operator
        try {
            $this->info('📝 Registering operator...');

            $syncAccount = MiraklOperatorRegistry::registerOperator(
                $config['operator_name'],
                $config
            );

            $this->info("✅ Operator '{$config['operator_name']}' registered successfully!");
            $this->line("   SyncAccount ID: {$syncAccount->id}");
            $this->line("   Display Name: {$syncAccount->display_name}");
            $this->line("   Channel: {$syncAccount->channel}");

            // Test connection
            $this->newLine();
            $this->info('🔗 Testing connection...');

            $wrapper = \App\Services\Mirakl\MiraklUniversalWrapper::fromSyncAccount($syncAccount);
            $connectionTest = $wrapper->testConnection();

            if ($connectionTest['success']) {
                $this->info('✅ Connection test successful!');

                // Get operator requirements
                $this->info('📋 Detecting operator capabilities...');
                $requirements = $wrapper->getRequirements();

                if (! isset($requirements['error'])) {
                    $this->displayCapabilities($requirements);
                } else {
                    $this->warn('⚠️ Could not detect all capabilities: '.$requirements['error']);
                }

            } else {
                $this->error('❌ Connection test failed: '.$connectionTest['message']);
                $this->warn('⚠️ Operator registered but connection issues detected');
            }

            $this->newLine();
            $this->info('🎯 Next Steps:');
            $this->line('• Test sync: php artisan mirakl:sync --operator='.$config['operator_name'].' --products=TEST-005 --dry-run');
            $this->line('• View stats: php artisan mirakl:sync --stats');
            $this->line('• Test connections: php artisan mirakl:sync --test-connections');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Failed to register operator: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * 📋 GATHER OPERATOR CONFIGURATION
     */
    protected function gatherOperatorConfig(): ?array
    {
        // Get operator name
        $operatorName = $this->argument('operator');
        if (! $operatorName) {
            $operatorName = $this->ask('Operator name (e.g., mybrand, newshop)');
        }

        if (! $operatorName) {
            return null;
        }

        // Check if operator already exists
        $existing = \App\Models\SyncAccount::where('channel', "mirakl_{$operatorName}")->first();
        if ($existing) {
            $this->error("❌ Operator '{$operatorName}' already exists!");
            $this->line("   SyncAccount ID: {$existing->id}");
            $this->line("   Status: {$existing->status}");

            return null;
        }

        $config = ['operator_name' => $operatorName];

        // Interactive mode or command line options
        if ($this->option('interactive') !== false) {
            return $this->interactiveConfig($config);
        } else {
            return $this->optionsConfig($config);
        }
    }

    /**
     * 💬 INTERACTIVE CONFIGURATION
     */
    protected function interactiveConfig(array $config): array
    {
        $this->info("📋 Configuring operator: {$config['operator_name']}");
        $this->newLine();

        // Basic required fields
        $config['base_url'] = $this->ask('Mirakl base URL (e.g., https://mybrand.mirakl.net)');
        $config['api_key'] = $this->secret('API key');
        $config['store_id'] = $this->ask('Store ID');

        // Optional fields
        $config['display_name'] = $this->ask('Display name', ucwords(str_replace('_', ' ', $config['operator_name'])));
        $config['default_category'] = $this->ask('Default category (leave empty for auto-detect)', 'auto-detect');
        $config['currency'] = $this->choice('Currency', ['GBP', 'USD', 'EUR'], 'GBP');
        $config['timeout'] = (int) $this->ask('API timeout (seconds)', '60');
        $config['auto_sync'] = $this->confirm('Enable auto-sync?', false);

        return $this->validateConfig($config);
    }

    /**
     * ⚙️ OPTIONS CONFIGURATION
     */
    protected function optionsConfig(array $config): array
    {
        $config['base_url'] = $this->option('base-url');
        $config['api_key'] = $this->option('api-key');
        $config['store_id'] = $this->option('store-id');
        $config['display_name'] = $this->option('display-name') ?? ucwords(str_replace('_', ' ', $config['operator_name']));
        $config['default_category'] = $this->option('category') ?? 'auto-detect';
        $config['auto_sync'] = $this->option('auto-sync');

        // Check for missing required fields
        $missing = [];
        foreach (['base_url', 'api_key', 'store_id'] as $field) {
            if (empty($config[$field])) {
                $missing[] = '--'.str_replace('_', '-', $field);
            }
        }

        if (! empty($missing)) {
            $this->error('❌ Missing required options: '.implode(', ', $missing));
            $this->info('Use --interactive for guided setup or provide all required options');

            return null;
        }

        return $this->validateConfig($config);
    }

    /**
     * ✅ VALIDATE CONFIGURATION
     */
    protected function validateConfig(array $config): array
    {
        $validator = Validator::make($config, [
            'operator_name' => 'required|string|alpha_dash|max:50',
            'base_url' => 'required|url',
            'api_key' => 'required|string|min:10',
            'store_id' => 'required|string|max:100',
            'display_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            $this->error('❌ Configuration validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->line("   • {$error}");
            }

            return null;
        }

        return $config;
    }

    /**
     * 📊 DISPLAY CONFIGURATION SUMMARY
     */
    protected function displayConfigSummary(array $config): void
    {
        $this->newLine();
        $this->info('📊 Configuration Summary:');
        $this->line("   Operator Name: {$config['operator_name']}");
        $this->line("   Display Name: {$config['display_name']}");
        $this->line("   Base URL: {$config['base_url']}");
        $this->line("   Store ID: {$config['store_id']}");
        $this->line('   API Key: '.str_repeat('*', strlen($config['api_key']) - 4).substr($config['api_key'], -4));
        $this->line("   Default Category: {$config['default_category']}");
        $this->line('   Currency: '.($config['currency'] ?? 'GBP'));
        $this->line('   Auto-sync: '.($config['auto_sync'] ? 'Yes' : 'No'));
        $this->newLine();
    }

    /**
     * 🔧 DISPLAY CAPABILITIES
     */
    protected function displayCapabilities(array $requirements): void
    {
        $this->info('🔧 Detected Capabilities:');
        $this->line("   Total Fields: {$requirements['total_attributes']}");
        $this->line("   Required Fields: {$requirements['required_attributes']}");
        $this->line("   Optional Fields: {$requirements['optional_attributes']}");
        $this->line("   Value Lists: {$requirements['value_lists_available']}");
        $this->line("   Default Category: {$requirements['default_category']}");

        if (isset($requirements['capabilities'])) {
            $this->line('   Features:');
            foreach ($requirements['capabilities'] as $capability => $available) {
                $status = $available ? '✅' : '❌';
                $this->line("     {$status} ".ucwords(str_replace('_', ' ', $capability)));
            }
        }
    }
}
