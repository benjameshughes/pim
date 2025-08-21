<?php

namespace App\Services\Sync\Builders;

use App\Models\SyncAccount;
use App\Services\Marketplace\API\MarketplaceClient;

/**
 * 🌐 MIRAKL SYNC BUILDER
 *
 * Enhanced Mirakl integration with direct operator API support:
 * Sync::mirakl()->operators(['bq', 'debenhams'])->product($product)->push()
 */
class MiraklSyncBuilder extends BaseSyncBuilder
{
    /** @var array<string> */
    protected array $targetOperators = [];

    public function __construct()
    {
        parent::__construct();
    }

    protected function getChannelName(): string
    {
        return 'mirakl';
    }

    /**
     * 🎯 SPECIFY TARGET OPERATORS
     *
     * @param  array<string>  $operators
     */
    public function operators(array $operators): self
    {
        $this->targetOperators = $operators;

        return $this;
    }

    /**
     * 🚀 PUSH TO MIRAKL OPERATORS
     *
     * @return array<string, mixed>
     */
    public function push(): array
    {
        // Build products array from single product or collection
        $productsArray = [];

        if ($this->product) {
            $productsArray = [$this->product];
        } elseif (! $this->products->isEmpty()) {
            $productsArray = $this->products->toArray();
        }

        if (empty($productsArray)) {
            return [
                'success' => false,
                'error' => 'No products specified for Mirakl sync',
            ];
        }

        if (empty($this->targetOperators)) {
            // Default to all available Mirakl sync accounts
            $this->targetOperators = $this->getAvailableOperators();
        }

        $results = [];
        foreach ($this->targetOperators as $operator) {
            $account = SyncAccount::where('channel', 'mirakl')
                ->where('name', $operator)
                ->first();

            if (! $account) {
                $results[$operator] = [
                    'success' => false,
                    'error' => "No SyncAccount found for operator: {$operator}",
                ];

                continue;
            }

            try {
                $client = MarketplaceClient::for('mirakl')
                    ->withAccount($account)
                    ->withConfig(['operator' => $operator])
                    ->build();

                $response = $client->products()->create($productsArray)->execute();
                $results[$operator] = $response;
            } catch (\Exception $e) {
                $results[$operator] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $successCount = count(array_filter($results, fn ($r) => $r['success'] ?? false));

        return [
            'success' => $successCount > 0,
            'results' => $results,
            'successful_operators' => $successCount,
            'total_operators' => count($this->targetOperators),
        ];
    }

    /**
     * 🔄 PULL FROM MIRAKL (placeholder)
     *
     * @return array<string, mixed>
     */
    public function pull(): array
    {
        return [
            'success' => false,
            'message' => 'Mirakl pull functionality not yet implemented',
            'operators' => $this->targetOperators,
        ];
    }

    /**
     * ✅ TEST OPERATOR CONNECTIONS
     *
     * @return array<string, mixed>
     */
    public function testConnections(): array
    {
        $results = [];
        $operators = ! empty($this->targetOperators)
            ? $this->targetOperators
            : $this->getAvailableOperators();

        foreach ($operators as $operator) {
            $account = SyncAccount::where('channel', 'mirakl')
                ->where('name', $operator)
                ->first();

            if ($account) {
                try {
                    $client = MarketplaceClient::for('mirakl')
                        ->withAccount($account)
                        ->withConfig(['operator' => $operator])
                        ->build();

                    $testResult = $client->testConnection()->execute();
                    $results[$operator] = $testResult;
                } catch (\Exception $e) {
                    $results[$operator] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            } else {
                $results[$operator] = [
                    'success' => false,
                    'error' => "No SyncAccount found for operator: {$operator}",
                ];
            }
        }

        return [
            'success' => ! in_array(false, array_column($results, 'success')),
            'results' => $results,
            'tested_operators' => array_keys($results),
        ];
    }

    /**
     * 📋 Get available Mirakl operators from SyncAccounts
     */
    private function getAvailableOperators(): array
    {
        return SyncAccount::where('channel', 'mirakl')
            ->where('is_active', true)
            ->pluck('name')
            ->toArray();
    }

    /**
     * 📋 GET OPERATOR REQUIREMENTS
     *
     * @return array<string, mixed>
     */
    public function getRequirements(): array
    {
        $requirements = [];
        $operators = ! empty($this->targetOperators)
            ? $this->targetOperators
            : $this->getAvailableOperators();

        foreach ($operators as $operator) {
            $account = SyncAccount::where('channel', 'mirakl')
                ->where('name', $operator)
                ->first();

            if ($account) {
                try {
                    $client = MarketplaceClient::for('mirakl')
                        ->withAccount($account)
                        ->withConfig(['operator' => $operator])
                        ->build();

                    $requirements[$operator] = $client->getRequirements()->execute();
                } catch (\Exception $e) {
                    $requirements[$operator] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            } else {
                $requirements[$operator] = [
                    'success' => false,
                    'error' => "No SyncAccount found for operator: {$operator}",
                ];
            }
        }

        return $requirements;
    }
}
