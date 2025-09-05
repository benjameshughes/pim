<?php

namespace App\Livewire\Marketplace;

use App\Models\SyncAccount;
use App\Services\Marketplace\Facades\Sync;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * 🏷️ MARKETPLACE IDENTIFIERS DASHBOARD
 *
 * Unified dashboard for managing marketplace identifiers and account details.
 * Replaces the SKU linking system with a cleaner identifier management approach.
 */
#[Layout('components.layouts.app')]
class IdentifiersDashboard extends Component
{
    public ?int $selectedAccount = null;

    // No service/actions injection needed; we use the fluent Sync API for clarity

    /**
     * 📊 DASHBOARD STATS
     *
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'total_accounts' => SyncAccount::active()->count(),
            'configured_accounts' => SyncAccount::withIdentifiers()->count(),
            'pending_setup' => SyncAccount::needingIdentifierSetup()->count(),
            'channels' => SyncAccount::active()->distinct('channel')->count('channel'),
        ];
    }

    /**
     * 🏪 MARKETPLACE ACCOUNTS
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SyncAccount>
     */
    #[Computed]
    public function accounts(): \Illuminate\Database\Eloquent\Collection
    {
        return SyncAccount::active()
            ->orderBy('channel')
            ->orderBy('display_name')
            ->get();
    }

    /**
     * 🔍 SELECTED ACCOUNT DETAILS
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function selectedAccountData(): ?array
    {
        if (! $this->selectedAccount) {
            return null;
        }

        $account = SyncAccount::find($this->selectedAccount);
        if (! $account) {
            return null;
        }

        return [
            'account' => $account,
            'display_info' => $account->getMarketplaceDisplayInfo(),
            'identifiers' => $account->getMarketplaceIdentifiers(),
            'details' => $account->getMarketplaceDetails(),
            'identifier_types' => $account->getAvailableIdentifierTypes(),
        ];
    }

    /**
     * 🏪 ACCOUNT SELECTION HANDLER
     */
    public function selectAccount(int $accountId): void
    {
        $this->selectedAccount = $accountId;
        $this->dispatch('success', 'Account selected! 🎯');
    }

    /** Delete sync account cause why not */
    public function deleteAccount(SyncAccount $account): void
    {
        // I should really add permissions and checks for stuff.
        // I like actions so let's do the action pattern... Erm, I forgot how to do the action pattern. My bad. I just did the normal laravel way... Fix later? Cause I be bothered?
        $account->delete();
        $this->dispatch('success', 'Account deleted!');
    }

    /**
     * 🚀 SETUP IDENTIFIERS FOR ACCOUNT
     */
    public function setupIdentifiers(int $accountId): void
    {
        $account = SyncAccount::find($accountId);
        if (! $account) {
            $this->dispatch('error', 'Account not found');

            return;
        }

        try {
            $result = Sync::marketplace($account->channel)
                ->account($account->name)
                ->setupIdentifiers();

            if ($result['success']) {
                $this->dispatch('success', $result['summary'] ?? 'Identifiers setup successfully! ✅');

                // Refresh the selected account data
                $this->dispatch('$refresh');
            } else {
                $this->dispatch('error', $result['error'] ?? 'Setup failed');
            }

        } catch (\Exception $e) {
            $this->dispatch('error', "Setup failed: {$e->getMessage()}");
        }
    }

    /**
     * 🔄 REFRESH IDENTIFIERS FOR ACCOUNT
     */
    public function refreshIdentifiers(int $accountId): void
    {
        $account = SyncAccount::find($accountId);
        if (! $account) {
            $this->dispatch('error', 'Account not found');

            return;
        }

        try {
            $result = Sync::marketplace($account->channel)
                ->account($account->name)
                ->refreshIdentifiers();

            if ($result['success']) {
                $this->dispatch('success', 'Identifiers refreshed successfully! 🔄');

                // Refresh the selected account data
                $this->dispatch('$refresh');
            } else {
                $this->dispatch('error', $result['error'] ?? 'Refresh failed');
            }

        } catch (\Exception $e) {
            $this->dispatch('error', "Refresh failed: {$e->getMessage()}");
        }
    }

    /**
     * 🌐 TEST MIRAKL OPERATOR CONNECTION
     */
    public function testMiraklOperator(int $accountId): void
    {
        $account = SyncAccount::find($accountId);
        if (! $account || $account->channel !== 'mirakl') {
            $this->dispatch('error', 'Invalid Mirakl account');

            return;
        }

        try {
            $miraklService = app(\App\Services\Mirakl\MiraklService::class);

            // Detect the operator type from account name/display name
            $operator = $this->detectMiraklOperator($account);

            if (! $operator) {
                $this->dispatch('warning', 'Could not detect Mirakl operator type for this account');

                return;
            }

            $result = $miraklService->testOperatorConnection($operator, $account);

            if ($result['success']) {
                $this->dispatch('success', "✅ {$operator} operator connection successful!");
            } else {
                $this->dispatch('error', "❌ {$operator} connection failed: ".($result['error'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            $this->dispatch('error', "Connection test failed: {$e->getMessage()}");
        }
    }

    /**
     * 🔍 DETECT MIRAKL OPERATOR TYPE
     */
    private function detectMiraklOperator(SyncAccount $account): ?string
    {
        $accountName = strtolower($account->name);
        $displayName = strtolower($account->display_name);

        // Check for B&Q
        if (str_contains($accountName, 'bq') || str_contains($displayName, 'b&q')) {
            return 'bq';
        }

        // Check for Debenhams
        if (str_contains($accountName, 'debenhams') || str_contains($displayName, 'debenhams')) {
            return 'debenhams';
        }

        // Check for Freemans
        if (str_contains($accountName, 'freemans') || str_contains($displayName, 'freemans') ||
            str_contains($accountName, 'frasers') || str_contains($displayName, 'frasers')) {
            return 'freemans';
        }

        return null; // Generic Mirakl account
    }

    /**
     * 🎨 RENDER COMPONENT
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.marketplace.identifiers-dashboard');
    }
}
