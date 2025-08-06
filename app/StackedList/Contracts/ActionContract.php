<?php

namespace App\StackedList\Contracts;

interface ActionContract
{
    /**
     * Set the action name.
     */
    public function name(string $name): static;

    /**
     * Set the action label.
     */
    public function label(string $label): static;

    /**
     * Set the action icon.
     */
    public function icon(string $icon): static;

    /**
     * Set the action variant/style.
     */
    public function variant(string $variant): static;

    /**
     * Convert the action to array format.
     */
    public function toArray(): array;
}