<?php

namespace App\StackedList\Columns;

class TextColumn extends Column
{
    protected string $type = 'text';

    /**
     * Create a new text column instance.
     */
    public static function make(string $name): static
    {
        return (new static())->name($name);
    }

    /**
     * Set the column as monospace font (useful for SKUs, codes, etc.).
     */
    public function monospace(): static
    {
        return $this->font('font-mono text-sm');
    }

    /**
     * Set the column text as bold.
     */
    public function bold(): static
    {
        $currentFont = $this->font ?? '';
        return $this->font($currentFont . ' font-bold');
    }

    /**
     * Set the column text as medium weight.
     */
    public function medium(): static
    {
        $currentFont = $this->font ?? '';
        return $this->font($currentFont . ' font-medium');
    }

    /**
     * Set the column text as semibold.
     */
    public function semibold(): static
    {
        $currentFont = $this->font ?? '';
        return $this->font($currentFont . ' font-semibold');
    }

    /**
     * Set text size to small.
     */
    public function small(): static
    {
        $currentFont = $this->font ?? '';
        return $this->font($currentFont . ' text-sm');
    }

    /**
     * Set text size to extra small.
     */
    public function extraSmall(): static
    {
        $currentFont = $this->font ?? '';
        return $this->font($currentFont . ' text-xs');
    }
}