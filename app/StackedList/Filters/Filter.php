<?php

namespace App\StackedList\Filters;

use App\StackedList\Contracts\FilterContract;

abstract class Filter implements FilterContract
{
    protected string $name;
    protected string $label;
    protected string $type;
    protected ?string $placeholder = null;

    /**
     * Create a new filter instance.
     */
    public static function make(string $name): static
    {
        return (new static())->name($name);
    }

    /**
     * Set the filter name/key.
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
     * Set the filter label.
     */
    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Set the filter placeholder.
     */
    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    /**
     * Convert the filter to array format.
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'placeholder' => $this->placeholder ?? "Filter by {$this->label}",
        ], fn($value) => $value !== null);
    }

    /**
     * Generate a label from the filter name.
     */
    protected function generateLabelFromName(string $name): string
    {
        // Convert dot notation and snake_case to readable labels
        $label = str_replace(['.', '_'], ' ', $name);
        
        return ucwords($label);
    }
}