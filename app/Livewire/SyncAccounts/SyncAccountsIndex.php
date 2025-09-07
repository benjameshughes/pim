<?php

namespace App\Livewire\SyncAccounts;

use App\Models\SyncAccount;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class SyncAccountsIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount()
    {
        $this->authorize('viewAny', SyncAccount::class);
    }

    public string $channelFilter = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingChannelFilter()
    {
        $this->resetPage();
    }

    public function toggleActive(SyncAccount $syncAccount)
    {
        $this->authorize('update', $syncAccount);

        $syncAccount->update(['is_active' => ! $syncAccount->is_active]);

        $status = $syncAccount->is_active ? 'activated' : 'deactivated';
        $this->dispatch('success', "Sync account {$status} successfully.");
    }

    public function delete(SyncAccount $syncAccount)
    {
        $this->authorize('delete', $syncAccount);

        $syncAccount->delete();
        $this->dispatch('success', 'Sync account deleted successfully.');
    }

    public function testConnection(int $id)
    {
        $account = SyncAccount::findOrFail($id);
        $this->authorize('testConnection', $account);

        $svc = app(\App\Services\Marketplace\SyncAccountService::class);
        try {
            $result = $svc->testConnection($account);
            if ($result['success'] ?? false) {
                $this->dispatch('success', 'Connection successful');
            } else {
                $this->dispatch('error', 'Connection failed: '.($result['error'] ?? 'Unknown error'));
            }
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Connection test error: '.$e->getMessage());
        }
    }

    public function render()
    {
        $query = SyncAccount::query()
            ->when($this->search, fn ($q) => $q->where('display_name', 'like', "%{$this->search}%")
                ->orWhere('marketplace_subtype', 'like', "%{$this->search}%"))
            ->when($this->channelFilter, fn ($q) => $q->where('channel', $this->channelFilter))
            ->orderBy('created_at', 'desc');

        $syncAccounts = $query->paginate(10);

        $channels = SyncAccount::distinct('channel')->pluck('channel')->sort();

        return view('livewire.sync-accounts.sync-accounts-index', [
            'syncAccounts' => $syncAccounts,
            'channels' => $channels,
        ]);
    }
}
