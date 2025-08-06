<?php

namespace App\Contracts;

interface HasStackedList
{
    /**
     * Handle bulk actions executed from the StackedList.
     */
    public function handleBulkAction(string $action, array $selectedIds): void;

    /**
     * Get the configuration for the StackedList.
     */
    public function getStackedListConfig(): array;
}