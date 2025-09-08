<?php

namespace App\Livewire\SyncAccounts;

use App\Models\SyncAccount;
use App\Services\Marketplace\MarketplaceRegistry;
use App\Services\Marketplace\SyncAccountService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Form extends Component
{
    public ?int $accountId = null;
    public string $mode = 'create'; // create|edit

    // Form fields
    public string $channel = '';
    public ?string $operator = null; // for mirakl subtype
    public string $name = '';
    public string $display_name = '';
    public array $credentials = [];
    public array $settings = [];

    public array $errorsBag = [];

    public function mount(?int $accountId = null)
    {
        $this->accountId = $accountId;
        if ($accountId) {
            $this->mode = 'edit';
            $account = SyncAccount::findOrFail($accountId);
            $this->authorize('update', $account);

            $this->channel = (string) ($account->channel ?? '');
            $this->operator = $account->marketplace_subtype;
            $this->name = $account->name;
            $this->display_name = $account->display_name;
            $this->credentials = $account->credentials ?? [];
            $this->settings = $account->settings ?? [];
        } else {
            $this->authorize('create', SyncAccount::class);
        }
    }

    public function save(SyncAccountService $service, MarketplaceRegistry $registry)
    {
        // Normalize inputs before validation to avoid case issues
        $this->channel = strtolower(trim((string) $this->channel));
        $this->operator = $this->operator !== null ? strtolower(trim((string) $this->operator)) : null;

        // Basic validation for required top-level fields
        $this->validate([
            'channel' => 'required|string|in:shopify,ebay,amazon,mirakl',
            'name' => 'required|string|min:1',
            'display_name' => 'nullable|string',
        ]);

        $data = [
            'channel' => $this->channel,
            'operator' => $this->operator,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'credentials' => $this->credentials,
            'settings' => $this->settings,
            'is_active' => true,
        ];

        try {
            $account = $this->accountId ? SyncAccount::findOrFail($this->accountId) : null;

            $this->authorize($this->accountId ? 'update' : 'create', $account ?? SyncAccount::class);

            $saved = $service->upsert($data, $account);
            $this->accountId = $saved->id;
            $this->mode = 'edit';

            $this->dispatch('toast', [
                'type' => 'success',
                'message' => 'Sync account saved successfully',
            ]);

            $this->dispatch('sync-account-saved', id: $saved->id);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->errorsBag = $e->errors();
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Please fix the highlighted errors.',
            ]);
        }
    }

    public function testConnection(SyncAccountService $service)
    {
        $account = $this->accountId ? SyncAccount::findOrFail($this->accountId) : null;
        if (! $account) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Save the account before testing connection.',
            ]);
            return;
        }

        $this->authorize('testConnection', $account);

        $this->dispatch('toast', [
            'type' => 'info',
            'message' => 'Testing connection...',
        ]);

        $result = $service->testConnection($account);

        if ($result['success'] ?? false) {
            $this->dispatch('toast', [
                'type' => 'success',
                'message' => 'Connection successful',
            ]);
        } else {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Connection failed: '.($result['error'] ?? 'Unknown error'),
            ]);
        }
    }

    public function render(MarketplaceRegistry $registry)
    {
        $existing = $this->accountId ? SyncAccount::find($this->accountId) : null;

        return view('livewire.sync-accounts.form', [
            'marketplaces' => $registry->getAvailableMarketplaces(),
            'requiredFields' => $registry->getRequiredFields($this->channel ?: 'mirakl', $this->operator),
            'validationRules' => $registry->getValidationRules($this->channel ?: 'mirakl', $this->operator),
            'credentialFieldMeta' => $registry->getCredentialFieldMeta($this->channel ?: 'mirakl'),
            'lastTest' => $existing?->connection_test_result,
            'lastTestAt' => $existing?->last_connection_test,
        ]);
    }
}
