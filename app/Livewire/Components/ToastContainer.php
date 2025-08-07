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


    public function mount()
    {
        // No longer need to load toasts in mount since we use computed property
    }

    /**
     * Remove a specific toast.
     */
    public function removeToast(string $toastId): void
    {
        $toastManager = app(ToastManager::class);
        $toastManager->remove($toastId);
        // No need to loadToasts() - computed property will refresh automatically
    }

    /**
     * Clear all toasts.
     */
    public function clearAllToasts(): void
    {
        $toastManager = app(ToastManager::class);
        $toastManager->clear();
        // No need to loadToasts() - computed property will refresh automatically
    }

    /**
     * Listen for new toasts added via events.
     */
    #[On('toast-added')]
    public function handleToastAdded(): void
    {
        // Component will re-render automatically, fetching fresh data via computed property
        $this->render();
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