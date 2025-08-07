<?php

namespace App\UI\Toasts\Concerns;

use App\UI\Toasts\ToastManager;

/**
 * InteractsWithToasts Trait
 * 
 * FilamentPHP-inspired trait that enables Livewire components to work with toasts.
 * Provides complete toast state management and magic property resolution.
 * Matches the InteractsWithTable pattern exactly.
 */
trait InteractsWithToasts
{
    /**
     * Cache for the toast manager instance
     */
    protected ?ToastManager $toastManagerInstance = null;
    
    /**
     * Magic property getter for $this->toasts
     * This enables {{ $this->toasts }} to work in Blade templates
     * Exactly like $this->table works in the table system
     */
    public function getToastsProperty()
    {
        if ($this->toastManagerInstance === null) {
            $this->toastManagerInstance = app(ToastManager::class);
        }
        
        return $this->toastManagerInstance;
    }
    
    /**
     * Livewire lifecycle - mount hook
     * Initialize toast system for this component
     */
    public function mountInteractsWithToasts(): void
    {
        // Initialize toast system for this component instance
        app(ToastManager::class)->setComponent($this);
    }
    
    /**
     * Livewire lifecycle - boot hook
     * Set up event listeners for toast system
     */
    public function bootInteractsWithToasts(): void
    {
        $this->listeners = array_merge($this->listeners ?? [], [
            'toast:added' => 'refreshToasts',
            'toast:removed' => 'refreshToasts',
            'toast:cleared' => 'refreshToasts',
        ]);
    }
    
    /**
     * Refresh toasts when state changes
     */
    public function refreshToasts(): void
    {
        // Reset the toast manager instance to get fresh state
        $this->toastManagerInstance = null;
    }
    
    /**
     * Add a toast to this component
     */
    public function addToast($toast): void
    {
        app(ToastManager::class)->add($toast);
        $this->dispatch('toast:added');
    }
    
    /**
     * Remove a toast by ID
     */
    public function removeToast($toastId): void
    {
        app(ToastManager::class)->remove($toastId);
        $this->dispatch('toast:removed');
    }
    
    /**
     * Clear all toasts
     */
    public function clearAllToasts(): void
    {
        app(ToastManager::class)->clear();
        $this->dispatch('toast:cleared');
    }
    
    /**
     * Execute a toast action (like table actions)
     */
    public function executeToastAction(string $toastId, string $actionKey): void
    {
        $toast = app(ToastManager::class)->find($toastId);
        
        if (!$toast) {
            return;
        }
        
        $actions = $toast->getActions();
        
        foreach ($actions as $action) {
            if ($action->getKey() === $actionKey) {
                $callback = $action->getAction();
                
                if ($callback) {
                    $callback($toast);
                }
                
                // Auto-close toast if configured
                if ($action->getShouldCloseToast()) {
                    $this->removeToast($toastId);
                }
                
                break;
            }
        }
    }
}