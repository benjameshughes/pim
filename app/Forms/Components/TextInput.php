<?php

namespace App\Forms\Components;

/**
 * 📝 TEXT INPUT FIELD
 */
class TextInput extends FormField
{
    protected string $type = 'text';

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function email(): static
    {
        return $this->type('email');
    }

    public function password(): static
    {
        return $this->type('password');
    }

    public function number(): static
    {
        return $this->type('number');
    }

    public function render(): string
    {
        $attributes = array_merge([
            'type' => $this->type,
            'name' => $this->name,
            'id' => $this->name,
            'wire:model.live' => "formData.{$this->name}",
            'placeholder' => $this->placeholder,
            'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
        ], $this->attributes);

        if ($this->required) {
            $attributes['required'] = true;
        }

        $attributeString = collect($attributes)
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value, $key) => is_bool($value) ? $key : "{$key}=\"{$value}\"")
            ->implode(' ');

        return view('forms.components.text-input', [
            'field' => $this,
            'attributes' => $attributeString,
        ])->render();
    }
}
