<?php

namespace App\UI\Toasts\Concerns;

/**
 * HasStyling Concern
 * 
 * Manages toast styling including colors, variants, and positioning.
 * FilamentPHP-inspired styling management for toast notifications.
 */
trait HasStyling
{
    protected array $classes = [];
    protected array $styles = [];
    protected string $type = 'info';
    protected ?string $color = null;
    protected string $position = 'top-right';
    protected string $variant = 'filled';
    
    /**
     * Set as success toast (FilamentPHP style)
     */
    public function success(): static
    {
        $this->type = 'success';
        $this->icon = $this->icon ?? 'check-circle';
        return $this;
    }
    
    /**
     * Set as error toast (FilamentPHP style)
     */
    public function error(): static
    {
        $this->type = 'error';
        $this->icon = $this->icon ?? 'x-circle';
        return $this;
    }
    
    /**
     * Set as warning toast (FilamentPHP style)
     */
    public function warning(): static
    {
        $this->type = 'warning';
        $this->icon = $this->icon ?? 'exclamation-triangle';
        return $this;
    }
    
    /**
     * Set as info toast (FilamentPHP style)
     */
    public function info(): static
    {
        $this->type = 'info';
        $this->icon = $this->icon ?? 'information-circle';
        return $this;
    }
    
    /**
     * Set toast type directly
     */
    public function type(string $type): static
    {
        $this->type = $type;
        return $this;
    }
    
    /**
     * Set toast color
     */
    public function color(string $color): static
    {
        $this->color = $color;
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
     * Set toast variant
     */
    public function variant(string $variant): static
    {
        $this->variant = $variant;
        return $this;
    }

    /**
     * Add CSS classes to the toast.
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
     * Add inline styles to the toast.
     */
    public function style(array $styles): static
    {
        $this->styles = array_merge($this->styles, $styles);

        return $this;
    }

    /**
     * Get the toast type
     */
    public function getType(): string
    {
        return $this->type;
    }
    
    /**
     * Get the toast color
     */
    public function getColor(): ?string
    {
        return $this->color;
    }
    
    /**
     * Get the toast position
     */
    public function getPosition(): string
    {
        return $this->position;
    }
    
    /**
     * Get the toast variant
     */
    public function getVariant(): string
    {
        return $this->variant;
    }

    /**
     * Get the toast CSS classes.
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
     * Get the toast inline styles.
     */
    public function getStyles(): array
    {
        return $this->styles;
    }

    /**
     * Get compiled CSS class string.
     */
    public function getClassString(): string
    {
        return implode(' ', $this->classes);
    }

    /**
     * Get compiled inline style string.
     */
    public function getStyleString(): string
    {
        $styles = [];
        foreach ($this->styles as $property => $value) {
            $styles[] = "{$property}: {$value}";
        }

        return implode('; ', $styles);
    }
}