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
        if (! in_array($channel, ['shopify','ebay','amazon','mirakl'])) {
            throw ValidationException::withMessages([
                'channel' => ['Invalid or missing channel.'],
            ]);
        }
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => ['Account name is required.'],
            ]);
        }
        $rules = $this->registry->getValidationRules($channel, $operator);
        $validator = Validator::make(array_merge($credentials, $settings), $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Normalize settings with defaults
        $normalizedSettings = $this->normalizeSettings($channel, $settings);

        // Prepare attributes (used to find existing on create)
        $attributes = [
            'channel' => $channel,
            'name' => $name,
        ];

        // Determine platform and channel_code for explicit UI display
        $platform = $channel;
        if (in_array($channel, ['freemans', 'debenhams', 'bq'])) {
            $platform = 'mirakl';
        } elseif ($channel === 'mirakl' && $operator) {
            $platform = 'mirakl';
        }
        $channelCode = $operator ?: $channel;

        $payload = [
            'channel' => $channel,
            'name' => $name,
            'display_name' => $display,
            'is_active' => Arr::get($data, 'is_active', true),
            'credentials' => $this->normalizeCredentials($channel, $credentials),
            'settings' => $normalizedSettings,
            'platform' => $platform,
            'channel_code' => $channelCode,
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

        // Update top-level external shop snapshot if provided
        $shopId = $result['data']['shop']['id'] ?? $result['data']['shop_id'] ?? null;
        $shopName = $result['data']['shop']['name'] ?? $result['data']['shop_name'] ?? null;
        $health = ($result['success'] ?? false) ? 'healthy' : 'failing';

        $account->update([
            'external_shop_id' => $shopId ?: $account->external_shop_id,
            'external_shop_name' => $shopName ?: $account->external_shop_name,
            'health_status' => $health,
        ]);
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
