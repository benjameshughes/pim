<?php

namespace App\StackedList\Columns;

class BadgeColumn extends Column
{
    protected string $type = 'badge';
    protected array $badges = [];

    /**
     * Create a new badge column instance.
     */
    public static function make(string $name): static
    {
        return (new static())->name($name);
    }

    /**
     * Define a badge configuration for a specific value.
     */
    public function addBadge(string $value, Badge|string $badge, string $class = '', string $icon = ''): static
    {
        if ($badge instanceof Badge) {
            $this->badges[$value] = $badge->toArray();
        } else {
            // String label - legacy method
            $badgeObj = Badge::make($value, $badge)->class($class)->icon($icon);
            $this->badges[$value] = $badgeObj->toArray();
        }
        
        return $this;
    }

    /**
     * Define multiple badge configurations at once using Badge objects.
     */
    public function withBadges(Badge ...$badges): static
    {
        foreach ($badges as $badge) {
            $this->badges[$badge->value] = $badge->toArray();
        }
        return $this;
    }

    /**
     * Set the default badge configuration for unknown values.
     */
    public function default(string $label = 'Unknown', string $class = 'bg-gray-100 text-gray-800', string $icon = ''): static
    {
        $badge = Badge::make('default', $label)->class($class)->icon($icon);
        $this->badges['default'] = $badge->toArray();
        
        return $this;
    }

    /**
     * Predefined badge set for boolean values.
     */
    public function boolean(string $trueLabel = 'Yes', string $falseLabel = 'No'): static
    {
        return $this
            ->addBadge('1', $trueLabel, 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800', 'check')
            ->addBadge('0', $falseLabel, 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800', 'x')
            ->addBadge('true', $trueLabel, 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800', 'check')
            ->addBadge('false', $falseLabel, 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800', 'x');
    }

    /**
     * Predefined badge set for status values.
     */
    public function status(): static
    {
        return $this
            ->addBadge('active', 'Active', 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800', 'check-circle')
            ->addBadge('inactive', 'Inactive', 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-900/20 dark:text-gray-300 dark:border-gray-800', 'circle')
            ->addBadge('pending', 'Pending', 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800', 'clock')
            ->addBadge('suspended', 'Suspended', 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800', 'ban');
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