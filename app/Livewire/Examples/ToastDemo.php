<?php

namespace App\Livewire\Examples;

use App\UI\Toasts\Concerns\InteractsWithToasts;
use App\UI\Toasts\Facades\Toast;
use App\UI\Toasts\Toast as ToastNotification;
use App\UI\Toasts\ToastAction;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ToastDemo extends Component
{
    use InteractsWithToasts;
    public string $customTitle = 'Custom Toast Title';
    public string $customBody = 'This is a custom toast message with detailed content.';
    public string $selectedType = 'info';
    public string $selectedPosition = 'top-right';
    public int $customDuration = 4000;
    public bool $closable = true;
    public bool $persistent = false;

    /**
     * Demo basic toast types.
     */
    public function showSuccessToast()
    {
        Toast::success('Operation Successful!', 'Your changes have been saved successfully.')
            ->duration(5000)
            ->send();
    }

    public function showErrorToast()
    {
        Toast::error('Operation Failed!', 'Something went wrong. Please try again.')
            ->persistent()
            ->send();
    }

    public function showWarningToast()
    {
        Toast::warning('Warning Notice', 'Please review your input before proceeding.')
            ->duration(6000)
            ->send();
    }

    public function showInfoToast()
    {
        Toast::info('Information', 'Here\'s some helpful information for you.')
            ->send();
    }

    /**
     * Demo custom toast with actions.
     */
    public function showActionToast()
    {
        Toast::info('Action Required', 'Would you like to proceed with this operation?')
            ->persistent()
            ->action(
                ToastAction::make('Proceed')
                    ->icon('check')
                    ->class(['text-blue-600', 'hover:text-blue-800'])
            )
            ->action(
                ToastAction::make('Cancel')
                    ->icon('x')
                    ->class(['text-gray-600', 'hover:text-gray-800'])
                    ->shouldCloseToast(true)
            )
            ->send();
    }

    /**
     * Demo position variations.
     */
    public function showPositionDemo()
    {
        $positions = ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right'];
        
        foreach ($positions as $index => $position) {
            ToastNotification::info("Position Demo", "This toast is positioned at: {$position}")
                ->position($position)
                ->duration(3000 + ($index * 500)) // Staggered duration
                ->send();
        }
    }

    /**
     * Demo custom styled toast.
     */
    public function showCustomToast()
    {
        ToastNotification::make()
            ->title($this->customTitle)
            ->body($this->customBody)
            ->type($this->selectedType)
            ->position($this->selectedPosition)
            ->duration($this->customDuration)
            ->closable($this->closable)
            ->persistent($this->persistent)
            ->icon('star')
            ->send();
    }

    /**
     * Demo helper functions.
     */
    public function showHelperFunctionDemo()
    {
        // Using global helper functions
        toast_success('Helper Success', 'This toast was created using the toast_success() helper function.')->send();
        
        // Using the main toast() helper
        toast('Helper Info', 'This toast was created using the toast() helper function.')->send();
    }

    /**
     * Demo programmatic toast management.
     */
    public function showManagementDemo()
    {
        // Add multiple toasts
        for ($i = 1; $i <= 3; $i++) {
            Toast::info("Batch Toast {$i}", "This is toast number {$i} in a batch.")
                ->duration(8000)
                ->send();
        }
    }

    /**
     * Clear all toasts.
     */
    public function clearAllToasts()
    {
        Toast::clear();
        $this->dispatch('toast-added'); // Trigger refresh
    }
    
    /**
     * Demo navigation persistence.
     */
    public function showNavigationPersistent()
    {
        Toast::success('I persist across pages!', 'Navigate to another page using wire:navigate and I\'ll still be here.')
            ->persist() // This makes it survive navigation
            ->duration(15000) // Long duration to test
            ->send();
            
        Toast::info('I disappear on navigation', 'I\'m a regular toast that won\'t survive page changes.')
            ->duration(15000)
            ->send();
    }
    
    /**
     * Demo both persist() and persistent().
     */
    public function showPersistentAndPersistent()
    {
        Toast::warning('Ultimate Persistence!', 'I survive navigation AND won\'t auto-dismiss.')
            ->persist() // Survives navigation
            ->persistent() // Won't auto-dismiss
            ->send();
    }

    public function render()
    {
        return view('livewire.examples.toast-demo');
    }
}