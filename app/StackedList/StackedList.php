<?php

namespace App\StackedList;

use App\StackedList\Concerns\Configurable;
use App\StackedList\Concerns\HasActions;
use App\StackedList\Concerns\HasColumns;
use App\StackedList\Concerns\HasFilters;

abstract class StackedList
{
    use Configurable;
    use HasColumns;
    use HasActions;
    use HasFilters;

    /**
     * Create a new stacked list instance.
     */
    public static function make(): static
    {
        return new static();
    }

    /**
     * Configure the stacked list. This method should be implemented by subclasses.
     */
    abstract public function configure(): static;

    /**
     * Convert the stacked list configuration to array format for the blade component.
     */
    public function toArray(): array
    {
        return [
            'title' => $this->getTitle(),
            'subtitle' => $this->getSubtitle(),
            'search_placeholder' => $this->getSearchPlaceholder(),
            'export' => $this->isExportEnabled(),
            'searchable' => $this->getSearchableColumns(),
            'sortable_columns' => $this->getSortableColumns(),
            'columns' => $this->getColumns(),
            'bulk_actions' => $this->getBulkActions(),
            'header_actions' => $this->getHeaderActions(),
            'filters' => $this->getFilters(),
            'empty_title' => $this->getEmptyState()['title'],
            'empty_description' => $this->getEmptyState()['description'],
            'empty_action' => $this->getEmptyState()['action'],
        ];
    }
}