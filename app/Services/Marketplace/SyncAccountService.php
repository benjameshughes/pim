<?php

namespace App\Services\Marketplace;

use App\Models\SyncAccount;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SyncAccountService
{
    public function __construct(private readonly MarketplaceRegistry $registry)
    {
    }

    /**
     * Create or update a SyncAccount with normalized credentials/settings
     *
     * @param  array{channel:string,name:string,display_name?:string,credentials?:array,settings?:array,operator?:string}  $data
     */
    public function upsert(array $data, ?SyncAccount $account = null): SyncAccount
    {
        $channel = strtolower($data['channel'] ?? '');
        $name = $data['name'] ?? '';
        $display = $data['display_name'] ?? ($channel.' '.$name);
        $credentials = $data['credentials'] ?? [];
        $settings = $data['settings'] ?? [];
        $operator = $data['operator'] ?? null; // for mirakl subtype if needed

        // Validate
        $rules = $this->registry->getValidationRules($channel, $operator);
        $validator = Validator::make(array_merge($credentials, $settings), $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Normalize settings with defaults
        $normalizedSettings = $this->normalizeSettings($channel, $settings);

        // Prepare attributes
        $attributes = [
            'channel' => $channel,
            'name' => $name,
        ];

        $payload = [
            'display_name' => $display,
            'is_active' => Arr::get($data, 'is_active', true),
            'credentials' => $this->normalizeCredentials($channel, $credentials),
            'settings' => $normalizedSettings,
        ];

        if ($operator && $channel === 'mirakl') {
            $payload['marketplace_subtype'] = $operator;
        }

        if ($account) {
            $account->update($payload);
            $account->refresh();
            \App\Events\SyncAccounts\SyncAccountUpdated::dispatch($account);

            return $account;
        }

        $saved = SyncAccount::updateOrCreate($attributes, $payload);
        if ($saved->wasRecentlyCreated) {
            \App\Events\SyncAccounts\SyncAccountCreated::dispatch($saved);
        } else {
            \App\Events\SyncAccounts\SyncAccountUpdated::dispatch($saved);
        }

        return $saved;
    }

    public function testConnection(SyncAccount $account): array
    {
        // Dispatch to marketplace client via facade-style builder
        $service = \App\Services\Marketplace\API\MarketplaceClient::for($account->marketplace_type ?? $account->channel)
            ->withAccount($account)
            ->build();

        $result = $service->testConnection();

        // Persist last test result for UI
        $account->updateConnectionTestResult($result);
        \App\Events\SyncAccounts\SyncAccountTested::dispatch($account, $result);

        return $result;
    }

    protected function normalizeCredentials(string $channel, array $credentials): array
    {
        // Minimal: return as-is; can add key normalization per channel
        return $credentials;
    }

    protected function normalizeSettings(string $channel, array $settings): array
    {
        // Apply defaults from registry if not present
        $template = $this->registry->getMarketplaceTemplate($channel);
        $defaults = $template?->defaultSettings ?? [];

        return array_replace_recursive($defaults, $settings);
    }
}
