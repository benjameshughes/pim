<?php

namespace App\Console\Commands;

use App\Services\BarcodePoolOptimizationService;
use Illuminate\Console\Command;

class TestBarcodePerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'barcode:test-performance 
                            {--generate=0 : Generate test data (number of records)}
                            {--cleanup= : Cleanup test data by batch ID}
                            {--report : Generate performance report}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test barcode pool performance with large datasets';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('cleanup')) {
            return $this->handleCleanup();
        }

        if ($this->option('generate') > 0) {
            return $this->handleGenerate();
        }

        if ($this->option('report')) {
            return $this->handleReport();
        }

        $this->info('Barcode Pool Performance Testing Tool');
        $this->newLine();
        $this->line('Available options:');
        $this->line('  --generate=10000    Generate test data (default: 10,000 records)');
        $this->line('  --cleanup=batch_id  Cleanup test data by batch ID');
        $this->line('  --report            Generate performance report');
        $this->newLine();
        $this->line('Examples:');
        $this->line('  php artisan barcode:test-performance --generate=50000');
        $this->line('  php artisan barcode:test-performance --report');
        $this->line('  php artisan barcode:test-performance --cleanup=perf_test_1722759975');
    }

    private function handleGenerate(): int
    {
        $count = (int) $this->option('generate');

        $this->info("Generating {$count} test barcode records...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $result = BarcodePoolOptimizationService::generateTestData($count);

        $bar->finish();
        $this->newLine(2);

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $this->info("✅ Generated {$result['created']} records");
        $this->line("📦 Batch ID: {$result['batch_id']}");
        $this->line('⏱️  Time: '.round($endTime - $startTime, 2).' seconds');
        $this->line('💾 Memory: '.round(($endMemory - $startMemory) / 1024 / 1024, 2).' MB');

        $this->newLine();
        $this->line("To cleanup: php artisan barcode:test-performance --cleanup={$result['batch_id']}");

        return self::SUCCESS;
    }

    private function handleCleanup(): int
    {
        $batchId = $this->option('cleanup');

        $this->info("Cleaning up test data for batch: {$batchId}");

        $deleted = BarcodePoolOptimizationService::cleanupTestData($batchId);

        $this->info("✅ Deleted {$deleted} test records");

        return self::SUCCESS;
    }

    private function handleReport(): int
    {
        $this->info('Generating performance report...');
        $this->newLine();

        $report = BarcodePoolOptimizationService::generatePerformanceReport();

        $this->line('📊 <comment>Performance Report</comment>');
        $this->line("Generated at: {$report['timestamp']}");
        $this->newLine();

        $this->line('📈 <info>Database Statistics</info>');
        $this->line('Total records: '.number_format($report['database_size']));
        $this->newLine();

        $this->line('💾 <info>Memory Analysis</info>');
        foreach ($report['memory_analysis'] as $key => $value) {
            $label = ucfirst(str_replace('_', ' ', $key));
            $this->line("{$label}: {$value}");
        }
        $this->newLine();

        $this->line('⭐ <info>Efficiency Score</info>');
        $color = match ($report['efficiency_score']) {
            'Excellent' => 'green',
            'Good' => 'blue',
            'Average' => 'yellow',
            'Poor' => 'red',
            default => 'white'
        };
        $this->line("<fg={$color}>{$report['efficiency_score']}</fg>");
        $this->newLine();

        $this->line('🎯 <info>Recommendations</info>');
        foreach ($report['recommendations'] as $recommendation) {
            $this->line("• {$recommendation}");
        }

        return self::SUCCESS;
    }
}
