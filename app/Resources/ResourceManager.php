<?php

namespace App\Resources;

use App\Table\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Resource Manager
 * 
 * Core system inspired by FilamentPHP that manages resources across different systems.
 * This is the central hub that coordinates between resource classes and various adapters
 * (Livewire, API, Blade, etc.).
 */
class ResourceManager
{
    /**
     * Registry of all discovered resources.
     */
    protected static array $resources = [];
    
    /**
     * Cache of resolved resource configurations.
     */
    protected static array $configCache = [];
    
    /**
     * Register a resource class.
     */
    public static function register(string $resourceClass): void
    {
        if (!class_exists($resourceClass) || !is_subclass_of($resourceClass, Resource::class)) {
            throw new \InvalidArgumentException(
                "Resource [{$resourceClass}] must exist and extend " . Resource::class
            );
        }
        
        static::$resources[$resourceClass] = $resourceClass;
        
        // Clear cache when registering new resources
        static::clearCache();
    }
    
    /**
     * Get all registered resources.
     */
    public static function getResources(): array
    {
        return static::$resources;
    }
    
    /**
     * Get a specific resource class.
     */
    public static function getResource(string $resourceClass): ?string
    {
        return static::$resources[$resourceClass] ?? null;
    }
    
    /**
     * Check if a resource is registered.
     */
    public static function hasResource(string $resourceClass): bool
    {
        return isset(static::$resources[$resourceClass]);
    }
    
    /**
     * Get resource configuration (cached for performance).
     */
    public static function getResourceConfig(string $resourceClass): array
    {
        if (!static::hasResource($resourceClass)) {
            throw new \InvalidArgumentException("Resource [{$resourceClass}] is not registered.");
        }
        
        // Check cache first
        if (isset(static::$configCache[$resourceClass])) {
            return static::$configCache[$resourceClass];
        }
        
        // Generate and cache configuration
        $config = $resourceClass::configureResource();
        static::$configCache[$resourceClass] = $config;
        
        return $config;
    }
    
    /**
     * Get a configured table instance for a resource.
     */
    public static function getTable(string $resourceClass): Table
    {
        if (!static::hasResource($resourceClass)) {
            throw new \InvalidArgumentException("Resource [{$resourceClass}] is not registered.");
        }
        
        // Create base table with model
        $table = new Table();
        $table->model($resourceClass::getModel());
        
        // Let the resource configure the table
        return $resourceClass::table($table);
    }
    
    /**
     * Get an Eloquent query builder for a resource.
     */
    public static function getQuery(string $resourceClass): Builder
    {
        if (!static::hasResource($resourceClass)) {
            throw new \InvalidArgumentException("Resource [{$resourceClass}] is not registered.");
        }
        
        return $resourceClass::getEloquentQuery();
    }
    
    /**
     * Get a model instance for a resource.
     */
    public static function getModel(string $resourceClass): string
    {
        if (!static::hasResource($resourceClass)) {
            throw new \InvalidArgumentException("Resource [{$resourceClass}] is not registered.");
        }
        
        return $resourceClass::getModel();
    }
    
    /**
     * Find a resource by its slug.
     */
    public static function findBySlug(string $slug): ?string
    {
        foreach (static::$resources as $resourceClass) {
            if ($resourceClass::getSlug() === $slug) {
                return $resourceClass;
            }
        }
        
        return null;
    }
    
    /**
     * Get all resources for navigation (grouped and sorted).
     */
    public static function getNavigationResources(): Collection
    {
        return collect(static::$resources)
            ->map(function ($resourceClass) {
                $config = static::getResourceConfig($resourceClass);
                
                return [
                    'class' => $resourceClass,
                    'label' => $config['navigationLabel'],
                    'icon' => $config['navigationIcon'],
                    'group' => $config['navigationGroup'],
                    'sort' => $config['navigationSort'] ?? 999,
                    'url' => $resourceClass::getUrl('index'),
                ];
            })
            ->groupBy('group')
            ->map(function ($groupResources) {
                return $groupResources->sortBy('sort')->values();
            })
            ->sortKeys();
    }
    
    /**
     * Get resources available for global search.
     */
    public static function getGloballySearchableResources(): array
    {
        return collect(static::$resources)
            ->filter(fn($resourceClass) => $resourceClass::canGloballySearch())
            ->values()
            ->toArray();
    }
    
    /**
     * Perform global search across all searchable resources.
     */
    public static function globalSearch(string $query, int $limit = 50): Collection
    {
        $results = collect();
        
        foreach (static::getGloballySearchableResources() as $resourceClass) {
            $titleAttribute = $resourceClass::getRecordTitleAttribute();
            
            $records = $resourceClass::getEloquentQuery()
                ->where($titleAttribute, 'like', "%{$query}%")
                ->limit($limit)
                ->get();
                
            foreach ($records as $record) {
                $results->push([
                    'resource' => $resourceClass,
                    'record' => $record,
                    'title' => $resourceClass::getGlobalSearchResultTitle($record),
                    'url' => $resourceClass::getUrl('edit', ['record' => $record]),
                ]);
            }
        }
        
        return $results->take($limit);
    }
    
    /**
     * Generate routes for all registered resources.
     */
    public static function generateRoutes(): array
    {
        $routes = [];
        
        foreach (static::$resources as $resourceClass) {
            $config = static::getResourceConfig($resourceClass);
            $slug = $config['slug'];
            
            foreach ($config['pages'] as $pageName => $pageConfig) {
                $routes[] = [
                    'name' => "resources.{$slug}.{$pageName}",
                    'uri' => trim($slug . $pageConfig['route'], '/'),
                    'component' => $pageConfig['component'],
                    'resource' => $resourceClass,
                    'page' => $pageName,
                ];
            }
        }
        
        return $routes;
    }
    
    /**
     * Resolve a record from a resource and ID.
     */
    public static function resolveRecord(string $resourceClass, mixed $recordId): Model
    {
        if (!static::hasResource($resourceClass)) {
            throw new \InvalidArgumentException("Resource [{$resourceClass}] is not registered.");
        }
        
        $model = static::getModel($resourceClass);
        
        return $model::findOrFail($recordId);
    }
    
    /**
     * Get resource statistics.
     */
    public static function getStatistics(): array
    {
        return [
            'total_resources' => count(static::$resources),
            'searchable_resources' => count(static::getGloballySearchableResources()),
            'navigation_groups' => static::getNavigationResources()->keys()->count(),
            'total_routes' => count(static::generateRoutes()),
        ];
    }
    
    /**
     * Clear all cached data.
     */
    public static function clearCache(): void
    {
        static::$configCache = [];
        
        // Clear Laravel cache if needed
        Cache::forget('resource_manager.routes');
        Cache::forget('resource_manager.navigation');
    }
    
    /**
     * Validate all registered resources.
     */
    public static function validateResources(): array
    {
        $errors = [];
        
        foreach (static::$resources as $resourceClass) {
            try {
                // Test model exists
                $model = $resourceClass::getModel();
                if (!class_exists($model)) {
                    $errors[] = "Resource [{$resourceClass}] references non-existent model [{$model}].";
                }
                
                // Test table configuration
                $table = static::getTable($resourceClass);
                
                // Test configuration
                $config = static::getResourceConfig($resourceClass);
                
            } catch (\Exception $e) {
                $errors[] = "Resource [{$resourceClass}] validation failed: " . $e->getMessage();
            }
        }
        
        return $errors;
    }
    
    /**
     * Get detailed resource information for debugging.
     */
    public static function getResourceInfo(string $resourceClass): array
    {
        if (!static::hasResource($resourceClass)) {
            throw new \InvalidArgumentException("Resource [{$resourceClass}] is not registered.");
        }
        
        $config = static::getResourceConfig($resourceClass);
        
        return [
            'class' => $resourceClass,
            'config' => $config,
            'model' => $resourceClass::getModel(),
            'pages' => $resourceClass::getPages(),
            'routes' => collect(static::generateRoutes())
                ->where('resource', $resourceClass)
                ->values()
                ->toArray(),
        ];
    }
}