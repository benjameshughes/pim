<?php

namespace App\UI\Toasts;

use Closure;
use Illuminate\Contracts\Support\Arrayable;

/**
 * ToastAction Class
 * 
 * FilamentPHP-inspired action buttons for toast notifications.
 * Supports URLs, callbacks, styling, and interaction behaviors.
 */
class ToastAction implements Arrayable
{
    protected string $label;
    protected ?string $url = null;
    protected ?Closure $action = null;
    protected ?string $icon = null;
    protected array $classes = [];
    protected bool $shouldCloseToast = true;
    protected string $color = 'primary';
    protected string $variant = 'filled';
    protected bool $openInNewTab = false;

    protected function __construct(string $label)
    {
        $this->label = $label;
    }

    /**
     * Create a new toast action.
     */
    public static function make(string $label): static
    {
        return new static($label);
    }

    /**
     * Set the action URL.
     */
    public function url(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Set the action callback.
     */
    public function action(Closure $action): static
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Set the action icon.
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Add CSS classes to the action.
     */
    public function class(string|array $classes): static
    {
        if (is_string($classes)) {
            $classes = explode(' ', $classes);
        }

        $this->classes = array_merge($this->classes, $classes);

        return $this;
    }

    /**
     * Set action color (FilamentPHP style)
     */
    public function color(string $color): static
    {
        $this->color = $color;
        return $this;
    }

    /**
     * Set action variant (FilamentPHP style)
     */
    public function variant(string $variant): static
    {
        $this->variant = $variant;
        return $this;
    }

    /**
     * Open URL in new tab
     */
    public function openInNewTab(bool $openInNewTab = true): static
    {
        $this->openInNewTab = $openInNewTab;
        return $this;
    }

    /**
     * Set whether the toast should close when the action is clicked.
     */
    public function shouldCloseToast(bool $shouldClose = true): static
    {
        $this->shouldCloseToast = $shouldClose;
        return $this;
    }

    /**
     * Get the action label.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the action URL.
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Get the action callback.
     */
    public function getAction(): ?Closure
    {
        return $this->action;
    }

    /**
     * Get the action icon.
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * Get the action CSS classes.
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
     * Check if the toast should close when this action is clicked.
     */
    public function getShouldCloseToast(): bool
    {
        return $this->shouldCloseToast;
    }

    /**
     * Get the action color
     */
    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * Get the action variant
     */
    public function getVariant(): string
    {
        return $this->variant;
    }

    /**
     * Check if should open in new tab
     */
    public function getOpenInNewTab(): bool
    {
        return $this->openInNewTab;
    }

    /**
     * Convert the action to an array.
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'url' => $this->url,
            'icon' => $this->icon,
            'classes' => $this->classes,
            'color' => $this->color,
            'variant' => $this->variant,
            'open_in_new_tab' => $this->openInNewTab,
            'should_close_toast' => $this->shouldCloseToast,
        ];
    }
}