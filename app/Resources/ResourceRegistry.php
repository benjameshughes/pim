<?php

namespace App\Resources;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use SplFileInfo;

/**
 * Resource Registry
 * 
 * FilamentPHP-inspired auto-discovery system that automatically finds and registers
 * all resource classes in the application. This is the magic that makes resources
 * "just work" without manual registration!
 */
class ResourceRegistry
{
    /**
     * Directories to scan for resources.
     */
    protected static array $scanDirectories = [
        'app/Resources',
    ];
    
    /**
     * Resource namespace pattern.
     */
    protected static string $resourceNamespace = 'App\\Resources\\';
    
    /**
     * Auto-discover and register all resources.
     */
    public static function discover(): void
    {
        $resources = static::findAllResources();
        
        foreach ($resources as $resourceClass) {
            try {
                ResourceManager::register($resourceClass);
            } catch (\Exception $e) {
                // Log error but continue discovery
                \Log::warning("Failed to register resource [{$resourceClass}]: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Find all resource classes in the application.
     */
    public static function findAllResources(): array
    {
        $resources = [];
        
        foreach (static::$scanDirectories as $directory) {
            $fullPath = base_path($directory);
            
            if (!File::isDirectory($fullPath)) {
                continue;
            }
            
            $resources = array_merge($resources, static::findResourcesInDirectory($fullPath));
        }
        
        return array_unique($resources);
    }
    
    /**
     * Find resource classes in a specific directory.
     */
    protected static function findResourcesInDirectory(string $directory): array
    {
        $resources = [];
        
        $files = File::allFiles($directory);
        
        foreach ($files as $file) {
            $resourceClass = static::getClassFromFile($file);
            
            if ($resourceClass && static::isValidResourceClass($resourceClass)) {
                $resources[] = $resourceClass;
            }
        }
        
        return $resources;
    }
    
    /**
     * Extract class name from file.
     */
    protected static function getClassFromFile(SplFileInfo $file): ?string
    {
        // Only process PHP files
        if ($file->getExtension() !== 'php') {
            return null;
        }
        
        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
        
        // Convert file path to namespace
        $namespace = str_replace(['/', '.php'], ['\\', ''], $relativePath);
        $namespace = ucfirst($namespace);
        
        // Handle different directory structures
        if (Str::startsWith($namespace, 'App\\')) {
            return $namespace;
        }
        
        return 'App\\' . $namespace;
    }
    
    /**
     * Check if a class is a valid resource class.
     */
    protected static function isValidResourceClass(string $className): bool
    {
        try {
            if (!class_exists($className)) {
                return false;
            }
            
            $reflection = new ReflectionClass($className);
            
            // Must extend Resource class
            if (!$reflection->isSubclassOf(Resource::class)) {
                return false;
            }
            
            // Must not be abstract
            if ($reflection->isAbstract()) {
                return false;
            }
            
            // Must have a model property defined
            $modelProperty = $reflection->getProperty('model');
            $modelProperty->setAccessible(true);
            $modelValue = $modelProperty->getValue();
            
            return !empty($modelValue);
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get resource classes by namespace pattern.
     */
    public static function findResourcesByPattern(string $pattern): array
    {
        $resources = static::findAllResources();
        
        return array_filter($resources, function ($resourceClass) use ($pattern) {
            return Str::is($pattern, $resourceClass);
        });
    }
    
    /**
     * Find resources by model class.
     */
    public static function findResourceByModel(string $modelClass): ?string
    {
        $resources = static::findAllResources();
        
        foreach ($resources as $resourceClass) {
            try {
                if ($resourceClass::getModel() === $modelClass) {
                    return $resourceClass;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return null;
    }
    
    /**
     * Get resource discovery statistics.
     */
    public static function getDiscoveryStats(): array
    {
        $allClasses = [];
        $validResources = [];
        $invalidClasses = [];
        
        foreach (static::$scanDirectories as $directory) {
            $fullPath = base_path($directory);
            
            if (!File::isDirectory($fullPath)) {
                continue;
            }
            
            $files = File::allFiles($fullPath);
            
            foreach ($files as $file) {
                $className = static::getClassFromFile($file);
                
                if (!$className) {
                    continue;
                }
                
                $allClasses[] = $className;
                
                if (static::isValidResourceClass($className)) {
                    $validResources[] = $className;
                } else {
                    $invalidClasses[] = $className;
                }
            }
        }
        
        return [
            'scan_directories' => static::$scanDirectories,
            'total_classes_found' => count($allClasses),
            'valid_resources' => count($validResources),
            'invalid_classes' => count($invalidClasses),
            'resource_classes' => $validResources,
            'invalid_class_names' => $invalidClasses,
        ];
    }
    
    /**
     * Add a custom scan directory.
     */
    public static function addScanDirectory(string $directory): void
    {
        if (!in_array($directory, static::$scanDirectories)) {
            static::$scanDirectories[] = $directory;
        }
    }
    
    /**
     * Set custom resource namespace.
     */
    public static function setResourceNamespace(string $namespace): void
    {
        static::$resourceNamespace = rtrim($namespace, '\\') . '\\';
    }
    
    /**
     * Check if a directory has valid resources.
     */
    public static function hasResourcesInDirectory(string $directory): bool
    {
        $fullPath = base_path($directory);
        
        if (!File::isDirectory($fullPath)) {
            return false;
        }
        
        return !empty(static::findResourcesInDirectory($fullPath));
    }
    
    /**
     * Validate discovered resources for common issues.
     */
    public static function validateDiscoveredResources(): array
    {
        $issues = [];
        $resources = static::findAllResources();
        
        foreach ($resources as $resourceClass) {
            try {
                // Check if model exists
                $modelClass = $resourceClass::getModel();
                if (!class_exists($modelClass)) {
                    $issues[] = [
                        'resource' => $resourceClass,
                        'issue' => 'model_not_found',
                        'message' => "Model [{$modelClass}] does not exist.",
                    ];
                }
                
                // Check for duplicate slugs
                $slug = $resourceClass::getSlug();
                $duplicate = ResourceManager::findBySlug($slug);
                if ($duplicate && $duplicate !== $resourceClass) {
                    $issues[] = [
                        'resource' => $resourceClass,
                        'issue' => 'duplicate_slug',
                        'message' => "Slug [{$slug}] conflicts with [{$duplicate}].",
                    ];
                }
                
            } catch (\Exception $e) {
                $issues[] = [
                    'resource' => $resourceClass,
                    'issue' => 'configuration_error',
                    'message' => $e->getMessage(),
                ];
            }
        }
        
        return $issues;
    }
    
    /**
     * Clear discovery cache and re-scan.
     */
    public static function refresh(): array
    {
        // Clear existing registrations
        ResourceManager::clearCache();
        
        // Re-discover all resources
        static::discover();
        
        return ResourceManager::getResources();
    }
}