<?php

namespace App\Atom\Navigation;

/**
 * Navigation Item
 * 
 * Represents a single navigation item with label, URL, icon, and metadata.
 * Used for building navigation menus with support for grouping, badges, and relationships.
 */
class NavigationItem
{
    protected string $label;
    protected ?string $url = null;
    protected ?string $icon = null;
    protected ?string $badge = null;
    protected ?string $group = null;
    protected ?string $parent = null;
    protected array $children = [];
    protected array $metadata = [];
    protected bool $active = false;
    protected int $sort = 0;
    
    public function __construct(string $label)
    {
        $this->label = $label;
    }
    
    /**
     * Set the navigation URL.
     */
    public function url(string $url): static
    {
        $this->url = $url;
        return $this;
    }
    
    /**
     * Set the navigation icon.
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }
    
    /**
     * Set a badge for the navigation item.
     */
    public function badge(string|callable $badge): static
    {
        $this->badge = is_callable($badge) ? $badge() : $badge;
        return $this;
    }
    
    /**
     * Set the navigation group.
     */
    public function group(string $group): static
    {
        $this->group = $group;
        return $this;
    }
    
    /**
     * Set the parent navigation item.
     */
    public function parent(string $parent): static
    {
        $this->parent = $parent;
        return $this;
    }
    
    /**
     * Add a child navigation item.
     */
    public function child(NavigationItem $child): static
    {
        $this->children[] = $child;
        return $this;
    }
    
    /**
     * Set navigation sort order.
     */
    public function sort(int $sort): static
    {
        $this->sort = $sort;
        return $this;
    }
    
    /**
     * Mark navigation item as active.
     */
    public function active(bool $active = true): static
    {
        $this->active = $active;
        return $this;
    }
    
    /**
     * Set arbitrary metadata.
     */
    public function metadata(string|array $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->metadata = array_merge($this->metadata, $key);
        } else {
            $this->metadata[$key] = $value;
        }
        return $this;
    }
    
    /**
     * Get specific metadata value.
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
    
    /**
     * Get the navigation label.
     */
    public function getLabel(): string
    {
        return $this->label;
    }
    
    /**
     * Get the navigation URL.
     */
    public function getUrl(): ?string
    {
        // Resolve deferred route if needed
        if ($this->url === '#' && isset($this->metadata['deferred_route'])) {
            try {
                $routeName = $this->metadata['deferred_route'];
                $parameters = $this->metadata['deferred_route_parameters'] ?? [];
                $this->url = route($routeName, $parameters);
                
                // Clean up metadata
                unset($this->metadata['deferred_route']);
                unset($this->metadata['deferred_route_parameters']);
            } catch (\Exception $e) {
                // If route still doesn't exist, return placeholder
                return '#';
            }
        }
        
        // Resolve deferred resource URL if needed
        if ($this->url === '#' && isset($this->metadata['deferred_resource_url'])) {
            try {
                $resourceInfo = $this->metadata['deferred_resource_url'];
                $resourceClass = $resourceInfo['resource'];
                $page = $resourceInfo['page'];
                $this->url = $resourceClass::getUrl($page);
                
                // Clean up metadata
                unset($this->metadata['deferred_resource_url']);
            } catch (\Exception $e) {
                // If resource URL still can't be resolved, return placeholder
                return '#';
            }
        }
        
        return $this->url;
    }
    
    /**
     * Get the navigation icon.
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }
    
    /**
     * Get the navigation badge.
     */
    public function getBadge(): ?string
    {
        return $this->badge;
    }
    
    /**
     * Get the navigation group.
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }
    
    /**
     * Get the parent navigation item.
     */
    public function getParent(): ?string
    {
        return $this->parent;
    }
    
    /**
     * Get child navigation items.
     */
    public function getChildren(): array
    {
        return $this->children;
    }
    
    /**
     * Get navigation sort order.
     */
    public function getSort(): int
    {
        return $this->sort;
    }
    
    /**
     * Check if navigation item is active.
     */
    public function isActive(): bool
    {
        return $this->active;
    }
    
    /**
     * Get metadata.
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
    
    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'url' => $this->url,
            'icon' => $this->icon,
            'badge' => $this->badge,
            'group' => $this->group,
            'parent' => $this->parent,
            'children' => array_map(fn($child) => $child->toArray(), $this->children),
            'sort' => $this->sort,
            'active' => $this->active,
            'metadata' => $this->metadata,
        ];
    }
}