<?php

namespace App\Services\Marketplace;

use App\Facades\Activity;
use App\Models\SyncAccount;
use App\Services\Marketplace\ValueObjects\SyncResult;
use App\ValueObjects\ConnectionTestResult;

/**
 * 🔎 ConnectionTestService
 *
 * Runs adapter-level connection tests and records lightweight health snapshots
 * into the SyncAccount settings (no new tables), plus Activity logs.
 */
class ConnectionTestService
{
    public function __construct(private readonly MarketplaceManager $manager)
    {
    }

    /**
     * Test connection for a single SyncAccount and record result.
     */
    public function testAndRecord(SyncAccount $account): ConnectionTestResult
    {
        $adapter = $this->manager->make($account->channel, $account->name);

        $started = (int) (microtime(true) * 1000);
        $result = $adapter->testConnection();
        $duration = (int) (microtime(true) * 1000) - $started;

        $ct = $this->toConnectionTestResult($result, $duration, $account);

        // Persist snapshot to settings
        $account->recordHealthCheck($ct);

        // Activity log
        Activity::log()
            ->customEvent('sync_account.connection_test', $account)
            ->with([
                'channel' => $account->channel,
                'account' => $account->name,
                'success' => $ct->success,
                'message' => $ct->message,
                'response_time_ms' => $ct->responseTime,
                'status_code' => $ct->statusCode,
            ])
            ->save();

        return $ct;
    }

    /**
     * Convert a SyncResult from an adapter into our ConnectionTestResult VO.
     */
    private function toConnectionTestResult(SyncResult $result, int $durationMs, SyncAccount $account): ConnectionTestResult
    {
        if ($result->isSuccess()) {
            return ConnectionTestResult::success(
                message: $result->getMessage(),
                details: array_merge($result->getData(), $result->getMetadata()),
                responseTime: $durationMs,
                endpoint: $result->getMetadata()['endpoint'] ?? null,
            );
        }

        return ConnectionTestResult::failure(
            message: $result->getMessage(),
            details: $result->getErrors(),
            recommendations: null,
            statusCode: $result->getMetadata()['status_code'] ?? null,
            endpoint: $result->getMetadata()['endpoint'] ?? null,
        );
    }
}

