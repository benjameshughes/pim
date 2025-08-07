<?php

namespace App\Atom\Navigation;

use App\Atom\Resources\ResourceManager;
use App\Atom\Resources\ResourceRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Navigation Manager
 * 
 * Central registry and manager for all navigation items.
 * Handles auto-discovery, grouping, sorting, and rendering of navigation structures.
 */
class NavigationManager
{
    protected static Collection $builders;
    protected static Collection $items;
    protected static Collection $groups;
    protected static Collection $customItems;
    protected static bool $discovered = false;
    
    /**
     * Initialize collections.
     */
    protected static function init(): void
    {
        if (!isset(static::$builders)) {
            static::$builders = collect();
            static::$items = collect();
            static::$groups = collect();
            static::$customItems = collect();
        }
    }
    
    /**
     * Register a navigation builder.
     */
    public static function register(NavigationBuilder $builder): void
    {
        static::init();
        static::$builders->push($builder);
    }
    
    /**
     * Register a custom navigation item.
     */
    public static function registerCustom(NavigationItem $item): void
    {
        static::init();
        static::$customItems->push($item);
    }
    
    /**
     * Auto-discover navigation from resources.
     */
    public static function discover(): void
    {
        if (static::$discovered) {
            return;
        }
        
        static::init();
        
        $resources = ResourceManager::getResources();
        
        foreach ($resources as $resourceClass) {
            // Check if resource has custom navigation configuration
            if (method_exists($resourceClass, 'getNavigation')) {
                $navigation = $resourceClass::getNavigation();
                if ($navigation instanceof NavigationBuilder) {
                    static::register($navigation);
                    continue;
                }
            }
            
            // Auto-create navigation for resource
            $builder = NavigationBuilder::resource($resourceClass);
            
            // Apply resource properties using getter methods
            if (method_exists($resourceClass, 'getNavigationGroup')) {
                $group = $resourceClass::getNavigationGroup();
                if ($group) {
                    $builder->group($group);
                }
            }
            
            if (method_exists($resourceClass, 'getNavigationSort')) {
                $sort = $resourceClass::getNavigationSort();
                if ($sort !== null) {
                    $builder->sort($sort);
                }
            }
            
            // Check if resource should be hidden from navigation
            if (method_exists($resourceClass, 'shouldRegisterNavigation')) {
                if (!$resourceClass::shouldRegisterNavigation()) {
                    continue;
                }
            }
            
            static::register($builder);
        }
        
        static::$discovered = true;
    }
    
    /**
     * Build all navigation items.
     */
    public static function build(): void
    {
        static::discover();
        static::$items = collect();
        static::$groups = collect();
        
        // Add resource-based navigation items
        foreach (static::$builders as $builder) {
            $item = $builder->buildPrimaryNavigation();
            static::$items->push($item);
            
            // Group items if they have a group
            if ($group = $item->getGroup()) {
                if (!static::$groups->has($group)) {
                    static::$groups->put($group, new NavigationGroup($group));
                }
                static::$groups->get($group)->addItem($item);
            }
        }
        
        // Add custom navigation items
        foreach (static::$customItems as $item) {
            // Only include visible items
            if ($item->getMetadataValue('visible', true)) {
                static::$items->push($item);
                
                // Group custom items if they have a group
                if ($group = $item->getGroup()) {
                    if (!static::$groups->has($group)) {
                        static::$groups->put($group, new NavigationGroup($group));
                    }
                    static::$groups->get($group)->addItem($item);
                }
            }
        }
    }
    
    /**
     * Get all navigation items.
     */
    public static function getItems(): Collection
    {
        static::build();
        return static::$items->sortBy('sort');
    }
    
    /**
     * Get navigation items grouped by group.
     */
    public static function getGroupedItems(): Collection
    {
        static::build();
        
        $grouped = collect();
        $ungrouped = collect();
        
        foreach (static::$items as $item) {
            if ($item->getGroup()) {
                $groupName = $item->getGroup();
                if (!$grouped->has($groupName)) {
                    $grouped->put($groupName, static::$groups->get($groupName));
                }
            } else {
                $ungrouped->push($item);
            }
        }
        
        // Sort groups and add ungrouped items at the end
        $result = $grouped->sortBy(fn($group) => $group->getSort());
        
        if ($ungrouped->isNotEmpty()) {
            $ungroupedGroup = new NavigationGroup('');
            $ungroupedGroup->addItems($ungrouped->toArray());
            $result->put('_ungrouped', $ungroupedGroup);
        }
        
        return $result;
    }
    
    /**
     * Get sub-navigation for a specific resource and record.
     */
    public static function getSubNavigation(string $resourceClass, mixed $record = null): Collection
    {
        static::discover();
        
        $builder = static::$builders->first(function($b) use ($resourceClass) {
            return $b->resourceClass === $resourceClass;
        });
        
        if (!$builder) {
            return collect();
        }
        
        $subNav = $builder->buildSubNavigation($record);
        $relationNav = $builder->buildRelationshipNavigation($record);
        
        return $subNav->merge($relationNav);
    }
    
    /**
     * Generate breadcrumbs for a given route.
     */
    public static function generateBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $currentRoute = Route::current();
        
        if (!$currentRoute) {
            return $breadcrumbs;
        }
        
        $routeName = $currentRoute->getName();
        $routeParameters = $currentRoute->parameters();
        
        // Parse route name to build breadcrumbs
        if (str_starts_with($routeName, 'resources.')) {
            $parts = explode('.', str_replace('resources.', '', $routeName));
            $resourceName = $parts[0] ?? '';
            $page = $parts[1] ?? 'index';
            
            // Find the resource class
            $resourceClass = static::findResourceByName($resourceName);
            
            if ($resourceClass) {
                // Add resource index breadcrumb
                $breadcrumbs[] = [
                    'label' => static::getResourcePluralLabel($resourceClass),
                    'url' => $resourceClass::getUrl('index'),
                ];
                
                // Add record breadcrumb if we have a record
                if (isset($routeParameters['record']) && in_array($page, ['view', 'edit'])) {
                    $record = $resourceClass::resolveRecord($routeParameters['record']);
                    if ($record && method_exists($record, 'getRouteKey')) {
                        $breadcrumbs[] = [
                            'label' => $record->{$resourceClass::getRecordTitleAttribute()} ?? "#{$record->getRouteKey()}",
                            'url' => $resourceClass::getUrl('view', ['record' => $record]),
                        ];
                    }
                }
                
                // Add page breadcrumb if not index
                if ($page !== 'index') {
                    $breadcrumbs[] = [
                        'label' => ucfirst($page),
                        'url' => null, // Current page, no URL
                    ];
                }
            }
        }
        
        return $breadcrumbs;
    }
    
    /**
     * Find a resource class by its name/slug.
     */
    protected static function findResourceByName(string $name): ?string
    {
        $resources = ResourceManager::getResources();
        
        foreach ($resources as $resourceClass) {
            $slug = $resourceClass::getSlug();
            if ($slug === $name) {
                return $resourceClass;
            }
        }
        
        return null;
    }
    
    /**
     * Get the plural label for a resource.
     */
    protected static function getResourcePluralLabel(string $resourceClass): string
    {
        if (method_exists($resourceClass, 'getPluralModelLabel')) {
            return $resourceClass::getPluralModelLabel();
        }
        
        if (method_exists($resourceClass, 'getNavigationLabel')) {
            return $resourceClass::getNavigationLabel();
        }
        
        return class_basename($resourceClass);
    }
    
    /**
     * Clear navigation cache.
     */
    public static function clear(): void
    {
        static::$builders = collect();
        static::$items = collect();
        static::$groups = collect();
        static::$customItems = collect();
        static::$discovered = false;
    }
    
    /**
     * Check if a navigation item is active for the current route.
     */
    public static function isNavigationActive(NavigationItem $item): bool
    {
        $currentRoute = Route::current();
        
        if (!$currentRoute) {
            return false;
        }
        
        $currentUrl = $currentRoute->uri();
        $itemUrl = $item->getUrl();
        
        if (!$itemUrl) {
            return false;
        }
        
        // Remove leading slash for comparison
        $currentUrl = ltrim($currentUrl, '/');
        $itemUrl = ltrim(parse_url($itemUrl, PHP_URL_PATH), '/');
        
        // Exact match or starts with for sub-pages
        return $currentUrl === $itemUrl || str_starts_with($currentUrl, $itemUrl . '/');
    }
    
    /**
     * Render navigation as HTML.
     */
    public static function render(array $options = []): string
    {
        $grouped = static::getGroupedItems();
        
        // This would integrate with your Blade components
        return view('components.navigation.main', [
            'groups' => $grouped,
            'options' => $options,
        ])->render();
    }
    
    /**
     * Get navigation statistics.
     */
    public static function getStatistics(): array
    {
        static::build();
        
        return [
            'total_items' => static::$items->count(),
            'total_groups' => static::$groups->count(),
            'total_builders' => static::$builders->count(),
            'total_custom_items' => static::$customItems->count(),
            'discovered' => static::$discovered,
        ];
    }
}