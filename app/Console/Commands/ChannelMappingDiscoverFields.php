<?php

namespace App\Console\Commands;

use App\Services\ChannelMapping\ChannelFieldDiscoveryService;
use Illuminate\Console\Command;

class ChannelMappingDiscoverFields extends Command
{
    protected $signature = 'channel-mapping:discover-fields
                            {--account= : Specific sync account ID to discover}
                            {--channel= : Specific channel type (mirakl, shopify, ebay, amazon)}
                            {--force : Force rediscovery even if recently discovered}';

    protected $description = 'Discover field requirements from marketplace APIs and store in channel mapping system';

    protected ChannelFieldDiscoveryService $discoveryService;

    public function __construct(ChannelFieldDiscoveryService $discoveryService)
    {
        parent::__construct();
        $this->discoveryService = $discoveryService;
    }

    public function handle(): int
    {
        $this->info('🔍 Starting Channel Field Discovery...');
        $this->newLine();

        try {
            $startTime = microtime(true);

            // Get discovery statistics before
            $statsBefore = $this->discoveryService->getDiscoveryStatistics();

            $this->info('📊 System Status Before Discovery:');
            $this->displayStats($statsBefore);
            $this->newLine();

            // Run discovery
            $results = $this->discoveryService->discoverAllChannels();

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Display results
            $this->info('✅ Discovery completed!');
            $this->newLine();

            $this->displayResults($results, $executionTime);

            // Get statistics after
            $statsAfter = $this->discoveryService->getDiscoveryStatistics();

            $this->newLine();
            $this->info('📊 System Status After Discovery:');
            $this->displayStats($statsAfter);

            // Show improvements
            $this->newLine();
            $this->displayImprovements($statsBefore, $statsAfter);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Discovery failed: {$e->getMessage()}");
            $this->newLine();

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    protected function displayStats(array $stats): void
    {
        $fieldStats = $stats['field_definitions'] ?? [];
        $valueListStats = $stats['value_lists'] ?? [];
        $health = $stats['discovery_health'] ?? [];

        $this->table(
            ['Metric', 'Count'],
            [
                ['Sync Accounts', $stats['sync_accounts'] ?? 0],
                ['Field Definitions', $fieldStats['total_fields'] ?? 0],
                ['Active Fields', $fieldStats['active_fields'] ?? 0],
                ['Required Fields', $fieldStats['required_fields'] ?? 0],
                ['Value Lists', $valueListStats['total_lists'] ?? 0],
                ['Synced Lists', $valueListStats['synced_lists'] ?? 0],
                ['Total Values', number_format($valueListStats['total_values'] ?? 0)],
                ['Health Score', round($health['overall_health']['score'] ?? 0, 1).'%'],
            ]
        );
    }

    protected function displayResults(array $results, float $executionTime): void
    {
        $summary = $results['summary'] ?? [];

        $this->table(
            ['Metric', 'Count'],
            [
                ['Accounts Processed', $results['processed_accounts'] ?? 0],
                ['Successful', $summary['successful'] ?? 0],
                ['Failed', $summary['failed'] ?? 0],
                ['Fields Discovered', $summary['total_fields'] ?? 0],
                ['Value Lists Discovered', $summary['total_value_lists'] ?? 0],
                ['Required Fields', $summary['total_required_fields'] ?? 0],
                ['Optional Fields', $summary['total_optional_fields'] ?? 0],
                ['Execution Time', $executionTime.'ms'],
            ]
        );

        // Show account-specific results
        if (isset($results['results']) && count($results['results']) > 0) {
            $this->newLine();
            $this->info('📋 Account Results:');

            $accountResults = [];
            foreach ($results['results'] as $accountResult) {
                $result = $accountResult['result'];
                $accountResults[] = [
                    $accountResult['channel'],
                    $result['success'] ? '✅ Success' : '❌ Failed',
                    $result['fields_discovered'] ?? 0,
                    $result['value_lists_discovered'] ?? 0,
                    isset($result['execution_time_ms']) ? $result['execution_time_ms'].'ms' : 'N/A',
                ];
            }

            $this->table(
                ['Channel', 'Status', 'Fields', 'Lists', 'Time'],
                $accountResults
            );
        }

        // Show errors if any
        if (isset($summary['errors']) && count($summary['errors']) > 0) {
            $this->newLine();
            $this->error('❌ Errors encountered:');
            foreach ($summary['errors'] as $error) {
                $this->error("  • {$error}");
            }
        }
    }

    protected function displayImprovements(array $before, array $after): void
    {
        $fieldsBefore = $before['field_definitions']['total_fields'] ?? 0;
        $fieldsAfter = $after['field_definitions']['total_fields'] ?? 0;
        $fieldsAdded = $fieldsAfter - $fieldsBefore;

        $listsBefore = $before['value_lists']['total_lists'] ?? 0;
        $listsAfter = $after['value_lists']['total_lists'] ?? 0;
        $listsAdded = $listsAfter - $listsBefore;

        $valuesBefore = $before['value_lists']['total_values'] ?? 0;
        $valuesAfter = $after['value_lists']['total_values'] ?? 0;
        $valuesAdded = $valuesAfter - $valuesBefore;

        $healthBefore = $before['discovery_health']['overall_health']['score'] ?? 0;
        $healthAfter = $after['discovery_health']['overall_health']['score'] ?? 0;
        $healthImproved = $healthAfter - $healthBefore;

        $this->info('📈 Improvements:');
        $this->table(
            ['Metric', 'Before', 'After', 'Change'],
            [
                ['Field Definitions', $fieldsBefore, $fieldsAfter, $this->formatChange($fieldsAdded)],
                ['Value Lists', $listsBefore, $listsAfter, $this->formatChange($listsAdded)],
                ['Total Values', number_format($valuesBefore), number_format($valuesAfter), $this->formatChange($valuesAdded)],
                ['Health Score', round($healthBefore, 1).'%', round($healthAfter, 1).'%', $this->formatChange($healthImproved, '%')],
            ]
        );
    }

    protected function formatChange(float $change, string $suffix = ''): string
    {
        if ($change > 0) {
            return "<fg=green>+{$change}{$suffix}</>";
        } elseif ($change < 0) {
            return "<fg=red>{$change}{$suffix}</>";
        } else {
            return "<fg=gray>0{$suffix}</>";
        }
    }
}
