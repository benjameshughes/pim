<?php

namespace App\UI\Toasts\Concerns;

trait HasIcon
{
    protected ?string $icon = null;

    /**
     * Set the toast icon.
     */
    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Get the toast icon.
     */
    public function getIcon(): ?string
    {
        // If no custom icon is set, use the default icon for the toast type
        return $this->icon ?? $this->getTypeConfig()['icon'] ?? null;
    }
}