<?php

namespace App\Forms\Components;

/**
 * 🎨 BASE FORM FIELD
 *
 * Abstract base for all form field components.
 * Inspired by FilamentPHP's approach to form field rendering.
 */
abstract class FormField
{
    protected string $name;

    protected string $label;

    protected mixed $value = null;

    protected bool $required = false;

    protected ?string $placeholder = null;

    protected array $attributes = [];

    protected ?string $description = null;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->label = ucfirst(str_replace('_', ' ', $name));
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function value(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function attributes(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * 🎨 RENDER FIELD TO HTML
     */
    abstract public function render(): string;

    /**
     * 📊 GET FIELD CONFIGURATION ARRAY
     */
    public function toArray(): array
    {
        return [
            'type' => class_basename($this),
            'name' => $this->name,
            'label' => $this->label,
            'value' => $this->value,
            'required' => $this->required,
            'placeholder' => $this->placeholder,
            'description' => $this->description,
            'attributes' => $this->attributes,
        ];
    }
}
