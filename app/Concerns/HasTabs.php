<?php

namespace App\Concerns;

use App\UI\Components\Tab;
use App\UI\Components\TabSet;

trait HasTabs
{
    protected ?TabSet $tabSet = null;

    protected ?string $baseRoute = null;

    protected array $baseRouteParameters = [];

    /**
     * Initialize tabs - should be called in mount() or similar
     */
    protected function initializeTabs(): void
    {
        if (method_exists($this, 'configureTabs')) {
            $this->tabSet = $this->configureTabs();
        }
    }

    /**
     * Get the configured tab set
     */
    protected function getTabSet(): ?TabSet
    {
        if ($this->tabSet === null) {
            $this->initializeTabs();
        }

        return $this->tabSet;
    }

    /**
     * Get all tabs for navigation
     */
    public function getTabsForNavigation(?object $model = null): array
    {
        $tabSet = $this->getTabSet();

        if (! $tabSet) {
            return [];
        }

        // Use the model from the component if available and none provided
        if (! $model && property_exists($this, 'model')) {
            $model = $this->model;
        }

        return $tabSet->buildNavigation($model)->toArray();
    }

    /**
     * Get the currently active tab
     */
    public function getActiveTab(): ?Tab
    {
        return $this->getTabSet()?->getActiveTab();
    }

    /**
     * Navigate to a specific tab
     */
    public function navigateToTab(string $tabKey, ?object $model = null): void
    {
        $tabSet = $this->getTabSet();
        $tab = $tabSet?->getTab($tabKey);

        if (! $tab) {
            return;
        }

        // Use the model from the component if available and none provided
        if (! $model && property_exists($this, 'model')) {
            $model = $this->model;
        }

        // Build navigation to get the URL
        $navigation = $tabSet->buildNavigation($model);
        $tabNavigation = collect($navigation)->firstWhere('key', $tabKey);

        if ($tabNavigation && isset($tabNavigation['url'])) {
            $this->redirect($tabNavigation['url'], navigate: true);
        }
    }

    /**
     * Handle tab click events
     */
    public function handleTabClick(string $tabKey): void
    {
        $tabSet = $this->getTabSet();
        $tab = $tabSet?->getTab($tabKey);

        if ($tab && $tab->hasClickHandler()) {
            $result = $tab->executeClickHandler();

            // If the click handler returns false, don't navigate
            if ($result === false) {
                return;
            }
        }

        // Default behavior is to navigate to the tab
        $this->navigateToTab($tabKey);
    }

    /**
     * Get the current route name for tab detection
     */
    protected function getCurrentRoute(): string
    {
        return request()->route()?->getName() ?? '';
    }

    /**
     * Get current route parameters
     */
    protected function getCurrentRouteParameters(): array
    {
        return request()->route()?->parameters() ?? [];
    }

    /**
     * Check if we should redirect to the default tab
     */
    public function redirectToDefaultTabIfNeeded(): void
    {
        $tabSet = $this->getTabSet();

        if (! $tabSet) {
            return;
        }

        $currentRoute = $this->getCurrentRoute();
        $firstTab = $tabSet->getFirstTab();

        if (! $firstTab) {
            return;
        }

        // Check if we're on a base route that should redirect to the first tab
        if ($this->shouldRedirectToFirstTab($currentRoute)) {
            $this->navigateToTab($firstTab->getKey());
        }
    }

    /**
     * Determine if the current route should redirect to the first tab
     */
    protected function shouldRedirectToFirstTab(string $currentRoute): bool
    {
        // Override this method in your component for custom logic
        return false;
    }

    /**
     * Get tab count with optional filtering
     */
    public function getTabCount(): int
    {
        return $this->getTabSet()?->getTabs()->count() ?? 0;
    }

    /**
     * Check if a specific tab exists
     */
    public function hasTab(string $tabKey): bool
    {
        return $this->getTabSet()?->getTab($tabKey) !== null;
    }

    /**
     * Get tab badge value for a specific tab
     */
    public function getTabBadge(string $tabKey): string|int|null
    {
        $tab = $this->getTabSet()?->getTab($tabKey);

        return $tab?->getBadge();
    }

    /**
     * Update a tab's badge value (useful for dynamic updates)
     */
    public function updateTabBadge(string $tabKey, string|int|null $badge): void
    {
        $tab = $this->getTabSet()?->getTab($tabKey);

        if ($tab) {
            $tab->badge($badge);
        }
    }
}
