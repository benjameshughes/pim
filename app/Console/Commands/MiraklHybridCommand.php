<?php

namespace App\Console\Commands;

use App\Services\Mirakl\MiraklConfigurationManager;
use App\Services\Mirakl\MiraklMigrationHelper;
use App\Services\Mirakl\MiraklSyncService;
use App\Services\Mirakl\MiraklUniversalWrapper;
use Illuminate\Console\Command;

class MiraklHybridCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mirakl:hybrid 
                            {action : Action to perform (status|migrate|rollback|test|config)}
                            {--operator= : Specific operator (freemans, debenhams, bq)}
                            {--percentage=10 : Migration percentage for gradual rollout}
                            {--mode= : Force specific mode (custom, sdk, hybrid)}
                            {--dry-run : Simulate actions without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage Mirakl Hybrid System: configuration, migration, and monitoring';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        $operator = $this->option('operator');

        $this->info('🌍 Mirakl Hybrid System Management');
        $this->line('=====================================');

        return match ($action) {
            'status' => $this->handleStatus($operator),
            'migrate' => $this->handleMigrate($operator),
            'rollback' => $this->handleRollback($operator),
            'test' => $this->handleTest($operator),
            'config' => $this->handleConfig($operator),
            default => $this->handleHelp(),
        };
    }

    /**
     * 📊 HANDLE STATUS ACTION
     */
    protected function handleStatus(?string $operator): int
    {
        if ($operator) {
            return $this->showOperatorStatus($operator);
        }

        $this->info('📊 Overall System Status');
        $this->line('');

        // Configuration summary
        $configSummary = MiraklConfigurationManager::getConfigurationSummary();
        $this->table(['Setting', 'Value'], [
            ['Default Mode', $configSummary['default_mode']],
            ['SDK Available', $configSummary['sdk_available'] ? '✅ Yes' : '❌ No'],
            ['Intelligent Features', $configSummary['intelligent_features_enabled'] ? '✅ Enabled' : '❌ Disabled'],
            ['Performance Monitoring', $configSummary['performance_monitoring'] ? '✅ Enabled' : '❌ Disabled'],
            ['Caching', $configSummary['caching_enabled'] ? '✅ Enabled' : '❌ Disabled'],
            ['Environment', $configSummary['environment']],
        ]);

        // Migration status for all operators
        $this->line('');
        $this->info('🔄 Migration Status');
        $migrationStatus = MiraklMigrationHelper::getAllMigrationsStatus();

        $operatorData = [];
        foreach ($migrationStatus['operators'] as $op => $status) {
            $operatorData[] = [
                $op,
                $status['status'],
                ($status['readiness_analysis']['ready_for_migration'] ?? false) ? '✅ Ready' : '❌ Not Ready',
                $status['metrics']['total_requests'] ?? 0,
                ($status['metrics']['error_rate'] ?? 0).'%',
            ];
        }

        $this->table(
            ['Operator', 'Migration Status', 'Ready', 'Requests', 'Error Rate'],
            $operatorData
        );

        return 0;
    }

    /**
     * 🚀 HANDLE MIGRATE ACTION
     */
    protected function handleMigrate(?string $operator): int
    {
        if (! $operator) {
            $this->error('❌ Operator required for migration. Use --operator=freemans');

            return 1;
        }

        $percentage = (int) $this->option('percentage');
        $dryRun = $this->option('dry-run');

        $this->info("🚀 Starting migration for {$operator}");

        if ($dryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
        }

        // Check readiness
        $readiness = MiraklMigrationHelper::analyzeMigrationReadiness($operator);

        if (! $readiness['ready_for_migration']) {
            $this->error("❌ {$operator} is not ready for migration");
            $this->line('');
            $this->warn('Issues to resolve:');

            foreach ($readiness['recommendations'] as $recommendation) {
                $this->line("  • {$recommendation}");
            }

            return 1;
        }

        if (! $dryRun) {
            $result = MiraklMigrationHelper::startGradualMigration($operator, $percentage);

            if ($result['success']) {
                $this->info("✅ {$result['message']}");
                $this->line('');
                $this->info('📋 Next Steps:');
                foreach ($result['next_steps'] as $step) {
                    $this->line("  • {$step}");
                }
            } else {
                $this->error("❌ {$result['message']}");

                return 1;
            }
        } else {
            $this->info("✅ Migration would start with {$percentage}% traffic routing");
        }

        return 0;
    }

    /**
     * 🔙 HANDLE ROLLBACK ACTION
     */
    protected function handleRollback(?string $operator): int
    {
        if (! $operator) {
            $this->error('❌ Operator required for rollback. Use --operator=freemans');

            return 1;
        }

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
            $this->info("Would rollback migration for {$operator}");

            return 0;
        }

        if ($this->confirm("Are you sure you want to rollback migration for {$operator}?")) {
            $result = MiraklMigrationHelper::rollbackMigration($operator, 'Manual rollback via command');

            if ($result['success']) {
                $this->info("✅ {$result['message']}");
                $this->warn("All requests now routed to: {$result['all_requests_routed_to']}");
            } else {
                $this->error('❌ Rollback failed');

                return 1;
            }
        }

        return 0;
    }

    /**
     * ✅ HANDLE TEST ACTION
     */
    protected function handleTest(?string $operator): int
    {
        if ($operator) {
            return $this->testOperator($operator);
        }

        $this->info('✅ Testing all operators');
        $results = MiraklSyncService::testAllConnections();

        $this->line('');
        $this->info("📊 Test Results: {$results['message']}");

        $testData = [];
        foreach ($results['results'] as $op => $result) {
            $testData[] = [
                $op,
                $result['success'] ? '✅ Pass' : '❌ Fail',
                $result['message'],
                $result['base_url'] ?? 'Not configured',
            ];
        }

        $this->table(
            ['Operator', 'Status', 'Message', 'Base URL'],
            $testData
        );

        return $results['success'] ? 0 : 1;
    }

    /**
     * ⚙️ HANDLE CONFIG ACTION
     */
    protected function handleConfig(?string $operator): int
    {
        if ($operator) {
            return $this->showOperatorConfig($operator);
        }

        $this->info('⚙️ Configuration Overview');

        $configs = MiraklConfigurationManager::getAllOperatorConfigs();

        foreach ($configs as $op => $config) {
            $this->line('');
            $this->info("📋 {$op} Configuration");

            $configData = [
                ['Default Mode', $config['default_mode']],
                ['Default Category', $config['default_category']],
                ['Timeout', $config['fallback']['timeout_multiplier'] ?? 60],
                ['Retry Attempts', $config['fallback']['retry_attempts'] ?? 3],
                ['Caching Enabled', $config['caching']['enable_caching'] ? 'Yes' : 'No'],
                ['Performance Monitoring', $config['performance']['enable_monitoring'] ? 'Yes' : 'No'],
            ];

            $this->table(['Setting', 'Value'], $configData);
        }

        return 0;
    }

    /**
     * 📋 SHOW OPERATOR STATUS
     */
    protected function showOperatorStatus(string $operator): int
    {
        $this->info("📊 {$operator} Status");

        $migrationStatus = MiraklMigrationHelper::getMigrationStatus($operator);
        $readiness = $migrationStatus['readiness_analysis'];

        // Basic status
        $statusData = [
            ['Migration Status', $migrationStatus['status']],
            ['Ready for Migration', $readiness['ready_for_migration'] ? '✅ Yes' : '❌ No'],
            ['Readiness Score', $readiness['readiness_score'].'/100'],
        ];

        if (isset($migrationStatus['configuration'])) {
            $config = $migrationStatus['configuration'];
            $statusData[] = ['Migration Percentage', $config['percentage'].'%'];
            $statusData[] = ['Started At', $config['started_at']];
        }

        $this->table(['Metric', 'Value'], $statusData);

        // Performance metrics
        if (isset($migrationStatus['metrics']) && $migrationStatus['metrics']['total_requests'] > 0) {
            $this->line('');
            $this->info('📊 Performance Metrics');

            $metrics = $migrationStatus['metrics'];
            $metricsData = [
                ['Total Requests', $metrics['total_requests']],
                ['Error Rate', $metrics['error_rate'].'%'],
                ['Performance Degradation', $metrics['performance_degradation'].'%'],
                ['Recent Failures', $metrics['recent_failure_count']],
            ];

            $this->table(['Metric', 'Value'], $metricsData);
        }

        // Safety check
        if (isset($migrationStatus['safety_check'])) {
            $this->line('');
            $safety = $migrationStatus['safety_check'];
            $this->info('🛡️ Safety Status: '.($safety['safe'] ? '✅ Safe' : '❌ Unsafe'));
            $this->line($safety['recommendation']);
        }

        return 0;
    }

    /**
     * ✅ TEST OPERATOR
     */
    protected function testOperator(string $operator): int
    {
        $this->info("✅ Testing {$operator}");

        $mode = $this->option('mode');
        $options = $mode ? ['mode' => $mode] : [];

        try {
            $wrapper = MiraklUniversalWrapper::forOperator($operator, $options);
            $result = $wrapper->testConnection();

            if ($result['success']) {
                $this->info('✅ Connection successful');
                $this->line("Base URL: {$result['base_url']}");
                $this->line("Store ID: {$result['store_id']}");
            } else {
                $this->error("❌ Connection failed: {$result['message']}");

                return 1;
            }

            // Test requirements
            $requirements = $wrapper->getRequirements();
            if (! isset($requirements['error'])) {
                $this->line('');
                $this->info('📋 Operator Requirements');
                $this->line("Total Fields: {$requirements['total_attributes']}");
                $this->line("Required Fields: {$requirements['required_attributes']}");
                $this->line("Optional Fields: {$requirements['optional_attributes']}");
                $this->line("Value Lists: {$requirements['value_lists_available']}");
            }

        } catch (\Exception $e) {
            $this->error("❌ Test failed: {$e->getMessage()}");

            return 1;
        }

        return 0;
    }

    /**
     * ⚙️ SHOW OPERATOR CONFIG
     */
    protected function showOperatorConfig(string $operator): int
    {
        $this->info("⚙️ {$operator} Configuration");

        $config = MiraklConfigurationManager::getOperatorConfig($operator);
        $recommendation = MiraklConfigurationManager::getRecommendedMode($operator);

        $configData = [
            ['Current Mode', $config['default_mode']],
            ['Recommended Mode', $recommendation['recommended_mode']],
            ['Default Category', $config['default_category']],
            ['Base URL', $config['base_url'] ?? 'Not configured'],
            ['Store ID', $config['store_id'] ?? 'Not configured'],
            ['Timeout', $config['timeout']],
            ['Retry Attempts', $config['retry_attempts']],
            ['Caching', $config['enable_caching'] ? 'Enabled' : 'Disabled'],
            ['Monitoring', $config['enable_monitoring'] ? 'Enabled' : 'Disabled'],
        ];

        $this->table(['Setting', 'Value'], $configData);

        if (! empty($recommendation['reasons'])) {
            $this->line('');
            $this->info('💡 Recommendations:');
            foreach ($recommendation['reasons'] as $reason) {
                $this->line("  • {$reason}");
            }
        }

        return 0;
    }

    /**
     * ❓ SHOW HELP
     */
    protected function handleHelp(): int
    {
        $this->info('🌍 Mirakl Hybrid System Commands');
        $this->line('');
        $this->line('Available actions:');
        $this->line('  status   - Show system and operator status');
        $this->line('  migrate  - Start gradual migration to hybrid mode');
        $this->line('  rollback - Rollback migration to custom mode');
        $this->line('  test     - Test operator connections and capabilities');
        $this->line('  config   - Show configuration details');
        $this->line('');
        $this->line('Examples:');
        $this->line('  php artisan mirakl:hybrid status');
        $this->line('  php artisan mirakl:hybrid test --operator=freemans');
        $this->line('  php artisan mirakl:hybrid migrate --operator=freemans --percentage=25');
        $this->line('  php artisan mirakl:hybrid config --operator=debenhams');

        return 0;
    }
}
