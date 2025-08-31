<?php

namespace App\Livewire\SyncAccounts;

use App\Models\SyncAccount;
use App\Services\EbayConnectService;
use App\Services\Shopify\API\CategoryApi;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
class CreateSyncAccount extends Component
{
    #[Validate('required')]
    public string $channel = '';

    #[Validate('required|string|min:2')]
    public string $display_name = '';

    #[Validate('required|string')]
    public string $marketplace_subtype = '';

    // Shopify fields
    #[Validate('required_if:channel,shopify')]
    public string $shop_domain = '';

    #[Validate('required_if:channel,shopify')]
    public string $access_token = '';

    public string $api_version = '2025-07';

    // eBay fields
    #[Validate('required_if:channel,ebay')]
    public string $client_id = '';

    #[Validate('required_if:channel,ebay')]
    public string $client_secret = '';

    #[Validate('required_if:channel,ebay')]
    public string $dev_id = '';

    public string $environment = 'SANDBOX';

    // Test results
    public bool $isLoading = false;

    public bool $hasTestedConnection = false;

    public bool $connectionSuccessful = false;

    public string $testError = '';

    public array $testResults = [];

    public function mount()
    {
        // Authorize creating marketplace connections
        $this->authorize('manage-marketplace-connections');

        // Pre-fill some defaults
        $this->api_version = '2025-07';
        $this->environment = 'SANDBOX';
    }

    public function updatedChannel()
    {
        // Reset form when channel changes
        $this->reset([
            'shop_domain', 'access_token', 'client_id', 'client_secret',
            'dev_id', 'hasTestedConnection', 'connectionSuccessful',
            'testError', 'testResults',
        ]);

        // Set some defaults based on channel
        if ($this->channel === 'shopify') {
            $this->marketplace_subtype = '';
        } elseif ($this->channel === 'ebay') {
            $this->marketplace_subtype = 'ebay';
        }
    }

    public function updatedShopDomain()
    {
        // Auto-fill marketplace_subtype for Shopify
        if ($this->channel === 'shopify' && $this->shop_domain) {
            $this->marketplace_subtype = str_replace('.myshopify.com', '', $this->shop_domain);

            // Auto-generate display name if empty
            if (empty($this->display_name)) {
                $this->display_name = ucfirst($this->marketplace_subtype).' Shopify Store';
            }
        }
    }

    public function testConnection()
    {
        $this->validate();

        $this->isLoading = true;
        $this->hasTestedConnection = false;
        $this->connectionSuccessful = false;
        $this->testError = '';
        $this->testResults = [];

        try {
            if ($this->channel === 'shopify') {
                $result = $this->testShopifyConnection();
            } elseif ($this->channel === 'ebay') {
                $result = $this->testEbayConnection();
            } else {
                throw new \Exception('Unsupported channel: '.$this->channel);
            }

            $this->hasTestedConnection = true;
            $this->connectionSuccessful = $result['success'];

            if ($result['success']) {
                $this->testResults = $result;
                $this->dispatch('success', 'Connection test successful! You can now create the sync account.');
            } else {
                $this->testError = $result['error'];
                $this->dispatch('error', 'Connection test failed: '.$result['error']);
            }

        } catch (\Exception $e) {
            $this->hasTestedConnection = true;
            $this->connectionSuccessful = false;
            $this->testError = $e->getMessage();
            $this->dispatch('error', 'Connection test failed: '.$e->getMessage());
        } finally {
            $this->isLoading = false;
        }
    }

    private function testShopifyConnection(): array
    {
        // Create temporary sync account for testing
        $tempCredentials = [
            'shop_domain' => $this->shop_domain,
            'access_token' => $this->access_token,
            'api_version' => $this->api_version,
        ];

        // Create a temporary SyncAccount for testing
        $tempSyncAccount = new SyncAccount([
            'channel' => 'shopify',
            'marketplace_subtype' => $this->marketplace_subtype,
            'credentials' => $tempCredentials,
            'is_active' => true,
        ]);
        $tempSyncAccount->save();

        try {
            // Test the connection using CategoryApi
            $categoryApi = new CategoryApi($this->shop_domain);
            $result = $categoryApi->testConnection();

            if ($result['success']) {
                // Get some additional info for display
                $result['shop_info'] = $result['shop_data'];
                $result['credentials_valid'] = true;
            }

            return $result;

        } finally {
            // Clean up temporary account
            $tempSyncAccount->delete();
        }
    }

    private function testEbayConnection(): array
    {
        try {
            $ebayService = new EbayConnectService;

            // Test eBay connection with provided credentials
            $config = [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'dev_id' => $this->dev_id,
                'environment' => $this->environment,
            ];

            $result = $ebayService->testConnection($config);

            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function create()
    {
        if (! $this->hasTestedConnection || ! $this->connectionSuccessful) {
            $this->dispatch('error', 'Please test the connection successfully before creating the sync account.');

            return;
        }

        $this->validate();

        try {
            $credentials = $this->buildCredentials();
            $settings = $this->buildSettings();

            $syncAccount = SyncAccount::create([
                'channel' => $this->channel,
                'display_name' => $this->display_name,
                'marketplace_subtype' => $this->marketplace_subtype,
                'credentials' => $credentials,
                'settings' => $settings,
                'is_active' => true,
            ]);

            $this->dispatch('success', 'Sync account created successfully!');

            // Redirect to the sync account details or list
            return $this->redirect('/sync-accounts/'.$syncAccount->id);

        } catch (\Exception $e) {
            $this->dispatch('error', 'Failed to create sync account: '.$e->getMessage());
        }
    }

    private function buildCredentials(): array
    {
        if ($this->channel === 'shopify') {
            return [
                'shop_domain' => $this->shop_domain,
                'access_token' => $this->access_token,
                'api_version' => $this->api_version,
            ];
        } elseif ($this->channel === 'ebay') {
            return [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'dev_id' => $this->dev_id,
                'environment' => $this->environment,
            ];
        }

        return [];
    }

    private function buildSettings(): array
    {
        $settings = [];

        // Add test results to settings for reference
        if (! empty($this->testResults)) {
            $settings['connection_test'] = [
                'tested_at' => now()->toISOString(),
                'results' => $this->testResults,
            ];
        }

        if ($this->channel === 'shopify' && ! empty($this->testResults['shop_info'])) {
            $settings['auto_fetched_data'] = $this->testResults['shop_info'];
        }

        return $settings;
    }

    public function getChannelOptions(): array
    {
        return [
            'shopify' => 'Shopify',
            'ebay' => 'eBay',
            // 'mirakl' => 'Mirakl', // Add when needed
        ];
    }

    public function render()
    {
        return view('livewire.sync-accounts.create-sync-account');
    }
}
