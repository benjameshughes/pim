<?php

namespace App\Actions\Marketplace;

use App\Models\SyncAccount;
use App\Services\Marketplace\MarketplaceRegistry;
use App\ValueObjects\ConnectionTestResult;
use App\ValueObjects\MarketplaceCredentials;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🏪 BASE MARKETPLACE ACTION
 *
 * Base class for all marketplace actions following single responsibility principle.
 * Provides common functionality and transaction safety.
 */
abstract class BaseMarketplaceAction
{
    protected MarketplaceRegistry $marketplaceRegistry;

    public function __construct(MarketplaceRegistry $marketplaceRegistry)
    {
        $this->marketplaceRegistry = $marketplaceRegistry;
    }

    /**
     * 📝 LOG ACTION ACTIVITY
     */
    protected function logActivity(string $action, array $data = [], string $level = 'info'): void
    {
        Log::channel('marketplace')->{$level}("Marketplace Action: {$action}", [
            'action' => $action,
            'data' => $this->sanitizeLogData($data),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * 🔒 SANITIZE LOG DATA (Remove sensitive information)
     */
    protected function sanitizeLogData(array $data): array
    {
        $sensitiveKeys = ['api_key', 'access_token', 'client_secret', 'secret_key', 'password'];

        return collect($data)->map(function ($value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                return '[REDACTED]';
            }

            if (is_array($value)) {
                return $this->sanitizeLogData($value);
            }

            return $value;
        })->toArray();
    }

    /**
     * 🧪 VALIDATE MARKETPLACE CONFIGURATION
     */
    protected function validateMarketplaceConfiguration(string $marketplaceType, ?string $operator = null): bool
    {
        if (! $this->marketplaceRegistry->isSupported($marketplaceType)) {
            throw new \InvalidArgumentException("Marketplace type '{$marketplaceType}' is not supported.");
        }

        $template = $this->marketplaceRegistry->getMarketplaceTemplate($marketplaceType);

        // Special handling for Mirakl with dynamic operator detection
        if ($marketplaceType === 'mirakl') {
            // For Mirakl, operators are auto-detected dynamically, so any operator is valid
            // The operator validation happens during the fetch process in MiraklOperatorDetectionService
            return true;
        }

        // Standard validation for other marketplaces
        if ($operator && ! $template->hasOperators()) {
            throw new \InvalidArgumentException("Marketplace '{$marketplaceType}' does not support operators.");
        }

        if ($operator && $template->hasOperators() && ! in_array($operator, $template->getSupportedOperators())) {
            throw new \InvalidArgumentException("Operator '{$operator}' is not supported for marketplace '{$marketplaceType}'.");
        }

        return true;
    }

    /**
     * 🎯 VALIDATE REQUIRED FIELDS
     */
    protected function validateRequiredFields(array $credentials, string $marketplaceType, ?string $operator = null): bool
    {
        $requiredFields = $this->marketplaceRegistry->getRequiredFields($marketplaceType, $operator);

        foreach ($requiredFields as $field) {
            if (! isset($credentials[$field]) || empty($credentials[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing for marketplace '{$marketplaceType}'.");
            }
        }

        return true;
    }

    /**
     * 💾 EXECUTE WITH TRANSACTION SAFETY
     */
    protected function executeWithTransaction(callable $operation): mixed
    {
        return DB::transaction(function () use ($operation) {
            return $operation();
        });
    }

    /**
     * 🏗️ BUILD MARKETPLACE TEMPLATE CACHE
     */
    protected function buildMarketplaceTemplateCache(string $marketplaceType, ?string $operator = null): array
    {
        $template = $this->marketplaceRegistry->getMarketplaceTemplate($marketplaceType);

        if (! $template) {
            return [];
        }

        $templateData = $template->toArray();

        // Add operator-specific data if applicable
        if ($operator && $template->hasOperators()) {
            $operatorData = $template->supportedOperators[$operator] ?? [];
            $templateData['active_operator'] = $operator;
            $templateData['operator_config'] = $operatorData;
        }

        return $templateData;
    }

    /**
     * 🔍 FIND EXISTING ACCOUNT
     */
    protected function findExistingAccount(string $channel, string $name): ?SyncAccount
    {
        return SyncAccount::where('channel', $channel)
            ->where('name', $name)
            ->first();
    }

    /**
     * 🏷️ GENERATE UNIQUE ACCOUNT NAME
     */
    protected function generateUniqueAccountName(string $baseChannel, string $baseMarketplaceType): string
    {
        $baseName = $baseMarketplaceType;
        $counter = 1;

        while ($this->findExistingAccount($baseChannel, $baseName.($counter > 1 ? '_'.$counter : ''))) {
            $counter++;
        }

        return $baseName.($counter > 1 ? '_'.$counter : '');
    }

    /**
     * 🧪 CREATE CONNECTION TEST RESULT
     */
    protected function createConnectionTestResult(bool $success, string $message, array $details = []): ConnectionTestResult
    {
        if ($success) {
            return ConnectionTestResult::success($message, $details);
        }

        return ConnectionTestResult::failure($message, $details);
    }

    /**
     * 🏪 CREATE MARKETPLACE CREDENTIALS VALUE OBJECT
     */
    protected function createMarketplaceCredentials(string $type, array $credentials, array $settings = [], ?string $operator = null): MarketplaceCredentials
    {
        return new MarketplaceCredentials(
            type: $type,
            credentials: $credentials,
            settings: $settings,
            operator: $operator
        );
    }

    /**
     * 🔧 MERGE DEFAULT SETTINGS
     */
    protected function mergeDefaultSettings(string $marketplaceType, array $userSettings = []): array
    {
        $template = $this->marketplaceRegistry->getMarketplaceTemplate($marketplaceType);
        $defaultSettings = $template?->defaultSettings ?? [];

        return array_merge($defaultSettings, $userSettings);
    }
}
