<?php

namespace App\Atom\Navigation;

/**
 * Navigation - Custom Navigation Builder
 * 
 * Fluent API for creating custom navigation items that aren't tied to resources.
 * Works alongside ResourceManager auto-discovery for a unified navigation system.
 * 
 * Usage:
 * Navigation::make()
 *     ->label('Import Data')
 *     ->url('/import')
 *     ->icon('upload')
 *     ->group('Data Management')
 *     ->register();
 */
class Navigation
{
    protected string $label;
    protected ?string $url = null;
    protected ?string $route = null;
    protected ?string $icon = null;
    protected ?string $badge = null;
    protected ?string $group = null;
    protected int $sort = 0;
    protected bool $openInNewTab = false;
    protected bool $visible = true;
    protected array $metadata = [];
    
    /**
     * Private constructor - use make() static method.
     */
    private function __construct()
    {
        //
    }
    
    /**
     * Static factory method for fluent API.
     */
    public static function make(): static
    {
        return new static();
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
     * Set the navigation URL (direct URL).
     */
    public function url(string $url): static
    {
        $this->url = $url;
        $this->route = null; // Clear route if URL is set
        return $this;
    }
    
    /**
     * Set the navigation route (Laravel route name).
     */
    public function route(string $route, array $parameters = []): static
    {
        $this->route = $route;
        $this->metadata['route_parameters'] = $parameters;
        $this->url = null; // Clear URL if route is set
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
        $this->badge = $badge;
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
     * Set navigation sort order.
     */
    public function sort(int $sort): static
    {
        $this->sort = $sort;
        return $this;
    }
    
    /**
     * Make the link open in a new tab.
     */
    public function openInNewTab(bool $openInNewTab = true): static
    {
        $this->openInNewTab = $openInNewTab;
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
     * Add custom metadata.
     */
    public function metadata(string $key, mixed $value): static
    {
        $this->metadata[$key] = $value;
        return $this;
    }
    
    /**
     * Register this navigation item with NavigationManager.
     */
    public function register(): NavigationItem
    {
        if (!isset($this->label)) {
            throw new \InvalidArgumentException('Navigation item must have a label');
        }
        
        $item = $this->build();
        NavigationManager::registerCustom($item);
        
        return $item;
    }
    
    /**
     * Build the NavigationItem without registering it.
     */
    public function build(): NavigationItem
    {
        if (!isset($this->label)) {
            throw new \InvalidArgumentException('Navigation item must have a label');
        }
        
        $item = new NavigationItem($this->label);
        
        // Set URL (either direct URL or resolve route)
        if ($this->url) {
            $item->url($this->url);
        } elseif ($this->route) {
            $parameters = $this->metadata['route_parameters'] ?? [];
            // Defer route resolution until needed to avoid boot-time issues
            try {
                $item->url(route($this->route, $parameters));
            } catch (\Exception $e) {
                // If route doesn't exist during boot, store route info for later resolution
                $item->metadata('deferred_route', $this->route);
                $item->metadata('deferred_route_parameters', $parameters);
                $item->url('#'); // Temporary placeholder
            }
        } else {
            throw new \InvalidArgumentException('Navigation item must have either a URL or route');
        }
        
        // Configure other properties
        if ($this->icon) {
            $item->icon($this->icon);
        }
        
        if ($this->badge) {
            $item->badge($this->badge);
        }
        
        if ($this->group) {
            $item->group($this->group);
        }
        
        $item->sort($this->sort);
        
        // Add metadata
        if ($this->openInNewTab) {
            $item->metadata('target', '_blank');
        }
        
        $item->metadata('visible', $this->visible);
        
        foreach ($this->metadata as $key => $value) {
            if ($key !== 'route_parameters') { // Skip internal metadata
                $item->metadata($key, $value);
            }
        }
        
        return $item;
    }
    
    /**
     * Create multiple navigation items at once.
     */
    public static function createGroup(string $groupName, array $items): array
    {
        $navigationItems = [];
        
        foreach ($items as $item) {
            if ($item instanceof static) {
                $navigationItems[] = $item->group($groupName)->register();
            }
        }
        
        return $navigationItems;
    }
    
    /**
     * Create a divider/separator in navigation.
     */
    public static function separator(string $group = null): NavigationItem
    {
        return static::make()
            ->label('---')
            ->url('#')
            ->metadata('type', 'separator')
            ->group($group)
            ->register();
    }
    
    /**
     * Create an external link navigation item.
     */
    public static function external(string $label, string $url): static
    {
        return static::make()
            ->label($label)
            ->url($url)
            ->icon('external-link')
            ->openInNewTab();
    }
}