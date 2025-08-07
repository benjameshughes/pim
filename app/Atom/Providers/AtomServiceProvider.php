<?php

namespace App\Atom\Providers;

use App\Atom\Navigation\NavigationManager;
use App\Atom\Resources\ResourceManager;
use App\Atom\Resources\ResourceRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Atom Service Provider
 * 
 * Laravel service provider that bootstraps the Atom framework.
 * Handles auto-discovery, route registration, and Livewire component binding.
 * Following Laravel best practices for service provider architecture.
 */
class AtomServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the ResourceManager as singleton
        $this->app->singleton(ResourceManager::class, function ($app) {
            return new ResourceManager();
        });
        
        // Register the ResourceRegistry as singleton
        $this->app->singleton(ResourceRegistry::class, function ($app) {
            return new ResourceRegistry();
        });
        
        // Register the NavigationManager as singleton
        $this->app->singleton(NavigationManager::class, function ($app) {
            return new NavigationManager();
        });
        
        // Bind interfaces if needed
        // $this->app->bind(ResourceManagerInterface::class, ResourceManager::class);
        
        // Register configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../../config/resources.php',
            'resources'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Auto-discover resources if enabled (but silently)
        if (config('resources.auto_discovery', true)) {
            $this->discoverResourcesSilently();
        }
        
        // Discover navigation (but silently)
        $this->discoverNavigationSilently();
        
        // Register resource routes
        $this->registerResourceRoutes();
        
        // Register navigation routes
        $this->registerNavigationRoutes();
        
        // Register Livewire components
        $this->registerLivewireComponents();
        
        // Register Blade directives
        $this->registerBladeDirectives();
        
        // Publish configuration if running in console
        $this->publishConfiguration();
        
        // Register commands
        $this->registerCommands();
    }

    /**
     * Discover and register all resources.
     */
    protected function discoverResources(): void
    {
        try {
            ResourceRegistry::discover();
            
            if (config('app.debug')) {
                // Get basic stats without navigation which requires routes
                $totalResources = count(ResourceManager::getResources());
                $this->app['log']->info(
                    'Resource discovery completed',
                    ['total_resources' => $totalResources]
                );
            }
        } catch (\Exception $e) {
            // Log error but don't break the application
            $this->app['log']->error(
                'Resource discovery failed: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    /**
     * Discover and register all resources silently (no logging).
     */
    protected function discoverResourcesSilently(): void
    {
        try {
            ResourceRegistry::discover();
        } catch (\Exception $e) {
            // Fail silently - only log critical errors
            if (config('app.debug') && $this->app->runningInConsole()) {
                $this->app['log']->error(
                    'Resource discovery failed: ' . $e->getMessage(),
                    ['exception' => $e]
                );
            }
        }
    }

    /**
     * Register routes for all discovered resources.
     */
    protected function registerResourceRoutes(): void
    {
        if (!$this->app->routesAreCached()) {
            $this->registerWebResourceRoutes();
            $this->registerApiResourceRoutes();
        }
    }

    /**
     * Discover and register navigation.
     */
    protected function discoverNavigation(): void
    {
        try {
            NavigationManager::discover();
            
            if (config('app.debug')) {
                $stats = NavigationManager::getStatistics();
                $this->app['log']->info(
                    'Navigation discovery completed',
                    $stats
                );
            }
        } catch (\Exception $e) {
            // Log error but don't break the application
            $this->app['log']->error(
                'Navigation discovery failed: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    /**
     * Discover and register navigation silently (no logging).
     */
    protected function discoverNavigationSilently(): void
    {
        try {
            NavigationManager::discover();
        } catch (\Exception $e) {
            // Fail silently - only log critical errors
            if (config('app.debug') && $this->app->runningInConsole()) {
                $this->app['log']->error(
                    'Navigation discovery failed: ' . $e->getMessage(),
                    ['exception' => $e]
                );
            }
        }
    }
    
    /**
     * Register navigation routes.
     */
    protected function registerNavigationRoutes(): void
    {
        if (!$this->app->routesAreCached()) {
            $this->registerNestedResourceRoutes();
        }
    }
    
    /**
     * Register nested/relationship routes.
     */
    protected function registerNestedResourceRoutes(): void
    {
        Route::middleware(['web'])
            ->prefix(config('resources.web.prefix', ''))
            ->name('resources.')
            ->group(function () {
                // Get all resources and check for relationship routes
                $resources = ResourceManager::getResources();
                
                foreach ($resources as $resourceClass) {
                    // Check if resource has relationship navigation
                    if (method_exists($resourceClass, 'getRelationshipRoutes')) {
                        $routes = $resourceClass::getRelationshipRoutes();
                        
                        foreach ($routes as $route) {
                            Route::get($route['uri'], function ($record, $relation = null) use ($route) {
                                $resourceClass = $route['resource'];
                                $parentRecord = $resourceClass::resolveRecord($record);
                                
                                if (!$parentRecord) {
                                    abort(404);
                                }
                                
                                // Render relationship page
                                return app('livewire')->mount(
                                    \App\Adapters\LivewireResourceAdapter::class,
                                    [
                                        'resourceClass' => $resourceClass,
                                        'record' => $parentRecord,
                                        'page' => 'relationship',
                                        'relationship' => $route['relationship'],
                                        'relatedResource' => $route['related_resource'],
                                    ]
                                );
                            })->name($route['name']);
                        }
                    }
                }
            });
    }
    
    /**
     * Register web routes for resources.
     */
    protected function registerWebResourceRoutes(): void
    {
        Route::middleware(['web'])
            ->prefix(config('resources.web.prefix', ''))
            ->group(function () {
                $routes = ResourceManager::generateRoutes();
                
                foreach ($routes as $route) {
                    $this->registerSingleResourceRoute($route);
                }
            });
    }

    /**
     * Register API routes for resources.
     */
    protected function registerApiResourceRoutes(): void
    {
        if (!config('resources.api.enabled', false)) {
            return;
        }

        Route::middleware(['api'])
            ->prefix(config('resources.api.prefix', 'api'))
            ->name('api.resources.')
            ->group(function () {
                // TODO: Implement API routes
                // This would use the ApiResourceAdapter
            });
    }

    /**
     * Register a single resource route.
     */
    protected function registerSingleResourceRoute(array $route): void
    {
        // Register the route with proper Laravel syntax and pass parameters to LivewireResourceAdapter
        Route::get($route['uri'], function () use ($route) {
            return app('livewire')->mount(
                $this->getRouteAction($route),
                [
                    'resource' => $route['resource'],
                    'page' => $route['page'],
                    'record' => request()->route('record'),
                ]
            );
        })->name($route['name']);
    }

    /**
     * Get the action for a resource route.
     */
    protected function getRouteAction(array $route): string
    {
        // Always use the LivewireResourceAdapter - this is how your framework works
        return \App\Atom\Adapters\LivewireResourceAdapter::class;
    }

    /**
     * Register Livewire components.
     */
    protected function registerLivewireComponents(): void
    {
        // Register the main adapter component
        Livewire::component(
            'adapters.livewire-resource-adapter',
            \App\Adapters\LivewireResourceAdapter::class
        );
        
        // Register any additional components if needed
        // Livewire::component('resources.create-record', CreateRecordComponent::class);
        // Livewire::component('resources.edit-record', EditRecordComponent::class);
    }

    /**
     * Register custom Blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        // @resource directive for embedding resources in Blade
        \Blade::directive('resource', function ($expression) {
            return "<?php echo app('App\\Resources\\ResourceManager')->renderResource({$expression}); ?>";
        });
        
        // @resourceTable directive for embedding just the table
        \Blade::directive('resourceTable', function ($expression) {
            return "<?php echo app('App\\Resources\\ResourceManager')->renderTable({$expression}); ?>";
        });
        
        // @resourceNavigation directive for resource navigation
        \Blade::directive('resourceNavigation', function ($expression) {
            return "<?php echo app('App\\Resources\\ResourceManager')->renderNavigation({$expression}); ?>";
        });
    }

    /**
     * Publish configuration files.
     */
    protected function publishConfiguration(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../../config/resources.php' => config_path('resources.php'),
            ], 'resources-config');
        }
    }

    /**
     * Register Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Atom\Console\Commands\MakeResourceCommand::class,
                // TODO: Add other commands when they're created
                // \App\Console\Commands\ListResourcesCommand::class,
                // \App\Console\Commands\ResourceStatsCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            ResourceManager::class,
            ResourceRegistry::class,
        ];
    }

    /**
     * Register event listeners for resource system.
     */
    protected function registerEventListeners(): void
    {
        // Listen for model events to clear resource cache
        $this->app['events']->listen([
            'eloquent.created:*',
            'eloquent.updated:*', 
            'eloquent.deleted:*',
        ], function () {
            ResourceManager::clearCache();
        });
    }

    /**
     * Register middleware for resource system.
     */
    protected function registerMiddleware(): void
    {
        // Register resource-specific middleware
        $router = $this->app['router'];
        
        // $router->aliasMiddleware('resource.auth', ResourceAuthMiddleware::class);
        // $router->aliasMiddleware('resource.cache', ResourceCacheMiddleware::class);
    }

    /**
     * Register view composers for resource system.
     */
    protected function registerViewComposers(): void
    {
        // Share resource navigation with all views
        view()->composer('*', function ($view) {
            if (config('resources.navigation.share_globally', true)) {
                $view->with([
                    'resourceNavigation' => NavigationManager::getGroupedItems(),
                    'navigationBreadcrumbs' => NavigationManager::generateBreadcrumbs(),
                    'resourceStats' => ResourceManager::getStatistics(),
                ]);
            }
        });
    }

    /**
     * Register validation rules for resource system.
     */
    protected function registerValidationRules(): void
    {
        // Custom validation rules for resources
        \Validator::extend('resource_exists', function ($attribute, $value, $parameters, $validator) {
            return ResourceManager::hasResource($value);
        });
        
        \Validator::extend('resource_slug_unique', function ($attribute, $value, $parameters, $validator) {
            return ResourceManager::findBySlug($value) === null;
        });
    }

    /**
     * Handle deferred service provider booting.
     */
    protected function bootDeferred(): void
    {
        // Register event listeners
        $this->registerEventListeners();
        
        // Register middleware
        $this->registerMiddleware();
        
        // Register view composers
        $this->registerViewComposers();
        
        // Register validation rules
        $this->registerValidationRules();
    }
}