<?php

namespace App\StackedList\Filters;

class SelectFilter extends Filter
{
    protected string $type = 'select';
    protected array $options = [];
    protected ?string $column = null;

    /**
     * Set the database column this filter applies to.
     */
    public function column(string $column): static
    {
        $this->column = $column;
        return $this;
    }

    /**
     * Set the filter options from array.
     */
    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Add a single option using fluent builder.
     */
    public function option(string $value, string $label): static
    {
        $this->options[$value] = $label;
        return $this;
    }

    /**
     * Add multiple options using Option objects.
     */
    public function withOptions(Option ...$options): static
    {
        foreach ($options as $option) {
            $this->options[$option->value] = $option->label;
        }
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
    public function optionsFromModel(string $modelClass, string $labelField = 'name', string $valueField = 'id', array $orderBy = []): static
    {
        $query = $modelClass::query();
        
        // Apply ordering if specified
        if (!empty($orderBy)) {
            foreach ($orderBy as $column) {
                $query->orderBy($column);
            }
        }
        
        $this->options = $query->pluck($labelField, $valueField)->toArray();
        return $this;
    }

    /**
     * Convert the filter to array format.
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        $array['options'] = $this->options;
        
        if ($this->column !== null) {
            $array['column'] = $this->column;
        }
        
        return $array;
    }
}