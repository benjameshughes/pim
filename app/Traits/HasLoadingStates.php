<?php

namespace App\Traits;

/**
 * Loading States Trait
 * 
 * Provides loading state management for Livewire components with
 * progressive indicators and user feedback.
 */
trait HasLoadingStates
{
    /**
     * Global loading state
     * 
     * @var bool
     */
    public bool $isLoading = false;
    
    /**
     * Specific loading states for different operations
     * 
     * @var array
     */
    public array $loadingStates = [];
    
    /**
     * Loading messages for user feedback
     * 
     * @var array
     */
    public array $loadingMessages = [
        'saving' => 'Saving your changes...',
        'loading' => 'Loading data...',
        'processing' => 'Processing request...',
        'validating' => 'Validating form...',
        'uploading' => 'Uploading files...',
        'deleting' => 'Deleting item...',
        'searching' => 'Searching...',
    ];
    
    /**
     * Set loading state for specific operation
     */
    protected function setLoading(string $operation, bool $loading = true, ?string $message = null): void
    {
        $this->loadingStates[$operation] = [
            'active' => $loading,
            'message' => $message ?? ($this->loadingMessages[$operation] ?? 'Processing...'),
            'startTime' => $loading ? microtime(true) : null,
        ];
        
        // Update global loading state
        $this->updateGlobalLoadingState();
        
        // Emit loading state change for UI updates
        $this->dispatch('loading-state-changed', [
            'operation' => $operation,
            'loading' => $loading,
            'message' => $this->loadingStates[$operation]['message'],
        ]);
    }
    
    /**
     * Check if specific operation is loading
     */
    protected function isLoadingOperation(string $operation): bool
    {
        return $this->loadingStates[$operation]['active'] ?? false;
    }
    
    /**
     * Get loading message for operation
     */
    protected function getLoadingMessage(string $operation): string
    {
        return $this->loadingStates[$operation]['message'] ?? 'Loading...';
    }
    
    /**
     * Update global loading state based on active operations
     */
    protected function updateGlobalLoadingState(): void
    {
        $this->isLoading = collect($this->loadingStates)
            ->where('active', true)
            ->isNotEmpty();
    }
    
    /**
     * Clear specific loading state
     */
    protected function clearLoading(string $operation): void
    {
        $this->setLoading($operation, false);
    }
    
    /**
     * Clear all loading states
     */
    protected function clearAllLoading(): void
    {
        foreach (array_keys($this->loadingStates) as $operation) {
            $this->clearLoading($operation);
        }
    }
    
    /**
     * Execute callback with loading state management
     */
    protected function withLoadingState(string $operation, callable $callback, ?string $message = null)
    {
        $this->setLoading($operation, true, $message);
        
        try {
            $result = $callback();
            $this->clearLoading($operation);
            return $result;
        } catch (\Exception $e) {
            $this->clearLoading($operation);
            throw $e;
        }
    }
    
    /**
     * Get active loading operations
     */
    protected function getActiveLoadingOperations(): array
    {
        return collect($this->loadingStates)
            ->filter(fn($state) => $state['active'])
            ->map(fn($state, $operation) => [
                'operation' => $operation,
                'message' => $state['message'],
                'duration' => $state['startTime'] ? round((microtime(true) - $state['startTime']) * 1000) : 0,
            ])
            ->values()
            ->toArray();
    }
    
    /**
     * Set custom loading messages
     */
    protected function setLoadingMessages(array $messages): void
    {
        $this->loadingMessages = array_merge($this->loadingMessages, $messages);
    }
    
    /**
     * Create progressive loading sequence
     */
    protected function progressiveLoading(array $steps, callable $callback): void
    {
        $totalSteps = count($steps);
        $currentStep = 0;
        
        foreach ($steps as $operation => $message) {
            $currentStep++;
            $progress = round(($currentStep / $totalSteps) * 100);
            
            $this->setLoading($operation, true, "{$message} ({$progress}%)");
            
            try {
                $callback($operation, $currentStep, $totalSteps);
                $this->clearLoading($operation);
            } catch (\Exception $e) {
                $this->clearAllLoading();
                throw $e;
            }
        }
    }
    
    /**
     * Simulate loading for demo/testing purposes
     */
    protected function simulateLoading(string $operation, int $duration = 1000, ?string $message = null): void
    {
        $this->setLoading($operation, true, $message);
        
        // In real app, you wouldn't sleep - this is for demonstration
        // usleep($duration * 1000);
        
        $this->clearLoading($operation);
    }
    
    /**
     * Get loading states for JavaScript
     */
    protected function getLoadingStatesForJS(): array
    {
        return [
            'isLoading' => $this->isLoading,
            'operations' => $this->getActiveLoadingOperations(),
        ];
    }
}