<?php

namespace App\Actions\Marketplace;

use App\Models\SyncAccount;
use Illuminate\Support\Facades\Validator;

/**
 * ✨ CREATE MARKETPLACE INTEGRATION ACTION
 *
 * Creates a new marketplace integration with full validation and connection testing.
 * Follows single responsibility principle.
 */
class CreateMarketplaceIntegrationAction extends BaseMarketplaceAction
{
    /**
     * 🚀 EXECUTE: Create marketplace integration
     */
    public function execute(array $data): SyncAccount
    {
        $this->logActivity('create_integration_started', ['marketplace_type' => $data['marketplace_type']]);

        // Validate input data
        $this->validateInput($data);

        // Extract and validate marketplace configuration
        $marketplaceType = $data['marketplace_type'];
        $operator = $data['marketplace_subtype'] ?? null;
        $credentials = $data['credentials'];
        $displayName = $data['display_name'];

        // Validate marketplace configuration
        $this->validateMarketplaceConfiguration($marketplaceType, $operator);
        $this->validateRequiredFields($credentials, $marketplaceType, $operator);

        // Create the integration within a transaction
        return $this->executeWithTransaction(function () use ($marketplaceType, $operator, $credentials, $displayName, $data) {

            // Generate unique account name
            $accountName = $this->generateUniqueAccountName($marketplaceType, $marketplaceType);

            // Build marketplace template cache
            $templateCache = $this->buildMarketplaceTemplateCache($marketplaceType, $operator);

            // Merge default settings
            $settings = $this->mergeDefaultSettings($marketplaceType, $data['settings'] ?? []);

            // Create the SyncAccount
            $account = SyncAccount::create([
                'name' => $accountName,
                'channel' => $marketplaceType,
                'display_name' => $displayName,
                'is_active' => true,
                'credentials' => $credentials,
                'settings' => $settings,
                'marketplace_type' => $marketplaceType,
                'marketplace_subtype' => $operator,
                'marketplace_template' => $templateCache,
            ]);

            $this->logActivity('integration_created', [
                'account_id' => $account->id,
                'marketplace_type' => $marketplaceType,
                'operator' => $operator,
                'display_name' => $displayName,
            ]);

            return $account;
        });
    }

    /**
     * 🔍 VALIDATE INPUT DATA
     */
    private function validateInput(array $data): void
    {
        $validator = Validator::make($data, [
            'marketplace_type' => ['required', 'string'],
            'marketplace_subtype' => ['nullable', 'string'],
            'display_name' => ['required', 'string', 'max:255'],
            'credentials' => ['required', 'array'],
            'settings' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException(
                'Invalid input data: '.implode(', ', $validator->errors()->all())
            );
        }
    }

    /**
     * ✨ CREATE WITH CONNECTION TEST
     *
     * Creates integration and immediately tests the connection
     */
    public function createWithConnectionTest(array $data): array
    {
        // Create the integration
        $account = $this->execute($data);

        // Test the connection
        $testAction = new TestMarketplaceConnectionAction($this->marketplaceRegistry);
        $connectionResult = $testAction->execute($account);

        // Update account with connection test result
        $account->updateConnectionTestResult($connectionResult->toArray());

        return [
            'account' => $account->fresh(),
            'connection_test' => $connectionResult,
        ];
    }

    /**
     * 🔄 CREATE OR UPDATE INTEGRATION
     *
     * Creates new integration or updates existing one
     */
    public function createOrUpdate(array $data): array
    {
        $marketplaceType = $data['marketplace_type'];
        $operator = $data['marketplace_subtype'] ?? null;
        $displayName = $data['display_name'];

        // Check if integration already exists
        $existingAccount = SyncAccount::where('marketplace_type', $marketplaceType)
            ->where('marketplace_subtype', $operator)
            ->where('display_name', $displayName)
            ->first();

        if ($existingAccount) {
            // Update existing integration
            $updateAction = new UpdateMarketplaceIntegrationAction($this->marketplaceRegistry);
            $account = $updateAction->execute($existingAccount, $data);
            $isNew = false;
        } else {
            // Create new integration
            $account = $this->execute($data);
            $isNew = true;
        }

        return [
            'account' => $account,
            'is_new' => $isNew,
        ];
    }
}
