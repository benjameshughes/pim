<?php

namespace App\Forms\Components;

/**
 * 🎯 SELECT DROPDOWN FIELD
 */
class Select extends FormField
{
    protected array $options = [];

    protected bool $multiple = false;

    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function render(): string
    {
        return view('forms.components.select', [
            'field' => $this,
            'options' => $this->options,
            'multiple' => $this->multiple,
        ])->render();
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'options' => $this->options,
            'multiple' => $this->multiple,
        ]);
    }
}
