<?php

namespace App\UI\Components;

use Closure;
use Illuminate\Support\Collection;

class TabSet
{
    protected array $tabs = [];
    protected ?string $baseRoute = null;
    protected array $baseRouteParameters = [];
    protected ?Closure $activeTabResolver = null;
    protected array $defaultRouteParameters = [];
    protected bool $wireNavigate = true;

    protected function __construct()
    {
        //
    }

    public static function make(): static
    {
        return new static();
    }

    public function tabs(array $tabs): static
    {
        $this->tabs = [];
        
        foreach ($tabs as $tab) {
            if ($tab instanceof Tab) {
                $this->tabs[] = $tab;
            } elseif (is_array($tab)) {
                // Convert array format to Tab instance for backwards compatibility
                $tabInstance = Tab::make($tab['key'])
                    ->label($tab['label'] ?? $tab['key'])
                    ->icon($tab['icon'] ?? null);
                
                if (isset($tab['route'])) {
                    $tabInstance->route($tab['route'], $tab['routeParameters'] ?? []);
                }
                
                if (isset($tab['url'])) {
                    $tabInstance->url($tab['url']);
                }
                
                if (isset($tab['badge'])) {
                    $tabInstance->badge($tab['badge']);
                }
                
                if (isset($tab['hidden'])) {
                    $tabInstance->hidden($tab['hidden']);
                }
                
                $this->tabs[] = $tabInstance;
            }
        }

        return $this;
    }

    public function tab(Tab $tab): static
    {
        $this->tabs[] = $tab;
        return $this;
    }

    public function baseRoute(string $route, array $parameters = []): static
    {
        $this->baseRoute = $route;
        $this->baseRouteParameters = $parameters;
        return $this;
    }

    public function defaultRouteParameters(array $parameters): static
    {
        $this->defaultRouteParameters = $parameters;
        return $this;
    }

    public function activeTabResolver(Closure $resolver): static
    {
        $this->activeTabResolver = $resolver;
        return $this;
    }

    public function wireNavigate(bool $enabled = true): static
    {
        $this->wireNavigate = $enabled;
        return $this;
    }

    /**
     * Get all tabs with their computed properties
     */
    public function getTabs(): Collection
    {
        return collect($this->tabs)->filter(fn(Tab $tab) => !$tab->isHidden());
    }

    /**
     * Get a specific tab by its key
     */
    public function getTab(string $key): ?Tab
    {
        return collect($this->tabs)->first(fn(Tab $tab) => $tab->getKey() === $key);
    }

    /**
     * Build navigation data for the tabs
     */
    public function buildNavigation(?object $model = null): array
    {
        $currentRoute = request()->route()?->getName() ?? '';
        $currentParameters = request()->route()?->parameters() ?? [];
        
        $tabs = [];
        
        foreach ($this->getTabs() as $tab) {
            $tabData = $tab->toArray();
            
            if (empty($tabData)) {
                continue; // Skip hidden tabs
            }

            // Merge default parameters with tab-specific parameters
            $routeParameters = array_merge(
                $this->defaultRouteParameters,
                $this->baseRouteParameters,
                $tab->getRouteParameters()
            );

            // Add model to parameters if provided
            if ($model) {
                $modelKey = $this->getModelParameterKey($model);
                $routeParameters[$modelKey] = $model;
            }

            // Build route if base route is set and tab doesn't have its own route
            if ($this->baseRoute && !$tab->getRoute()) {
                $tabRoute = "{$this->baseRoute}.{$tab->getKey()}";
                $tabData['route'] = $tabRoute;
                $tabData['url'] = route($tabRoute, $routeParameters);
            } elseif ($tab->getRoute()) {
                // Use tab's own route with merged parameters
                $tabData['url'] = route($tab->getRoute(), array_merge($routeParameters, $tab->getRouteParameters()));
            }

            // Determine if tab is active
            $tabData['active'] = $this->isTabActive($tab, $currentRoute, $currentParameters);

            // Apply TabSet wire:navigate setting if tab doesn't override
            if (!isset($tabData['wireNavigate'])) {
                $tabData['wireNavigate'] = $this->wireNavigate;
            }

            $tabs[] = $tabData;
        }

        return $tabs;
    }

    /**
     * Determine if a tab is currently active
     */
    protected function isTabActive(Tab $tab, string $currentRoute, array $currentParameters): bool
    {
        if ($this->activeTabResolver) {
            return ($this->activeTabResolver)($tab, $currentRoute, $currentParameters);
        }

        // If tab has its own route, use that
        if ($tab->getRoute()) {
            return $tab->isActive($currentRoute, $currentParameters);
        }

        // If we have a base route, check against constructed route
        if ($this->baseRoute) {
            $expectedRoute = "{$this->baseRoute}.{$tab->getKey()}";
            
            // Check exact route match
            if ($currentRoute === $expectedRoute) {
                return true;
            }
            
            // Check if we're on a base route that should default to the first tab
            $baseRoutes = [
                $this->baseRoute,
                "{$this->baseRoute}.view", // Common Laravel pattern
                "{$this->baseRoute}.show", // Another common pattern
                str_replace('.product', '', $this->baseRoute) . '.view' // Handle products.view case
            ];
            
            if (in_array($currentRoute, $baseRoutes) && $tab === $this->getFirstTab()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the parameter key for a model based on its class
     */
    protected function getModelParameterKey(object $model): string
    {
        $className = class_basename($model);
        return strtolower($className);
    }

    /**
     * Get the first visible tab
     */
    public function getFirstTab(): ?Tab
    {
        return $this->getTabs()->first();
    }

    /**
     * Get the currently active tab
     */
    public function getActiveTab(): ?Tab
    {
        $currentRoute = request()->route()?->getName() ?? '';
        $currentParameters = request()->route()?->parameters() ?? [];

        return $this->getTabs()->first(function (Tab $tab) use ($currentRoute, $currentParameters) {
            return $this->isTabActive($tab, $currentRoute, $currentParameters);
        });
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(?object $model = null): array
    {
        return $this->buildNavigation($model);
    }
}