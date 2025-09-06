<?php

namespace App\Livewire\Marketplace\Wizard;

use App\Services\Marketplace\MarketplaceRegistry;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Step2Configuration
 *
 * Renders registry-driven credential fields and validates them. On success,
 * dispatches configurationUpdated to the parent with normalized values.
 */
class Step2Configuration extends Component
{
    public string $selectedMarketplace = '';
    public ?string $selectedOperator = null;

    #[Validate('required|string|max:255')]
    public string $displayName = '';

    /** @var array<string,mixed> */
    public array $credentials = [];

    /** @var array<string,mixed> */
    public array $settings = [];

    /** @var array<string,mixed> */
    public array $validationRules = [];

    /** @var array<int,string> */
    public array $requiredFields = [];

    public function mount(
        string $selectedMarketplace,
        ?string $selectedOperator,
        string $displayName,
        array $credentials = [],
        array $settings = []
    ): void {
        $this->selectedMarketplace = $selectedMarketplace;
        $this->selectedOperator = $selectedOperator;
        $this->displayName = $displayName;
        $this->credentials = $credentials;
        $this->settings = $settings;

        $registry = app(MarketplaceRegistry::class);
        $this->validationRules = $registry->getValidationRules($selectedMarketplace, $selectedOperator ?: null);
        $this->requiredFields = $registry->getRequiredFields($selectedMarketplace, $selectedOperator ?: null);
    }

    /** Validate and emit updated configuration to parent. */
    public function continue(): void
    {
        $rules = [
            'displayName' => 'required|string|max:255',
        ];

        foreach ($this->validationRules as $field => $ruleSet) {
            $rules["credentials.{$field}"] = $ruleSet;
        }

        $this->normalize();
        $this->validate($rules);
        // keep this for completeness, but creation is gated by storeInfoFetched
        $this->dispatch('configurationUpdated',
            displayName: $this->displayName,
            credentials: $this->credentials
        );
    }

    public function create(): void
    {
        if (!$this->storeInfoFetched) {
            session()->flash('error', 'Please fetch store info before creating the integration.');
            return;
        }
        
        $this->dispatch('createIntegrationRequested');
    }

    /** Go back to previous step. */
    public function back(): void
    {
        $this->dispatch('previousStep');
    }

    /** Normalize marketplace-specific fields (e.g., shop domains). */
    protected function normalize(): void
    {
        if ($this->selectedMarketplace === 'shopify' && isset($this->credentials['store_url'])) {
            $this->credentials['store_url'] = $this->normalizeDomain((string) $this->credentials['store_url']);
        }
    }

    protected function normalizeDomain(string $value): string
    {
        $value = trim($value);
        if ($value === '') return $value;
        $value = preg_replace('/^https?:\\/\\//i', '', $value);
        $value = preg_replace('/[\\/].*$/', '', $value);
        return strtolower($value);
    }

    public function render()
    {
        return view('livewire.marketplace.wizard.step2-configuration');
    }

    // --- Mirakl helpers (optional fetch store info in Step 2) ---

    public function getCanFetchMiraklInfoProperty(): bool
    {
        return $this->selectedMarketplace === 'mirakl'
            && !empty($this->credentials['base_url'])
            && !empty($this->credentials['api_key'])
            && !($this->isLoading ?? false);
    }

    public function getCanCreateIntegrationProperty(): bool
    {
        // Can create if we have display name and all required credentials
        if (empty($this->displayName)) {
            return false;
        }

        foreach ($this->requiredFields as $field) {
            if (empty($this->credentials[$field])) {
                return false;
            }
        }

        // Don't allow creation if currently loading
        return !$this->isLoading;
    }

    public bool $isLoading = false;
    public bool $storeInfoFetched = false;

    public function fetchMiraklStoreInfo(): void
    {
        if ($this->selectedMarketplace !== 'mirakl') {
            return;
        }

        $baseUrl = $this->credentials['base_url'] ?? '';
        $apiKey = $this->credentials['api_key'] ?? '';
        if (empty($baseUrl) || empty($apiKey)) {
            session()->flash('error', 'Please provide both Base URL and API Key before fetching store information.');
            return;
        }

        $this->isLoading = true;
        try {
            $endpoint = rtrim($baseUrl, '/').'/api/account';
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->get($endpoint);

            if (!$response->successful()) {
                throw new \Exception('Mirakl API returned error: '.$response->status());
            }

            $data = $response->json();
            if (!isset($data['shop_name'], $data['shop_id'])) {
                throw new \Exception('Invalid response from Mirakl API. Shop information not found.');
            }

            $this->credentials['shop_name'] = $data['shop_name'];
            $this->credentials['shop_id'] = (string) $data['shop_id'];

            $this->settings['auto_fetched_data'] = [
                'shop_name' => $data['shop_name'],
                'shop_id' => $data['shop_id'],
                'currency' => $data['currency_iso_code'] ?? null,
                'shop_state' => $data['shop_state'] ?? null,
                'fetched_at' => now()->toISOString(),
            ];

            // Suggest a better display name if empty/generic
            if (empty($this->displayName) || str_contains(strtolower($this->displayName), 'integration')) {
                $this->displayName = $data['shop_name'].' (Mirakl)';
            }

            $this->storeInfoFetched = true;

            // Patch parent state with the new credentials/settings
            $this->dispatch('configurationPatched',
                displayName: $this->displayName,
                credentials: $this->credentials,
                settings: $this->settings
            );

            session()->flash('success', 'Store information fetched successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to fetch store information: '.$e->getMessage());
        }

        $this->isLoading = false;
    }

    // --- Shopify helpers ---
    public function fetchShopifyStoreInfo(): void
    {
        if ($this->selectedMarketplace !== 'shopify') {
            return;
        }

        $storeUrl = (string) ($this->credentials['store_url'] ?? '');
        $accessToken = (string) ($this->credentials['access_token'] ?? '');
        $apiVersion = (string) ($this->credentials['api_version'] ?? '2025-07');

        if ($storeUrl === '' || $accessToken === '') {
            session()->flash('error', 'Please provide both Store URL and Access Token before fetching store information.');
            return;
        }

        $this->isLoading = true;
        try {
            $normalized = $this->normalizeDomain($storeUrl);
            if (!str_contains($normalized, '.myshopify.com')) {
                $normalized .= '.myshopify.com';
            }
            $this->credentials['store_url'] = $normalized;

            $endpoint = "https://{$normalized}/admin/api/{$apiVersion}/shop.json";
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(10)->get($endpoint);

            if (!$response->successful()) {
                throw new \Exception('Shopify API returned error: '.$response->status());
            }

            $shop = $response->json('shop');
            if (!$shop || !isset($shop['name'], $shop['id'])) {
                throw new \Exception('Invalid response from Shopify API. Shop information not found.');
            }

            $this->credentials['shop_name'] = $shop['name'];
            $this->credentials['shop_id'] = (string) $shop['id'];
            $this->credentials['domain'] = $shop['domain'] ?? '';

            $this->settings['auto_fetched_data'] = [
                'shop_name' => $shop['name'],
                'shop_id' => $shop['id'],
                'domain' => $shop['domain'] ?? '',
                'currency' => $shop['currency'] ?? '',
                'timezone' => $shop['timezone'] ?? '',
                'plan_name' => $shop['plan_name'] ?? '',
                'api_version' => $apiVersion,
                'fetched_at' => now()->toISOString(),
            ];

            if (empty($this->displayName) || str_contains(strtolower($this->displayName), 'integration')) {
                $this->displayName = $shop['name'].' (Shopify)';
            }

            $this->storeInfoFetched = true;

            $this->dispatch('configurationPatched',
                displayName: $this->displayName,
                credentials: $this->credentials,
                settings: $this->settings
            );

            session()->flash('success', 'Store information fetched successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to fetch store information: '.$e->getMessage());
        }

        $this->isLoading = false;
    }
}
