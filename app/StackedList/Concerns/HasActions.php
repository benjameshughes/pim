<?php

namespace App\StackedList\Concerns;

use App\StackedList\Contracts\ActionContract;

trait HasActions
{
    protected array $actions = [];
    protected array $bulkActions = [];
    protected array $headerActions = [];

    /**
     * Set row actions for the stacked list.
     */
    public function actions(array $actions): static
    {
        $this->actions = $actions;
        return $this;
    }

    /**
     * Set bulk actions for the stacked list.
     */
    public function bulkActions(array $actions): static
    {
        $this->bulkActions = $actions;
        return $this;
    }

    /**
     * Set header actions for the stacked list.
     */
    public function headerActions(array $actions): static
    {
        $this->headerActions = $actions;
        return $this;
    }

    /**
     * Get the actions array.
     */
    public function getActions(): array
    {
        return collect($this->actions)->map(function ($action) {
            return $action instanceof ActionContract ? $action->toArray() : $action;
        })->toArray();
    }

    /**
     * Get the bulk actions array.
     */
    public function getBulkActions(): array
    {
        return collect($this->bulkActions)->map(function ($action) {
            return $action instanceof ActionContract ? $action->toArray() : $action;
        })->toArray();
    }

    /**
     * Get the header actions array.
     */
    public function getHeaderActions(): array
    {
        return collect($this->headerActions)->map(function ($action) {
            return $action instanceof ActionContract ? $action->toArray() : $action;
        })->toArray();
    }
}