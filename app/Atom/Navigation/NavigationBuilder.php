<?php

namespace App\Atom\Navigation;

use App\Atom\Resources\Resource;
use Illuminate\Support\Collection;

/**
 * Navigation Builder
 * 
 * Fluent API builder for creating navigation structures from resources.
 * Supports auto-discovery, grouping, nested navigation, and relationship handling.
 */
class NavigationBuilder
{
    public string $resourceClass;
    protected ?string $label = null;
    protected ?string $icon = null;
    protected ?string $group = null;
    protected ?string $parent = null;
    protected $badge = null;
    protected array $pages = [];
    protected array $subNavigation = [];
    protected array $relationships = [];
    protected int $sort = 0;
    protected bool $visible = true;
    
    public function __construct(string $resourceClass)
    {
        $this->resourceClass = $resourceClass;
    }
    
    /**
     * Static factory method for fluent API.
     */
    public static function resource(string $resourceClass): static
    {
        return new static($resourceClass);
    }
    
    /**
     * Set the navigation label.
     */
    public function label(string $label): static
    {
        $this->label = $label;
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
     * Set a badge for the navigation item.
     */
    public function badge(callable $badge): static
    {
        $this->badge = $badge;
        return $this;
    }
    
    /**
     * Define custom pages for the resource.
     */
    public function pages(array $pages): static
    {
        $this->pages = $pages;
        return $this;
    }
    
    /**
     * Define sub-navigation for resource records.
     */
    public function subNavigation(array $subNavigation): static
    {
        $this->subNavigation = $subNavigation;
        return $this;
    }
    
    /**
     * Define relationships for nested navigation.
     */
    public function relationships(array $relationships): static
    {
        $this->relationships = $relationships;
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
     * Set navigation visibility.
     */
    public function visible(bool $visible = true): static
    {
        $this->visible = $visible;
        return $this;
    }
    
    /**
     * Hide the navigation item.
     */
    public function hidden(): static
    {
        return $this->visible(false);
    }
    
    /**
     * Build the primary navigation item for the resource.
     */
    public function buildPrimaryNavigation(): NavigationItem
    {
        $resourceClass = $this->resourceClass;
        
        $item = new NavigationItem($this->getNavigationLabel());
        
        // Defer URL resolution to avoid boot-time route resolution issues
        try {
            $item->url($resourceClass::getUrl('index'));
        } catch (\Exception $e) {
            // Store resource info for deferred URL resolution
            $item->metadata('deferred_resource_url', ['resource' => $resourceClass, 'page' => 'index']);
            $item->url('#'); // Temporary placeholder
        }
        
        $item->icon($this->getNavigationIcon())
            ->sort($this->sort);
            
        if ($this->group) {
            $item->group($this->group);
        }
        
        if ($this->parent) {
            $item->parent($this->parent);
        }
        
        if ($this->badge) {
            $item->badge(($this->badge)());
        }
        
        // Add metadata for resource information
        $item->metadata([
            'resource' => $this->resourceClass,
            'type' => 'resource',
            'pages' => $this->pages,
            'sub_navigation' => $this->subNavigation,
            'relationships' => $this->relationships,
        ]);
        
        return $item;
    }
    
    /**
     * Build sub-navigation items for resource records.
     */
    public function buildSubNavigation(mixed $record = null): Collection
    {
        $items = collect();
        $resourceClass = $this->resourceClass;
        
        // Add default CRUD pages if not overridden
        $defaultPages = [
            'view' => 'View',
            'edit' => 'Edit',
        ];
        
        $pages = array_merge($defaultPages, $this->pages);
        
        foreach ($pages as $page => $label) {
            if ($resourceClass::hasPage($page)) {
                $item = new NavigationItem($label);
                $item->url($resourceClass::getUrl($page, ['record' => $record]))
                    ->metadata([
                        'resource' => $this->resourceClass,
                        'page' => $page,
                        'type' => 'sub_navigation',
                    ]);
                
                $items->push($item);
            }
        }
        
        // Add custom sub-navigation items
        foreach ($this->subNavigation as $key => $label) {
            $item = new NavigationItem($label);
            $item->url($resourceClass::getUrl($key, ['record' => $record]))
                ->metadata([
                    'resource' => $this->resourceClass,
                    'page' => $key,
                    'type' => 'sub_navigation',
                ]);
            
            $items->push($item);
        }
        
        return $items;
    }
    
    /**
     * Build relationship navigation items.
     */
    public function buildRelationshipNavigation(mixed $record = null): Collection
    {
        $items = collect();
        $resourceClass = $this->resourceClass;
        
        foreach ($this->relationships as $relation => $relationResourceClass) {
            $label = $this->getRelationLabel($relation, $relationResourceClass);
            
            $item = new NavigationItem($label);
            $item->url($resourceClass::getUrl('index') . "/{$record->getKey()}/{$relation}")
                ->metadata([
                    'resource' => $this->resourceClass,
                    'relation' => $relation,
                    'relation_resource' => $relationResourceClass,
                    'type' => 'relationship',
                    'parent_record' => $record->getKey(),
                ]);
            
            $items->push($item);
        }
        
        return $items;
    }
    
    /**
     * Get the navigation label.
     */
    protected function getNavigationLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }
        
        $resourceClass = $this->resourceClass;
        
        // Try to get from resource method
        if (method_exists($resourceClass, 'getNavigationLabel')) {
            return $resourceClass::getNavigationLabel();
        }
        
        // Ultimate fallback
        return class_basename($resourceClass);
    }
    
    /**
     * Get the navigation icon.
     */
    protected function getNavigationIcon(): ?string
    {
        if ($this->icon) {
            return $this->icon;
        }
        
        $resourceClass = $this->resourceClass;
        
        // Try to get from resource method
        if (method_exists($resourceClass, 'getNavigationIcon')) {
            return $resourceClass::getNavigationIcon();
        }
        
        return null;
    }
    
    /**
     * Get a label for a relationship.
     */
    protected function getRelationLabel(string $relation, string $relationResourceClass): string
    {
        // Try to get plural label from relation resource
        if (method_exists($relationResourceClass, 'getPluralModelLabel')) {
            return $relationResourceClass::getPluralModelLabel();
        }
        
        // Fall back to relation name with title case
        return str_replace('_', ' ', ucwords($relation, '_'));
    }
    
    /**
     * Register the navigation with the NavigationManager.
     */
    public function register(): void
    {
        NavigationManager::register($this);
    }
}