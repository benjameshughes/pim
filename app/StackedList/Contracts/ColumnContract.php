<?php

namespace App\StackedList\Contracts;

interface ColumnContract
{
    /**
     * Set the column name/key.
     */
    public function name(string $name): static;

    /**
     * Set the column label.
     */
    public function label(string $label): static;

    /**
     * Make the column sortable.
     */
    public function sortable(bool $sortable = true): static;

    /**
     * Make the column searchable.
     */
    public function searchable(bool $searchable = true): static;

    /**
     * Convert the column to array format.
     */
    public function toArray(): array;
}