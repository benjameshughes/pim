<?php

namespace App\Actions\Marketplace;

use App\Models\SyncAccount;
use Illuminate\Support\Facades\Validator;

/**
 * ✏️ UPDATE MARKETPLACE INTEGRATION ACTION
 *
 * Updates an existing marketplace integration with validation and connection testing.
 * Follows single responsibility principle.
 */
class UpdateMarketplaceIntegrationAction extends BaseMarketplaceAction
{
    /**
     * 🔄 EXECUTE: Update marketplace integration
     */
    public function execute(SyncAccount $account, array $data): SyncAccount
    {
        $this->logActivity('update_integration_started', [
            'account_id' => $account->id,
            'marketplace_type' => $account->marketplace_type,
        ]);

        // Validate input data
        $this->validateInput($data);

        // Extract marketplace configuration
        $marketplaceType = $data['marketplace_type'] ?? $account->marketplace_type;
        $operator = $data['marketplace_subtype'] ?? $account->marketplace_subtype;
        $credentials = $data['credentials'] ?? $account->credentials;

        // Validate marketplace configuration if changed
        if ($marketplaceType !== $account->marketplace_type || $operator !== $account->marketplace_subtype) {
            $this->validateMarketplaceConfiguration($marketplaceType, $operator);
        }

        // Validate required fields
        $this->validateRequiredFields($credentials, $marketplaceType, $operator);

        // Update the integration within a transaction
        return $this->executeWithTransaction(function () use ($account, $data, $marketplaceType, $operator, $credentials) {

            // Build updated marketplace template cache
            $templateCache = $this->buildMarketplaceTemplateCache($marketplaceType, $operator);

            // Merge settings (preserve existing + add new)
            $existingSettings = $account->settings ?? [];
            $newSettings = $data['settings'] ?? [];
            $mergedSettings = $this->mergeDefaultSettings($marketplaceType, array_merge($existingSettings, $newSettings));

            // Prepare update data
            $updateData = [
                'display_name' => $data['display_name'] ?? $account->display_name,
                'credentials' => $credentials,
                'settings' => $mergedSettings,
                'marketplace_type' => $marketplaceType,
                'marketplace_subtype' => $operator,
                'marketplace_template' => $templateCache,
                'is_active' => $data['is_active'] ?? $account->is_active,
            ];

            // Clear connection test results if credentials changed
            if ($credentials !== $account->credentials) {
                $updateData['connection_test_result'] = null;
                $updateData['last_connection_test'] = null;

                $this->logActivity('credentials_updated', [
                    'account_id' => $account->id,
                    'marketplace_type' => $marketplaceType,
                ]);
            }

            // Update the account
            $account->update($updateData);

            $this->logActivity('integration_updated', [
                'account_id' => $account->id,
                'marketplace_type' => $marketplaceType,
                'operator' => $operator,
                'changes' => array_keys($updateData),
            ]);

            return $account->fresh();
        });
    }

    /**
     * 🔍 VALIDATE INPUT DATA
     */
    private function validateInput(array $data): void
    {
        $validator = Validator::make($data, [
            'marketplace_type' => ['sometimes', 'string'],
            'marketplace_subtype' => ['nullable', 'string'],
            'display_name' => ['sometimes', 'string', 'max:255'],
            'credentials' => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException(
                'Invalid input data: '.implode(', ', $validator->errors()->all())
            );
        }
    }

    /**
     * ✨ UPDATE WITH CONNECTION TEST
     *
     * Updates integration and immediately tests the connection
     */
    public function updateWithConnectionTest(SyncAccount $account, array $data): array
    {
        // Update the integration
        $updatedAccount = $this->execute($account, $data);

        // Test the connection if credentials were changed
        $connectionResult = null;
        if (isset($data['credentials']) && $data['credentials'] !== $account->credentials) {
            $testAction = new TestMarketplaceConnectionAction($this->marketplaceRegistry);
            $connectionResult = $testAction->execute($updatedAccount);

            // Update account with connection test result
            $updatedAccount->updateConnectionTestResult($connectionResult->toArray());
            $updatedAccount = $updatedAccount->fresh();
        }

        return [
            'account' => $updatedAccount,
            'connection_test' => $connectionResult,
            'credentials_changed' => isset($data['credentials']),
        ];
    }

    /**
     * 🔄 UPDATE SETTINGS ONLY
     *
     * Updates only the settings without touching credentials
     */
    public function updateSettingsOnly(SyncAccount $account, array $settings): SyncAccount
    {
        $this->logActivity('update_settings_only', [
            'account_id' => $account->id,
            'settings_keys' => array_keys($settings),
        ]);

        return $this->executeWithTransaction(function () use ($account, $settings) {
            $existingSettings = $account->settings ?? [];
            $mergedSettings = array_merge($existingSettings, $settings);

            $account->update(['settings' => $mergedSettings]);

            return $account->fresh();
        });
    }

    /**
     * 🎯 UPDATE STATUS ONLY
     *
     * Updates only the active status
     */
    public function updateStatus(SyncAccount $account, bool $isActive): SyncAccount
    {
        $this->logActivity('update_status', [
            'account_id' => $account->id,
            'new_status' => $isActive ? 'active' : 'inactive',
            'previous_status' => $account->is_active ? 'active' : 'inactive',
        ]);

        return $this->executeWithTransaction(function () use ($account, $isActive) {
            $account->update(['is_active' => $isActive]);

            return $account->fresh();
        });
    }
}
