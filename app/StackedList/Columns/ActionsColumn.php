<?php

namespace App\StackedList\Columns;

use App\StackedList\Contracts\ActionContract;

class ActionsColumn extends Column
{
    protected string $type = 'actions';
    protected array $actions = [];

    /**
     * Set actions for the column.
     */
    public function actions(array $actions): static
    {
        $this->actions = $actions;
        return $this;
    }

    /**
     * Add a single action to the column.
     */
    public function action(ActionContract $action): static
    {
        $this->actions[] = $action;
        return $this;
    }

    /**
     * Convert the column to array format.
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['actions'] = collect($this->actions)->map(function ($action) {
            return $action instanceof ActionContract ? $action->toArray() : $action;
        })->toArray();
        
        return $array;
    }
}