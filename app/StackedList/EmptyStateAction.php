<?php

namespace App\StackedList;

class EmptyStateAction
{
    protected string $label;
    protected ?string $href = null;
    protected ?string $icon = null;
    protected string $variant = 'primary';

    public function __construct(string $label)
    {
        $this->label = $label;
    }

    public static function make(string $label): static
    {
        return new static($label);
    }

    public function href(string $href): static
    {
        $this->href = $href;
        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function variant(string $variant): static
    {
        $this->variant = $variant;
        return $this;
    }

    public function primary(): static
    {
        return $this->variant('primary');
    }

    public function secondary(): static
    {
        return $this->variant('secondary');
    }

    public function outline(): static
    {
        return $this->variant('outline');
    }

    public function toArray(): array
    {
        return array_filter([
            'label' => $this->label,
            'href' => $this->href,
            'icon' => $this->icon,
            'variant' => $this->variant,
        ], fn($value) => $value !== null);
    }
}