<?php

namespace App\UI\Components;

use Closure;
use Illuminate\Support\Collection;

class TabSet
{
    /** @var Collection<int, Tab> */
    protected Collection $tabs;

    protected ?string $baseRoute = null;

    /** @var Collection<string, mixed> */
    protected Collection $baseRouteParameters;

    protected ?Closure $activeTabResolver = null;

    /** @var Collection<string, mixed> */
    protected Collection $defaultRouteParameters;

    protected bool $wireNavigate = true;

    protected function __construct()
    {
        $this->tabs = collect();
        $this->baseRouteParameters = collect();
        $this->defaultRouteParameters = collect();
    }

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  array<mixed>|Collection<mixed>  $tabs
     */
    public function tabs(array|Collection $tabs): static
    {
        $this->tabs = collect();

        collect($tabs)->each(function ($tab) {
            if ($tab instanceof Tab) {
                $this->tabs->push($tab);
            } elseif (is_array($tab)) {
                // Convert array format to Tab instance for backwards compatibility
                $tabInstance = Tab::make($tab['key'])
                    ->label($tab['label'] ?? $tab['key'])
                    ->icon($tab['icon'] ?? null);

                if (isset($tab['route'])) {
                    $tabInstance->route($tab['route'], collect($tab['routeParameters'] ?? [])->toArray());
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

                $this->tabs->push($tabInstance);
            }
        });

        return $this;
    }

    public function tab(Tab $tab): static
    {
        $this->tabs->push($tab);

        return $this;
    }

    public function baseRoute(string $route, array|Collection $parameters = []): static
    {
        $this->baseRoute = $route;
        $this->baseRouteParameters = collect($parameters);

        return $this;
    }

    public function defaultRouteParameters(array|Collection $parameters): static
    {
        $this->defaultRouteParameters = collect($parameters);

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
     *
     * @return Collection<int, Tab>
     */
    public function getTabs(): Collection
    {
        return $this->tabs->filter(fn (Tab $tab) => ! $tab->isHidden());
    }

    /**
     * Get a specific tab by its key
     */
    public function getTab(string $key): ?Tab
    {
        return $this->tabs->first(fn (Tab $tab) => $tab->getKey() === $key);
    }

    /**
     * Build navigation data for the tabs
     */
    public function buildNavigation(?\Illuminate\Database\Eloquent\Model $model = null)
    {
        $currentRoute = request()->route()?->getName() ?? '';
        $currentParameters = collect(request()->route()?->parameters() ?? []);

        return $this->getTabs()
            ->map(function (Tab $tab) use ($currentRoute, $currentParameters, $model) {
                $tabData = collect($tab->toArray());

                if ($tabData->isEmpty()) {
                    return null; // Skip hidden tabs
                }

                // Merge default parameters with tab-specific parameters
                $routeParameters = $this->defaultRouteParameters
                    ->merge($this->baseRouteParameters)
                    ->merge(collect($tab->getRouteParameters()));

                // Add model to parameters if provided
                if ($model) {
                    $modelKey = $this->getModelParameterKey($model);
                    $routeParameters->put($modelKey, $model->getKey());
                }

                // Build route if base route is set and tab doesn't have its own route
                if ($this->baseRoute && ! $tab->getRoute()) {
                    $tabRoute = "{$this->baseRoute}.{$tab->getKey()}";
                    $tabData->put('route', $tabRoute);
                    $tabData->put('url', route($tabRoute, $routeParameters->toArray()));
                } elseif ($tab->getRoute()) {
                    // Use tab's own route with merged parameters
                    $mergedParameters = $routeParameters->merge(collect($tab->getRouteParameters()));
                    $tabData->put('url', route($tab->getRoute(), $mergedParameters->toArray()));
                }

                // Determine if tab is active
                $tabData->put('active', $this->isTabActive($tab, $currentRoute, $currentParameters->toArray()));

                // Apply TabSet wire:navigate setting if tab doesn't override
                if (! $tabData->has('wireNavigate')) {
                    $tabData->put('wireNavigate', $this->wireNavigate);
                }

                return $tabData->toArray();
            })
            ->filter() // Remove null values (hidden tabs)
            ->values(); // Reset keys
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
            $baseRoutes = collect([
                $this->baseRoute,
                "{$this->baseRoute}.view", // Common Laravel pattern
                "{$this->baseRoute}.show", // Another common pattern
                str_replace('.product', '', $this->baseRoute).'.view', // Handle products.view case
            ]);

            if ($baseRoutes->contains($currentRoute) && $tab === $this->getFirstTab()) {
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
        return $this->buildNavigation($model)->toArray();
    }

    /**
     * Convert to Collection for advanced manipulation
     */
    public function toCollection(?object $model = null): Collection
    {
        return $this->buildNavigation($model);
    }
}
