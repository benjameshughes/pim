<?php

namespace App\Traits;

trait HasRouteTabs
{
    /**
     * Get the current active tab from the route
     */
    public function getCurrentTab(): string
    {
        $route = request()->route();

        return $route ? ($route->getName() ?? '') : '';
    }

    /**
     * Get the base route name for tab navigation
     */
    protected function getBaseRoute(): string
    {
        return $this->baseRoute ?? '';
    }

    /**
     * Get all tab configurations
     */
    protected function getTabConfig(): array
    {
        return $this->tabConfig ?? [];
    }

    /**
     * Get tabs for navigation component
     */
    public function getTabsForNavigation(): array
    {
        $config = $this->getTabConfig();
        $baseRoute = $this->getBaseRoute();
        $currentRoute = $this->getCurrentTab();

        $tabs = [];

        foreach ($config['tabs'] as $tab) {
            $routeName = "{$baseRoute}.{$tab['key']}";

            $tabs[] = [
                'key' => $tab['key'],
                'label' => $tab['label'],
                'icon' => $tab['icon'] ?? 'document',
                'route' => $routeName,
                'active' => $currentRoute === $routeName,
                'url' => route($routeName, request()->query()),
            ];
        }

        return $tabs;
    }

    /**
     * Navigate to a specific tab while preserving query parameters
     */
    public function navigateToTab(string $tabKey): void
    {
        $baseRoute = $this->getBaseRoute();
        $routeName = "{$baseRoute}.{$tabKey}";

        $this->redirect(route($routeName, request()->query()), navigate: true);
    }

    /**
     * Get current query parameters for URL building
     */
    protected function getCurrentQueryParams(): array
    {
        $params = request()->query();

        // Remove Laravel-specific parameters that shouldn't be preserved
        unset($params['_token'], $params['_method']);

        return $params;
    }

    /**
     * Setup common query string tracking for tabs
     */
    protected function initializeQueryStringTracking(): void
    {
        $this->queryString = array_merge($this->queryString ?? [], [
            'search' => ['except' => '', 'as' => 'q'],
            'page' => ['except' => 1],
        ]);
    }

    /**
     * Reset pagination when search changes
     */
    public function updatedSearch()
    {
        $this->resetPage();
    }

    /**
     * Check if we should redirect to default tab
     */
    public function redirectToDefaultTabIfNeeded(): void
    {
        $config = $this->getTabConfig();

        if (empty($config['tabs'])) {
            return;
        }

        // If we're on the index route, redirect to first tab
        $currentRoute = request()->route()->getName();
        $baseRoute = $this->getBaseRoute();

        if ($currentRoute === $baseRoute) {
            $defaultTab = $config['tabs'][0]['key'];
            $this->navigateToTab($defaultTab);
        }
    }
}
