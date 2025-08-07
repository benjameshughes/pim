<?php

namespace App\Contracts;

use App\StackedList\StackedListBuilder;

interface HasStackedList
{
    /**
     * Handle bulk actions executed from the StackedList.
     */
    public function handleBulkAction(string $action, array $selectedIds): void;

    /**
     * Get the StackedList builder definition.
     */
    public function getList(): StackedListBuilder;
}