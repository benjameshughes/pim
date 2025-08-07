<?php

namespace App\Livewire\Examples;

use App\UI\Toasts\Toast;
use App\UI\Toasts\ToastAction;
use App\UI\Toasts\ToastManager;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ToastStackingDemo extends Component
{
    private int $counter = 0;
    
    #[Computed]
    public function toastCount(): int
    {
        return app(ToastManager::class)->all()->count();
    }
    
    public function createMultipleToasts(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Toast::success(
                "Success Toast #{$i}", 
                "This is toast number {$i} in the stack. Each has its own timer!"
            )
            ->duration(3000 + ($i * 1000)) // Each lasts a bit longer
            ->send();
            
            usleep(100000); // Small delay to ensure ordering
        }
    }
    
    public function createToastsInDifferentPositions(): void
    {
        $positions = ['top-right', 'top-left', 'bottom-right', 'bottom-left', 'top-center', 'bottom-center'];
        $types = ['success', 'error', 'warning', 'info'];
        
        foreach ($positions as $index => $position) {
            $type = $types[$index % 4];
            Toast::$type(
                ucwords(str_replace('-', ' ', $position)),
                "This toast appears in the {$position} position"
            )
            ->position($position)
            ->duration(5000)
            ->send();
        }
    }
    
    public function createDifferentTypes(): void
    {
        Toast::success('Success Message', 'Operation completed successfully!')
            ->duration(4000)
            ->send();
            
        Toast::error('Error Message', 'Something went wrong. Please try again.')
            ->duration(5000)
            ->send();
            
        Toast::warning('Warning Message', 'Please review your input before continuing.')
            ->duration(6000)
            ->send();
            
        Toast::info('Info Message', 'Here\'s some helpful information for you.')
            ->duration(7000)
            ->send();
    }
    
    public function createWithDifferentTimers(): void
    {
        Toast::success('Quick Toast (2s)', 'I\'ll disappear quickly!')
            ->duration(2000)
            ->send();
            
        Toast::warning('Medium Toast (5s)', 'I\'ll stick around for a bit.')
            ->duration(5000)
            ->send();
            
        Toast::info('Long Toast (10s)', 'I\'ll be here for a while.')
            ->duration(10000)
            ->send();
            
        Toast::error('Persistent Toast', 'I won\'t go away until you close me!')
            ->persistent()
            ->send();
    }
    
    public function createMixedPersistent(): void
    {
        Toast::success('Auto-dismiss (3s)', 'I\'ll disappear automatically')
            ->duration(3000)
            ->send();
            
        Toast::error('Persistent Error', 'I require manual dismissal')
            ->persistent()
            ->send();
            
        Toast::warning('Auto-dismiss (5s)', 'Another auto-dismiss toast')
            ->duration(5000)
            ->send();
            
        Toast::info('Persistent Info', 'Another persistent toast')
            ->persistent()
            ->send();
    }
    
    public function createWithActions(): void
    {
        Toast::success('File Uploaded', 'Your file has been uploaded successfully.')
            ->action(ToastAction::make('View File')->url('/files'))
            ->action(ToastAction::make('Upload Another')->shouldCloseToast())
            ->duration(8000)
            ->send();
            
        Toast::warning('Unsaved Changes', 'You have unsaved changes.')
            ->action(ToastAction::make('Save Now')->url('#'))
            ->action(ToastAction::make('Discard')->shouldCloseToast())
            ->persistent()
            ->send();
            
        Toast::info('New Feature Available', 'Check out our latest feature!')
            ->action(ToastAction::make('Learn More')->url('/features'))
            ->duration(10000)
            ->send();
    }
    
    public function stressTest(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $types = ['success', 'error', 'warning', 'info'];
            $type = $types[array_rand($types)];
            $positions = ['top-right', 'top-left', 'bottom-right', 'bottom-left'];
            $position = $positions[array_rand($positions)];
            
            Toast::$type(
                "Stress Test Toast #{$i}",
                "This is toast {$i} of 20 in the stress test."
            )
            ->position($position)
            ->duration(rand(3000, 8000))
            ->send();
            
            usleep(50000); // Small delay
        }
    }
    
    public function createInPosition(string $position): void
    {
        $this->counter++;
        Toast::info(
            "Toast in " . ucwords(str_replace('-', ' ', $position)),
            "This is toast #{$this->counter} positioned at {$position}"
        )
        ->position($position)
        ->duration(5000)
        ->send();
    }
    
    public function clearAll(): void
    {
        app(ToastManager::class)->clear();
        $this->dispatch('$refresh');
    }
    
    public function render()
    {
        return view('livewire.examples.toast-stacking-demo');
    }
}