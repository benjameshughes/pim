<?php

namespace App\Navigation\Concerns;

use App\Atom\Navigation\NavigationBuilder;
use App\Atom\Navigation\NavigationItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Has Nested Navigation
 * 
 * Trait for resources that support nested/relationship navigation.
 * Provides methods for auto-discovering relationships and generating nested routes.
 */
trait HasNestedNavigation
{
    /**
     * Get relationships that should be included in navigation.
     */
    public static function getNavigationRelationships(): array
    {
        return [];
    }
    
    /**
     * Auto-discover relationships from the model.
     */
    public static function discoverRelationships(): array
    {
        $model = app(static::getModel());
        $relationships = [];
        
        // Get all methods on the model that return relationships
        $reflection = new \ReflectionClass($model);
        
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip non-relationship methods
            if ($method->getNumberOfParameters() > 0) {
                continue;
            }
            
            if ($method->isStatic() || $method->isAbstract()) {
                continue;
            }
            
            $name = $method->getName();
            
            // Skip common non-relationship methods
            if (in_array($name, [
                'getKey', 'getRouteKey', 'getTable', 'toArray', 'toJson',
                'save', 'delete', 'refresh', 'replicate', 'getAttributes',
                'getDirty', 'wasChanged', 'getOriginal', 'syncOriginal',
            ])) {
                continue;
            }
            
            try {
                $result = $model->{$name}();
                
                if ($result instanceof Relation) {
                    // Try to determine the related resource
                    $relatedModel = $result->getRelated();
                    $relatedResource = static::findResourceForModel(get_class($relatedModel));
                    
                    if ($relatedResource) {
                        $relationships[$name] = $relatedResource;
                    }
                }
            } catch (\Exception $e) {
                // Skip methods that throw exceptions
                continue;
            }
        }
        
        return $relationships;
    }
    
    /**
     * Find a resource class for a given model.
     */
    protected static function findResourceForModel(string $modelClass): ?string
    {
        // This would integrate with ResourceRegistry to find matching resources
        $resources = app('App\Resources\ResourceRegistry')->getResources();
        
        foreach ($resources as $resourceClass) {
            if ($resourceClass::getModel() === $modelClass) {
                return $resourceClass;
            }
        }
        
        return null;
    }
    
    /**
     * Build relationship navigation items.
     */
    public static function buildRelationshipNavigation(Model $record): Collection
    {
        $items = collect();
        
        // Get explicitly defined relationships
        $explicitRelationships = static::getNavigationRelationships();
        
        // Auto-discover if no explicit relationships defined
        $relationships = empty($explicitRelationships) 
            ? static::discoverRelationships()
            : $explicitRelationships;
        
        foreach ($relationships as $relationName => $resourceClass) {
            // Create navigation item for relationship
            $item = new NavigationItem(static::getRelationshipLabel($relationName, $resourceClass));
            
            // Build nested URL
            $url = static::getUrl('index') . '/' . $record->getRouteKey() . '/' . Str::kebab($relationName);
            $item->url($url);
            
            // Add metadata
            $item->metadata([
                'type' => 'relationship',
                'relation' => $relationName,
                'parent_resource' => static::class,
                'related_resource' => $resourceClass,
                'parent_record' => $record->getRouteKey(),
            ]);
            
            $items->push($item);
        }
        
        return $items;
    }
    
    /**
     * Get a human-readable label for a relationship.
     */
    protected static function getRelationshipLabel(string $relationName, string $resourceClass): string
    {
        // Try to get from related resource
        if (method_exists($resourceClass, 'getPluralModelLabel')) {
            return $resourceClass::getPluralModelLabel();
        }
        
        // Fall back to relation name formatting
        return Str::title(str_replace('_', ' ', $relationName));
    }
    
    /**
     * Generate nested routes for relationships.
     */
    public static function getRelationshipRoutes(): array
    {
        $routes = [];
        $relationships = static::getNavigationRelationships() ?: static::discoverRelationships();
        
        foreach ($relationships as $relationName => $resourceClass) {
            $route = [
                'name' => static::getSlug() . '.' . Str::kebab($relationName) . '.index',
                'uri' => static::getSlug() . '/{record}/' . Str::kebab($relationName),
                'resource' => static::class,
                'page' => 'relationship',
                'relationship' => $relationName,
                'related_resource' => $resourceClass,
            ];
            
            $routes[] = $route;
        }
        
        return $routes;
    }
    
    /**
     * Handle relationship page rendering.
     */
    public static function renderRelationshipPage(string $relationName, Model $parentRecord): array
    {
        $relationships = static::getNavigationRelationships() ?: static::discoverRelationships();
        $resourceClass = $relationships[$relationName] ?? null;
        
        if (!$resourceClass) {
            throw new \Exception("Relationship [{$relationName}] not found in resource.");
        }
        
        // Get the related records
        $relatedRecords = $parentRecord->{$relationName}();
        
        // Return data for rendering
        return [
            'parent_record' => $parentRecord,
            'related_records' => $relatedRecords,
            'related_resource' => $resourceClass,
            'relationship' => $relationName,
        ];
    }
    
    /**
     * Build sub-navigation that includes relationships.
     */
    public static function buildEnhancedSubNavigation(Model $record): Collection
    {
        $items = collect();
        
        // Add standard sub-navigation
        $standardSubNav = static::getRecordSubNavigation($record);
        foreach ($standardSubNav as $page => $label) {
            $item = new NavigationItem($label);
            $item->url(static::getUrl($page, ['record' => $record]));
            $item->metadata(['type' => 'sub_navigation', 'page' => $page]);
            $items->push($item);
        }
        
        // Add relationship navigation
        $relationshipNav = static::buildRelationshipNavigation($record);
        $items = $items->merge($relationshipNav);
        
        return $items;
    }
}