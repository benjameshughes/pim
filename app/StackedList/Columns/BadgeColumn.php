<?php

namespace App\StackedList\Columns;

class BadgeColumn extends Column
{
    protected string $type = 'badge';
    protected array $badges = [];

    /**
     * Define a badge configuration for a specific value.
     */
    public function badge(string $value, string $label, string $class = '', string $icon = ''): static
    {
        $this->badges[$value] = [
            'label' => $label,
            'class' => $class,
            'icon' => $icon,
        ];
        
        return $this;
    }

    /**
     * Define multiple badge configurations at once.
     */
    public function badges(array $badges): static
    {
        $this->badges = array_merge($this->badges, $badges);
        return $this;
    }

    /**
     * Set the default badge configuration for unknown values.
     */
    public function default(string $label = 'Unknown', string $class = 'bg-gray-100 text-gray-800', string $icon = ''): static
    {
        $this->badges['default'] = [
            'label' => $label,
            'class' => $class,
            'icon' => $icon,
        ];
        
        return $this;
    }

    /**
     * Predefined badge set for boolean values.
     */
    public function boolean(string $trueLabel = 'Yes', string $falseLabel = 'No'): static
    {
        return $this
            ->badge('1', $trueLabel, 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800', 'check')
            ->badge('0', $falseLabel, 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800', 'x')
            ->badge('true', $trueLabel, 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800', 'check')
            ->badge('false', $falseLabel, 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800', 'x');
    }

    /**
     * Predefined badge set for status values.
     */
    public function status(): static
    {
        return $this
            ->badge('active', 'Active', 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800', 'check-circle')
            ->badge('inactive', 'Inactive', 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-900/20 dark:text-gray-300 dark:border-gray-800', 'circle')
            ->badge('pending', 'Pending', 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800', 'clock')
            ->badge('suspended', 'Suspended', 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800', 'ban');
    }

    /**
     * Convert the column to array format.
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        $array['badges'] = $this->badges;
        
        return $array;
    }
}