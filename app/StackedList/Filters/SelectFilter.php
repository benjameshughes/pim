<?php

namespace App\StackedList\Filters;

class SelectFilter extends Filter
{
    protected string $type = 'select';
    protected array $options = [];

    /**
     * Set the filter options.
     */
    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Load options from a model's enum or constant.
     */
    public function optionsFromEnum(string $enumClass): static
    {
        if (method_exists($enumClass, 'cases')) {
            // Handle PHP 8.1+ enums
            $this->options = collect($enumClass::cases())
                ->mapWithKeys(fn($case) => [$case->value => $case->name])
                ->toArray();
        }
        
        return $this;
    }

    /**
     * Load options from a model's constant array.
     */
    public function optionsFromConstant(string $modelClass, string $constant): static
    {
        if (defined("{$modelClass}::{$constant}")) {
            $this->options = constant("{$modelClass}::{$constant}");
        }
        
        return $this;
    }

    /**
     * Load options from a database relationship.
     */
    public function optionsFromModel(string $modelClass, string $valueField = 'id', string $labelField = 'name'): static
    {
        $this->options = $modelClass::pluck($labelField, $valueField)->toArray();
        return $this;
    }

    /**
     * Convert the filter to array format.
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        $array['options'] = $this->options;
        
        return $array;
    }
}