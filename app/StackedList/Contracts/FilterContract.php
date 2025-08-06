<?php

namespace App\StackedList\Contracts;

interface FilterContract
{
    /**
     * Set the filter name/key.
     */
    public function name(string $name): static;

    /**
     * Set the filter label.
     */
    public function label(string $label): static;

    /**
     * Set the filter placeholder.
     */
    public function placeholder(string $placeholder): static;

    /**
     * Convert the filter to array format.
     */
    public function toArray(): array;
}