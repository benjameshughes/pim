<?php

namespace App\StackedList\Columns;

class Badge
{
    public function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly string $class = '',
        public readonly string $icon = ''
    ) {}

    public static function make(string $value = '', string $label = ''): static
    {
        return new static($value, $label);
    }

    public function class(string $class): static
    {
        return new static($this->value, $this->label, $class, $this->icon);
    }

    public function icon(string $icon): static
    {
        return new static($this->value, $this->label, $this->class, $icon);
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'class' => $this->class,
            'icon' => $this->icon,
        ];
    }
}