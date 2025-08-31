<?php

namespace App\Livewire\Traits;

use App\Facades\Activity;

trait TracksUserInteractions
{
    /**
     * Track button clicks with gorgeous verbose logging
     */
    public function trackButtonClick(string $buttonName, array $context = [])
    {
        $userName = auth()->user()?->name ?? 'Guest';
        $route = request()->route()?->getName() ?? 'unknown';
        $component = static::class;
        $componentName = class_basename($component);

        $description = "{$userName} clicked '{$buttonName}' button in {$componentName}";

        Activity::log()
            ->by(auth()->id())
            ->customEvent('ui.button_clicked')
            ->description($description)
            ->with([
                'button_name' => $buttonName,
                'component_class' => $component,
                'component_name' => $componentName,
                'route' => $route,
                'user_name' => $userName,
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
                'timestamp' => now()->toDateTimeString(),
                'context' => $context,
            ])
            ->save();
    }

    /**
     * Track form submissions
     */
    public function trackFormSubmission(string $formName, array $data = [])
    {
        $userName = auth()->user()?->name ?? 'Guest';
        $componentName = class_basename(static::class);

        $description = "{$userName} submitted '{$formName}' form in {$componentName}";

        Activity::log()
            ->by(auth()->id())
            ->customEvent('ui.form_submitted')
            ->description($description)
            ->with([
                'form_name' => $formName,
                'component_class' => static::class,
                'component_name' => $componentName,
                'route' => request()->route()?->getName(),
                'user_name' => $userName,
                'form_data_keys' => array_keys($data),
                'data_count' => count($data),
                'timestamp' => now()->toDateTimeString(),
            ])
            ->save();
    }

    /**
     * Track tab changes
     */
    public function trackTabChange(string $fromTab, string $toTab)
    {
        $userName = auth()->user()?->name ?? 'Guest';
        $componentName = class_basename(static::class);

        $description = "{$userName} switched from '{$fromTab}' tab to '{$toTab}' tab in {$componentName}";

        Activity::log()
            ->by(auth()->id())
            ->customEvent('ui.tab_changed')
            ->description($description)
            ->with([
                'from_tab' => $fromTab,
                'to_tab' => $toTab,
                'component_class' => static::class,
                'component_name' => $componentName,
                'user_name' => $userName,
                'timestamp' => now()->toDateTimeString(),
            ])
            ->save();
    }

    /**
     * Track search queries
     */
    public function trackSearch(string $query, string $searchType = 'general', array $filters = [])
    {
        $userName = auth()->user()?->name ?? 'Guest';
        $componentName = class_basename(static::class);

        $description = "{$userName} searched for '{$query}' ({$searchType}) in {$componentName}";

        Activity::log()
            ->by(auth()->id())
            ->customEvent('ui.search_performed')
            ->description($description)
            ->with([
                'search_query' => $query,
                'search_type' => $searchType,
                'filters_applied' => $filters,
                'component_class' => static::class,
                'component_name' => $componentName,
                'user_name' => $userName,
                'query_length' => strlen($query),
                'timestamp' => now()->toDateTimeString(),
            ])
            ->save();
    }
}
