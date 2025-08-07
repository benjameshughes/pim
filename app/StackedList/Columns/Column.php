<?php

namespace App\StackedList\Columns;

use App\StackedList\Contracts\ColumnContract;

class Column implements ColumnContract
{
    protected string $name;
    protected string $label;
    protected string $type = 'text'; // Default to text column
    protected bool $sortable = false;
    protected bool $searchable = false;
    protected ?string $class = null;
    protected ?string $font = null;

    /**
     * Create a new column instance.
     */
    public static function make(string $name): static
    {
        return (new static())->name($name);
    }

    /**
     * Set the column name/key.
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
     * Set the column label.
     */
    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Make the column sortable.
     */
    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;
        return $this;
    }

    /**
     * Make the column searchable.
     */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;
        return $this;
    }

    /**
     * Set CSS classes for the column.
     */
    public function class(string $class): static
    {
        $this->class = $class;
        return $this;
    }

    /**
     * Set font classes for the column.
     */
    public function font(string $font): static
    {
        $this->font = $font;
        return $this;
    }

    /**
     * Create a text column.
     */
    public static function text(string $name): TextColumn
    {
        return TextColumn::make($name);
    }

    /**
     * Convert this column to a badge column.
     */
    public function badge(): BadgeColumn
    {
        $badgeColumn = BadgeColumn::make($this->name);
        
        // Copy all properties from this column to the badge column
        $badgeColumn->label($this->label ?? $this->generateLabelFromName($this->name));
        if ($this->sortable) $badgeColumn->sortable();
        if ($this->searchable) $badgeColumn->searchable();
        if ($this->class) $badgeColumn->class($this->class);
        if ($this->font) $badgeColumn->font($this->font);
        
        return $badgeColumn;
    }


    /**
     * Get the column name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if the column is sortable.
     */
    public function isSortable(): bool
    {
        return $this->sortable;
    }

    /**
     * Check if the column is searchable.
     */
    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * Convert the column to array format.
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'sortable' => $this->sortable,
            'searchable' => $this->searchable,
            'class' => $this->class,
            'font' => $this->font,
        ], fn($value) => $value !== null);
    }

    /**
     * Generate a label from the column name.
     */
    protected function generateLabelFromName(string $name): string
    {
        // Convert dot notation and snake_case to readable labels
        $label = str_replace(['.', '_'], ' ', $name);
        
        // Handle relationship notation (e.g., "author.name" -> "Author Name")
        return ucwords($label);
    }
}