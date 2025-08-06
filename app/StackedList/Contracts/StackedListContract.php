<?php

namespace App\StackedList\Contracts;

use App\StackedList\StackedList;

interface StackedListContract
{
    /**
     * Configure and return the stacked list instance.
     */
    public function configure(): StackedList;

    /**
     * Get the model class for the stacked list.
     */
    public function getModel(): string;

    /**
     * Get the query for the stacked list data.
     */
    public function getQuery(): \Illuminate\Database\Eloquent\Builder;
}