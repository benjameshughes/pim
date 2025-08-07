<?php

namespace App\Livewire\Components;

use App\Toasts\ToastManager;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class ToastContainer extends Component
{
    /**
     * Get toasts grouped by position from the toast manager.
     * Using a computed property instead of storing Collection as property.
     */
    public function getToastsByPositionProperty(): Collection
    {
        $toastManager = app(ToastManager::class);
        return $toastManager->getToastsByPosition();
    }

    /**
     * Get all toasts as a flat array for Alpine store initialization.
     */
    public function getAllToastsProperty(): Collection
    {
        $toastManager = app(ToastManager::class);
        return $toastManager->all();
    }

    public function mount()
    {
        // Initial loading doesn't need to dispatch since template will handle it
    }

    /**
     * Remove a specific toast.
     */
    public function removeToast(string $toastId): void
    {
        $toastManager = app(ToastManager::class);
        $toastManager->remove($toastId);
        // Component will re-render and Alpine store will sync
    }

    /**
     * Clear all toasts.
     */
    public function clearAllToasts(): void
    {
        $toastManager = app(ToastManager::class);
        $toastManager->clear();
        // Component will re-render and Alpine store will sync
    }

    /**
     * Listen for new toasts added via events.
     */
    #[On('toast-added')]
    public function handleToastAdded(): void
    {
        // Component will re-render and Alpine store will sync
    }

    /**
     * Listen for toast removal events.
     */
    #[On('toast-removed')]
    public function handleToastRemoved(string $toastId): void
    {
        $this->removeToast($toastId);
    }

    /**
     * Listen for toast removal events from Alpine store.
     */
    #[On('toast-removed-from-store')]
    public function handleToastRemovedFromStore(string $toastId): void
    {
        $toastManager = app(ToastManager::class);
        $toastManager->remove($toastId);
        // Don't dispatch back to Alpine to avoid loops
    }

    /**
     * Listen for toasts cleared events from Alpine store.
     */
    #[On('toasts-cleared-from-store')]
    public function handleTostsClearedFromStore(): void
    {
        $toastManager = app(ToastManager::class);
        $toastManager->clear();
        // Don't dispatch back to Alpine to avoid loops
    }

    /**
     * Handle action clicks from JavaScript.
     */
    public function handleToastAction(string $toastId, array $actionData): void
    {
        // If the action has a URL, we'll let the frontend handle the navigation
        // If the action should close the toast, remove it
        if ($actionData['should_close_toast'] ?? true) {
            $this->removeToast($toastId);
        }

        // Dispatch event for custom JavaScript handling
        $this->dispatch('toast-action-clicked', [
            'toastId' => $toastId,
            'actionData' => $actionData,
        ]);
    }


    public function render()
    {
        return view('livewire.components.toast-container');
    }
}