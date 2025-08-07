<?php

namespace App\Atom\Navigation;

use Illuminate\Support\Collection;

/**
 * Navigation Group
 * 
 * Represents a logical grouping of navigation items.
 * Provides methods for organizing and sorting navigation items within groups.
 */
class NavigationGroup
{
    protected string $label;
    protected ?string $icon = null;
    protected Collection $items;
    protected int $sort = 0;
    protected bool $collapsible = false;
    protected bool $collapsed = false;
    
    public function __construct(string $label)
    {
        $this->label = $label;
        $this->items = collect();
    }
    
    /**
     * Set the group icon.
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }
    
    /**
     * Set the group sort order.
     */
    public function sort(int $sort): static
    {
        $this->sort = $sort;
        return $this;
    }
    
    /**
     * Make the group collapsible.
     */
    public function collapsible(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;
        return $this;
    }
    
    /**
     * Set the group collapsed state.
     */
    public function collapsed(bool $collapsed = true): static
    {
        $this->collapsed = $collapsed;
        return $this;
    }
    
    /**
     * Add a navigation item to the group.
     */
    public function addItem(NavigationItem $item): static
    {
        $this->items->push($item);
        return $this;
    }
    
    /**
     * Add multiple navigation items to the group.
     */
    public function addItems(array $items): static
    {
        foreach ($items as $item) {
            $this->addItem($item);
        }
        return $this;
    }
    
    /**
     * Get the group label.
     */
    public function getLabel(): string
    {
        return $this->label;
    }
    
    /**
     * Get the group icon.
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }
    
    /**
     * Get the group sort order.
     */
    public function getSort(): int
    {
        return $this->sort;
    }
    
    /**
     * Check if the group is collapsible.
     */
    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }
    
    /**
     * Check if the group is collapsed.
     */
    public function isCollapsed(): bool
    {
        return $this->collapsed;
    }
    
    /**
     * Get all navigation items in the group, sorted.
     */
    public function getItems(): Collection
    {
        return $this->items->sortBy('sort');
    }
    
    /**
     * Check if the group has any items.
     */
    public function hasItems(): bool
    {
        return $this->items->isNotEmpty();
    }
    
    /**
     * Get the count of items in the group.
     */
    public function getItemCount(): int
    {
        return $this->items->count();
    }
    
    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'icon' => $this->icon,
            'sort' => $this->sort,
            'collapsible' => $this->collapsible,
            'collapsed' => $this->collapsed,
            'items' => $this->getItems()->map(fn($item) => $item->toArray())->toArray(),
        ];
    }
}