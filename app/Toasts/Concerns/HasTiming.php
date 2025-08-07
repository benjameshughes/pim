<?php

namespace App\Toasts\Concerns;

trait HasTiming
{
    protected int $duration;
    protected ?int $delay = null;

    /**
     * Set the toast duration in milliseconds.
     */
    public function duration(int $duration): static
    {
        $this->duration = $duration;

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
     * Get the toast duration.
     */
    public function getDuration(): int
    {
        return $this->duration;
    }

    /**
     * Get the toast delay.
     */
    public function getDelay(): ?int
    {
        return $this->delay;
    }
}