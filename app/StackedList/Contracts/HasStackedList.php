<?php

namespace App\StackedList\Contracts;

use App\StackedList\StackedListBuilder;

interface HasStackedList
{
    /**
     * Get the StackedList builder definition.
     */
    public function getStackedList(): StackedListBuilder;

    /**
     * Handle bulk actions executed from the StackedList.
     */
    public function handleBulkAction(string $action, array $selectedIds): void;
}