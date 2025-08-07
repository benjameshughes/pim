<?php

namespace App\Toasts\Concerns;

trait HasStyling
{
    protected array $classes = [];
    protected array $styles = [];

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