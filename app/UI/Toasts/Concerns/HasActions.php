<?php

namespace App\Toasts\Concerns;

use App\Toasts\ToastAction;

trait HasActions
{
    protected array $actions = [];

    /**
     * Add an action to the toast.
     */
    public function action(ToastAction $action): static
    {
        $this->actions[] = $action;

        return $this;
    }

    /**
     * Add multiple actions to the toast.
     */
    public function actions(array $actions): static
    {
        foreach ($actions as $action) {
            $this->action($action);
        }

        return $this;
    }

    /**
     * Get the toast actions.
     */
    public function getActions(): array
    {
        return $this->actions;
    }
}