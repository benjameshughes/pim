<?php

namespace App\StackedList\Actions;

use App\StackedList\Contracts\ActionContract;

class Action implements ActionContract
{
    protected string $name;
    protected string $label;
    protected string $variant = 'ghost';
    protected ?string $icon = null;
    protected ?string $method = null;
    protected ?string $route = null;
    protected ?string $href = null;
    protected ?string $title = null;
    protected ?string $key = null;
    protected bool $navigate = true;
    protected bool $requiresConfirmation = false;
    protected ?string $confirmationTitle = null;
    protected ?string $confirmationText = null;

    /**
     * Create a new action instance.
     */
    public static function make(string $name): static
    {
        return (new static())->name($name);
    }

    /**
     * Set the action name.
     */
    public function name(string $name): static
    {
        $this->name = $name;
        
        // Auto-generate label from name if not set
        if (!isset($this->label)) {
            $this->label = $this->generateLabelFromName($name);
        }
        
        return $this;
    }

    /**
     * Set the action label.
     */
    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Set the action icon.
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * Set the action variant/style.
     */
    public function variant(string $variant): static
    {
        $this->variant = $variant;
        return $this;
    }

    /**
     * Set the Livewire method to call.
     */
    public function method(string $method): static
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Set the route name for navigation.
     */
    public function route(string $route): static
    {
        $this->route = $route;
        return $this;
    }

    /**
     * Set a direct URL.
     */
    public function href(string $href): static
    {
        $this->href = $href;
        return $this;
    }

    /**
     * Set the action key (used for bulk actions).
     */
    public function key(string $key): static
    {
        $this->key = $key;
        return $this;
    }

    /**
     * Set the title/tooltip text.
     */
    public function title(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Set whether to use Livewire navigation.
     */
    public function navigate(bool $navigate = true): static
    {
        $this->navigate = $navigate;
        return $this;
    }

    /**
     * Require confirmation before executing the action.
     */
    public function requiresConfirmation(?string $title = null, ?string $text = null): static
    {
        $this->requiresConfirmation = true;
        $this->confirmationTitle = $title;
        $this->confirmationText = $text;
        return $this;
    }

    /**
     * Set this as a danger/destructive action.
     */
    public function danger(): static
    {
        return $this->variant('danger');
    }

    /**
     * Set this as a primary action.
     */
    public function primary(): static
    {
        return $this->variant('primary');
    }

    /**
     * Set this as an outline action.
     */
    public function outline(): static
    {
        return $this->variant('outline');
    }

    /**
     * Create a view action.
     */
    public static function view(): static
    {
        return static::make('view')
            ->label('View')
            ->icon('eye')
            ->variant('ghost');
    }

    /**
     * Create an edit action.
     */
    public static function edit(): static
    {
        return static::make('edit')
            ->label('Edit')
            ->icon('pencil')
            ->variant('ghost');
    }

    /**
     * Create a delete action with confirmation.
     */
    public static function delete(): static
    {
        return static::make('delete')
            ->label('Delete')
            ->icon('trash-2')
            ->variant('ghost')
            ->requiresConfirmation(
                'Delete Item',
                'Are you sure you want to delete this item? This action cannot be undone.'
            );
    }

    /**
     * Convert the action to array format.
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'label' => $this->label,
            'icon' => $this->icon,
            'variant' => $this->variant,
            'method' => $this->method,
            'route' => $this->route,
            'href' => $this->href,
            'key' => $this->key ?? $this->name,
            'title' => $this->title,
            'navigate' => $this->navigate,
            'requiresConfirmation' => $this->requiresConfirmation,
            'confirmation_title' => $this->confirmationTitle,
            'confirmation_text' => $this->confirmationText,
        ], fn($value) => $value !== null && $value !== false);
    }

    /**
     * Generate a label from the action name.
     */
    protected function generateLabelFromName(string $name): string
    {
        // Convert camelCase and snake_case to readable labels
        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);
        $label = str_replace('_', ' ', $label);
        
        return ucwords($label);
    }
}