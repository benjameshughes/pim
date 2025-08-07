<?php

namespace App\UI\Toasts\Concerns;

/**
 * HasTiming Concern
 * 
 * Manages toast timing including duration and persistence.
 * FilamentPHP-inspired timing management for toast notifications.
 */
trait HasTiming
{
    protected int $duration = 4000;
    protected ?int $delay = null;
    protected bool $persistent = false;
    protected bool $closable = true;

    /**
     * Set the toast duration in milliseconds (FilamentPHP style)
     */
    public function duration(int $duration): static
    {
        $this->duration = $duration;
        $this->persistent = false; // Duration implies auto-dismiss
        return $this;
    }

    /**
     * Set the toast duration in seconds (FilamentPHP style)
     */
    public function seconds(int $seconds): static
    {
        $this->duration = $seconds * 1000;
        $this->persistent = false; // Duration implies auto-dismiss
        return $this;
    }

    /**
     * Make toast persistent (requires manual dismiss) - FilamentPHP style
     */
    public function persistent(): static
    {
        $this->persistent = true;
        return $this;
    }

    /**
     * Set the toast delay before showing in milliseconds.
     */
    public function delay(int $delay): static
    {
        $this->delay = $delay;
        return $this;
    }

    /**
     * Make toast closable/non-closable
     */
    public function closable(bool $closable = true): static
    {
        $this->closable = $closable;
        return $this;
    }

    /**
     * Get the toast duration
     */
    public function getDuration(): int
    {
        return $this->duration;
    }

    /**
     * Get the toast delay
     */
    public function getDelay(): ?int
    {
        return $this->delay;
    }

    /**
     * Check if toast is persistent
     */
    public function isPersistent(): bool
    {
        return $this->persistent;
    }

    /**
     * Check if toast is closable
     */
    public function isClosable(): bool
    {
        return $this->closable;
    }
}