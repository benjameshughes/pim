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
        // Authorize managing marketplace connections
        $this->authorize('manage-marketplace-connections');
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
        // Authorize managing marketplace connections
        $this->authorize('manage-marketplace-connections');
        
        $syncAccount->update(['is_active' => ! $syncAccount->is_active]);

        $status = $syncAccount->is_active ? 'activated' : 'deactivated';
        $this->dispatch('success', "Sync account {$status} successfully.");
    }

    public function delete(SyncAccount $syncAccount)
    {
        // Authorize managing marketplace connections
        $this->authorize('manage-marketplace-connections');
        
        $syncAccount->delete();
        $this->dispatch('success', 'Sync account deleted successfully.');
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
