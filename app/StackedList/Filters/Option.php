<?php

namespace App\StackedList\Filters;

class Option
{
    public function __construct(
        public readonly string $value,
        public readonly string $label
    ) {}

    public static function make(string $value, string $label): static
    {
        return new static($value, $label);
    }
}