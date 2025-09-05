<?php

namespace App\Livewire\SyncAccounts;

use App\Models\SyncAccount;
use Livewire\Component;

class HealthBadge extends Component
{
    public int $syncAccountId;

    public bool $showHistory = false;

    public bool $isTesting = false;

    public function mount(int $syncAccountId): void
    {
        $this->syncAccountId = $syncAccountId;
    }

    public function getAccountProperty(): ?SyncAccount
    {
        return SyncAccount::find($this->syncAccountId);
    }

    public function openHistory(): void
    {
        $this->showHistory = true;
    }

    public function render()
    {
        $account = $this->account;
        $badge = $account?->getHealthBadge() ?? [
            'status' => 'unknown',
            'color' => 'gray',
            'icon' => 'question-mark-circle',
            'tested_at' => null,
            'message' => null,
        ];
        $history = $account?->getHealthHistory() ?? [];

        return view('livewire.sync-accounts.health-badge', [
            'badge' => $badge,
            'history' => $history,
            'account' => $account,
        ]);
    }

    public function testConnection(): void
    {
        $account = $this->account;
        if (! $account) {
            return;
        }

        $this->isTesting = true;

        try {
            $service = app(\App\Services\Marketplace\ConnectionTestService::class);
            $result = $service->testAndRecord($account);

            if ($result->success) {
                $this->dispatch('success', 'Connection successful');
            } else {
                $this->dispatch('error', 'Connection failed: '.$result->message);
            }
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Connection test failed: '.$e->getMessage());
        } finally {
            $this->isTesting = false;
        }
    }
}
