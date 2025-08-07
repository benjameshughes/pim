<?php

namespace App\Atom\Tables;

use Closure;

/**
 * Base Action Class
 * 
 * FilamentPHP-inspired action system with fluent API for configuration.
 * Supports header actions, row actions, and bulk actions.
 */
class Action
{
    protected string $key;
    protected ?string $label = null;
    protected ?string $icon = null;
    protected string $variant = 'outline';
    protected ?string $color = null;
    protected string|Closure|null $url = null;
    protected ?string $route = null;
    protected ?Closure $action = null;
    protected bool $requiresConfirmation = false;
    protected ?string $confirmationTitle = null;
    protected ?string $confirmationText = null;
    protected bool $openUrlInNewTab = false;
    protected string $size = 'sm';
    protected Closure|bool $visible = true;
    
    public function __construct(string $key)
    {
        $this->key = $key;
        $this->label = ucwords(str_replace(['_', '-'], ' ', $key));
    }
    
    /**
     * Factory method to create a new action
     */
    public static function make(string $key): static
    {
        return new static($key);
    }
    
    /**
     * Set the action label
     */
    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }
    
    /**
     * Set the action icon
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }
    
    /**
     * Set the action URL
     */
    public function url(string|Closure $url): static
    {
        $this->url = $url;
        return $this;
    }
    
    /**
     * Set the action route
     */
    public function route(string $route, array $parameters = []): static
    {
        $this->route = $route;
        return $this;
    }
    
    /**
     * Set the action callback
     */
    public function action(Closure $callback): static
    {
        $this->action = $callback;
        return $this;
    }
    
    /**
     * Require confirmation before executing
     */
    public function requiresConfirmation(
        string $title = 'Are you sure?',
        string $text = 'This action cannot be undone.'
    ): static {
        $this->requiresConfirmation = true;
        $this->confirmationTitle = $title;
        $this->confirmationText = $text;
        return $this;
    }
    
    /**
     * Open URL in new tab
     */
    public function openUrlInNewTab(bool $openInNewTab = true): static
    {
        $this->openUrlInNewTab = $openInNewTab;
        return $this;
    }
    
    /**
     * Set as button variant
     */
    public function button(): static
    {
        $this->variant = 'primary';
        return $this;
    }
    
    /**
     * Set as link variant
     */
    public function link(): static
    {
        $this->variant = 'link';
        return $this;
    }
    
    /**
     * Set as outline variant
     */
    public function outline(): static
    {
        $this->variant = 'outline';
        return $this;
    }
    
    /**
     * Set color
     */
    public function color(string $color): static
    {
        $this->color = $color;
        return $this;
    }
    
    /**
     * Set size
     */
    public function size(string $size): static
    {
        $this->size = $size;
        return $this;
    }
    
    /**
     * Set visibility condition
     */
    public function visible(Closure|bool $condition): static
    {
        $this->visible = $condition;
        return $this;
    }
    
    /**
     * Hide the action
     */
    public function hidden(): static
    {
        $this->visible = false;
        return $this;
    }
    
    /**
     * Create a common "Edit" action
     */
    public static function edit(): static
    {
        return static::make('edit')
            ->label('Edit')
            ->icon('pencil')
            ->outline();
    }
    
    /**
     * Create a common "View" action
     */
    public static function view(): static
    {
        return static::make('view')
            ->label('View')
            ->icon('eye')
            ->link();
    }
    
    /**
     * Create a common "Delete" action
     */
    public static function delete(): static
    {
        return static::make('delete')
            ->label('Delete')
            ->icon('trash')
            ->color('red')
            ->requiresConfirmation('Delete Item', 'This action cannot be undone.');
    }
    
    /**
     * Get the action key
     */
    public function getKey(): string
    {
        return $this->key;
    }
    
    /**
     * Get the action callback
     */
    public function getAction(): ?Closure
    {
        return $this->action;
    }
    
    /**
     * Check if action is visible for given record
     */
    public function isVisible($record = null): bool
    {
        if ($this->visible instanceof Closure) {
            return ($this->visible)($record);
        }
        
        return $this->visible;
    }
    
    /**
     * Convert action to array for rendering
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'icon' => $this->icon,
            'variant' => $this->variant,
            'color' => $this->color,
            'url' => $this->url,
            'route' => $this->route,
            'requiresConfirmation' => $this->requiresConfirmation,
            'confirmationTitle' => $this->confirmationTitle,
            'confirmationText' => $this->confirmationText,
            'openUrlInNewTab' => $this->openUrlInNewTab,
            'size' => $this->size,
            'hasAction' => $this->action !== null,
            'visible' => $this->visible,
        ];
    }
}