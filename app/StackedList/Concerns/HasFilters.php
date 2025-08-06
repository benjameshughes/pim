<?php

namespace App\StackedList\Concerns;

use App\StackedList\Contracts\FilterContract;

trait HasFilters
{
    protected array $filters = [];

    /**
     * Set filters for the stacked list.
     */
    public function filters(array $filters): static
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * Get the filters array.
     */
    public function getFilters(): array
    {
        return collect($this->filters)->mapWithKeys(function ($filter) {
            $filterArray = $filter instanceof FilterContract ? $filter->toArray() : $filter;
            return [$filterArray['name'] ?? $filterArray['key'] => $filterArray];
        })->toArray();
    }
}