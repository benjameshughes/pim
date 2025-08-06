<?php

namespace App\StackedList\Concerns;

use App\StackedList\Contracts\ColumnContract;

trait HasColumns
{
    protected array $columns = [];
    protected array $searchableColumns = [];
    protected array $sortableColumns = [];

    /**
     * Set the columns for the stacked list.
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;
        $this->extractColumnProperties();
        return $this;
    }

    /**
     * Get the columns array.
     */
    public function getColumns(): array
    {
        return collect($this->columns)->map(function ($column) {
            return $column instanceof ColumnContract ? $column->toArray() : $column;
        })->toArray();
    }

    /**
     * Get searchable columns.
     */
    public function getSearchableColumns(): array
    {
        return $this->searchableColumns;
    }

    /**
     * Get sortable columns.
     */
    public function getSortableColumns(): array
    {
        return $this->sortableColumns;
    }

    /**
     * Extract searchable and sortable properties from columns.
     */
    protected function extractColumnProperties(): void
    {
        foreach ($this->columns as $column) {
            if (!($column instanceof ColumnContract)) {
                continue;
            }

            $columnArray = $column->toArray();

            if ($columnArray['searchable'] ?? false) {
                $this->searchableColumns[] = $columnArray['key'];
            }

            if ($columnArray['sortable'] ?? false) {
                $this->sortableColumns[] = $columnArray['key'];
            }
        }
    }
}