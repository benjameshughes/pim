<?php

namespace App\UI\Toasts\Concerns;

use App\UI\Toasts\ToastAction;

/**
 * HasActions Concern
 * 
 * Manages toast actions and action buttons.
 * FilamentPHP-inspired action management for toast notifications.
 */
trait HasActions
{
    protected array $actions = [];

    /**
     * Add an action to the toast (FilamentPHP style)
     */
    public function action($action): static
    {
        $this->actions[] = $action;
        return $this;
    }

    /**
     * Add multiple actions to the toast (FilamentPHP style)
     */
    public function actions(array $actions): static
    {
        foreach ($actions as $action) {
            $this->action($action);
        }
        return $this;
    }

    /**
     * Get the toast actions
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * Check if toast has actions
     */
    public function hasActions(): bool
    {
        return count($this->actions) > 0;
    }

    /**
     * Get actions as array format
     */
    public function getActionsArray(): array
    {
        return array_map(fn($action) => $action->toArray(), $this->actions);
    }

    /**
     * Clear all actions
     */
    public function clearActions(): static
    {
        $this->actions = [];
        return $this;
    }
}