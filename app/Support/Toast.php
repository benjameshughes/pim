<?php

namespace App\Support;

/**
 * Toast Notification Helper
 * 
 * Fluent API for creating user-friendly toast notifications with
 * enhanced error handling and user feedback.
 */
class Toast
{
    protected string $type;
    protected string $title;
    protected string $message;
    protected array $actions = [];
    protected int $duration = 5000;
    protected string $position = 'top-right';
    protected bool $dismissible = true;
    protected bool $persistent = false;
    protected array $data = [];
    
    protected function __construct(string $type, string $title, string $message = '')
    {
        $this->type = $type;
        $this->title = $title;
        $this->message = $message;
    }
    
    /**
     * Create success toast
     */
    public static function success(string $title, string $message = ''): static
    {
        return new static('success', $title, $message);
    }
    
    /**
     * Create error toast
     */
    public static function error(string $title, string $message = ''): static
    {
        return new static('error', $title, $message);
    }
    
    /**
     * Create warning toast
     */
    public static function warning(string $title, string $message = ''): static
    {
        return new static('warning', $title, $message);
    }
    
    /**
     * Create info toast
     */
    public static function info(string $title, string $message = ''): static
    {
        return new static('info', $title, $message);
    }
    
    /**
     * Set toast duration in milliseconds
     */
    public function duration(int $milliseconds): static
    {
        $this->duration = $milliseconds;
        return $this;
    }
    
    /**
     * Set toast position
     */
    public function position(string $position): static
    {
        $this->position = $position;
        return $this;
    }
    
    /**
     * Make toast persistent (won't auto-dismiss)
     */
    public function persistent(): static
    {
        $this->persistent = true;
        $this->duration = 0;
        return $this;
    }
    
    /**
     * Add action button to toast
     */
    public function action(string $label, string $action, array $params = []): static
    {
        $this->actions[] = [
            'label' => $label,
            'action' => $action,
            'params' => $params,
        ];
        return $this;
    }
    
    /**
     * Add URL action to toast
     */
    public function actionUrl(string $label, string $url, bool $newTab = false): static
    {
        $this->actions[] = [
            'label' => $label,
            'url' => $url,
            'newTab' => $newTab,
        ];
        return $this;
    }
    
    /**
     * Add retry action
     */
    public function retry(string $method = 'save', array $params = []): static
    {
        return $this->action('Retry', $method, $params);
    }
    
    /**
     * Add suggestions as additional data
     */
    public function withSuggestions(array $suggestions): static
    {
        $this->data['suggestions'] = $suggestions;
        return $this;
    }
    
    /**
     * Add additional contextual data
     */
    public function withData(array $data): static
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }
    
    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'duration' => $this->duration,
            'position' => $this->position,
            'dismissible' => $this->dismissible,
            'persistent' => $this->persistent,
            'actions' => $this->actions,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }
    
    /**
     * Dispatch toast to Livewire component
     */
    public function send($component = null): void
    {
        if ($component) {
            $component->dispatch('toast', $this->toArray());
        } else {
            // Store in session for next request
            session()->flash('toast', $this->toArray());
        }
    }
    
    /**
     * Create toast from exception with enhanced error handling
     */
    public static function fromException(\Exception $exception, $component = null): static
    {
        $toast = match (get_class($exception)) {
            'App\Exceptions\BarcodePoolExhaustedException' => static::error(
                'Barcodes Exhausted',
                $exception->getUserMessage()
            )
                ->withSuggestions($exception->getSuggestedActions())
                ->actionUrl('Import Barcodes', '/barcodes/pool/import'),
                
            'App\Exceptions\DuplicateSkuException' => static::error(
                'Duplicate SKU',
                $exception->getUserMessage()
            )
                ->withSuggestions($exception->getSuggestedSkus())
                ->retry(),
                
            default => static::error(
                'An error occurred',
                $exception->getMessage()
            )
                ->retry(),
        };
        
        if ($component) {
            $toast->send($component);
        }
        
        return $toast;
    }
}