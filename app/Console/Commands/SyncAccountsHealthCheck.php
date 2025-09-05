<?php

namespace App\Console\Commands;

use App\Models\SyncAccount;
use App\Services\Marketplace\ConnectionTestService;
use Illuminate\Console\Command;

class SyncAccountsHealthCheck extends Command
{
    protected $signature = 'sync-accounts:health-check {--force : Run even if not stale} {--max-age=24 : Hours before a check is considered stale} {--channel= : Limit to a single channel}';

    protected $description = 'Run connection health checks for marketplace sync accounts and record results';

    public function handle(ConnectionTestService $service): int
    {
        $maxAge = (int) $this->option('max-age');
        $force = (bool) $this->option('force');
        $channel = $this->option('channel');

        $query = SyncAccount::query()->active();
        if ($channel) {
            $query->where('channel', $channel);
        }

        $accounts = $query->get();
        if ($accounts->isEmpty()) {
            $this->warn('No active sync accounts found');
            return self::SUCCESS;
        }

        $this->info(sprintf('Running health checks for %d account(s)...', $accounts->count()));

        $bar = $this->output->createProgressBar($accounts->count());
        $bar->start();

        foreach ($accounts as $account) {
            if (! $force && ! $account->isHealthCheckStale($maxAge)) {
                $bar->advance();
                continue;
            }

            $result = $service->testAndRecord($account);
            $status = $result->success ? '✅' : '❌';
            $this->line(sprintf("\n%s %s/%s - %s (%s)", $status, $account->channel, $account->name, $result->message, ($result->responseTime ? $result->responseTime.'ms' : 'n/a')));

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Health checks completed.');

        return self::SUCCESS;
    }
}

