<?php

namespace App\StackedList\Actions;

use Closure;

class BulkAction extends Action
{
    protected $actionCallback = null;

    /**
     * Create a new bulk action instance.
     */
    public static function make(string $name): static
    {
        $instance = parent::make($name);
        $instance->key = $name; // Ensure key is set for bulk actions
        return $instance;
    }

    /**
     * Set the action to execute when this bulk action is triggered.
     * Accepts closures, class names, or method strings.
     */
    public function action(Closure|string $action): static
    {
        $this->actionCallback = $action;
        return $this;
    }

    /**
     * Create a bulk delete action with confirmation.
     */
    public static function delete(): static
    {
        return static::make('delete')
            ->label('Delete Selected')
            ->icon('trash-2')
            ->danger()
            ->requiresConfirmation(
                'Delete Selected Items',
                'Are you sure you want to delete the selected items? This action cannot be undone.'
            );
    }

    /**
     * Create a bulk export action.
     */
    public static function export(): static
    {
        return static::make('export')
            ->label('Export Selected')
            ->icon('download')
            ->outline();
    }

    /**
     * Create a bulk activate action.
     */
    public static function activate(): static
    {
        return static::make('activate')
            ->label('Activate Selected')
            ->icon('check-circle')
            ->outline();
    }

    /**
     * Create a bulk deactivate action.
     */
    public static function deactivate(): static
    {
        return static::make('deactivate')
            ->label('Deactivate Selected')
            ->icon('circle-x')
            ->outline();
    }

    /**
     * Execute the action with the given selected IDs.
     */
    public function execute(array $selectedIds, $component = null): mixed
    {
        if ($this->actionCallback === null) {
            return null;
        }

        if ($this->actionCallback instanceof Closure) {
            return ($this->actionCallback)($selectedIds, $component);
        }

        if (is_string($this->actionCallback)) {
            if (class_exists($this->actionCallback)) {
                // Instantiate and execute class
                $actionInstance = app($this->actionCallback);
                if (method_exists($actionInstance, 'execute')) {
                    return $actionInstance->execute($selectedIds, $component);
                }
                if (method_exists($actionInstance, '__invoke')) {
                    return $actionInstance($selectedIds, $component);
                }
            } elseif ($component && method_exists($component, $this->actionCallback)) {
                // Call component method
                return $component->{$this->actionCallback}($selectedIds);
            }
        }

        return null;
    }

    /**
     * Check if this action has an executable callback.
     */
    public function hasAction(): bool
    {
        return $this->actionCallback !== null;
    }

    /**
     * Get the action key.
     */
    public function getKey(): string
    {
        return $this->key ?? $this->name;
    }

    /**
     * Get the action callback.
     */
    public function getActionCallback(): mixed
    {
        return $this->actionCallback;
    }

    /**
     * Override toArray to include action information if needed.
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        $array['has_action'] = $this->hasAction();
        return $array;
    }
}