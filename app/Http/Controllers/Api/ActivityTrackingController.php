<?php

namespace App\Http\Controllers\Api;

use App\Facades\Activity;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 🎯 ACTIVITY TRACKING API CONTROLLER
 *
 * Handles all the gorgeous verbose activity tracking from the frontend.
 * Because Ben LOVES knowing what buttons get clicked! 🎉
 */
class ActivityTrackingController extends Controller
{
    /**
     * Track frontend activity with all the verbose detail Ben craves
     */
    public function track(Request $request)
    {
        try {
            $eventType = $request->input('event_type', 'unknown');
            $data = $request->input('data', []);
            $timestamp = $request->input('timestamp');

            $userName = auth()->user()?->name ?? 'Guest';
            $userAgent = $request->userAgent();
            $ipAddress = $request->ip();

            // Create gorgeous descriptive messages based on event type
            $description = match ($eventType) {
                'button_clicked' => $this->createButtonClickDescription($data, $userName),
                'form_submitted' => $this->createFormSubmissionDescription($data, $userName),
                'page_viewed' => $this->createPageViewDescription($data, $userName),
                'page_navigated' => $this->createPageNavigationDescription($data, $userName),
                'search_performed' => $this->createSearchDescription($data, $userName),
                default => "{$userName} performed {$eventType} action"
            };

            // Log with all the verbose detail
            Activity::log()
                ->by(auth()->id())
                ->customEvent("ui.{$eventType}")
                ->description($description)
                ->with([
                    'event_type' => $eventType,
                    'user_name' => $userName,
                    'user_agent' => $userAgent,
                    'ip_address' => $ipAddress,
                    'frontend_timestamp' => $timestamp,
                    'server_timestamp' => now()->toDateTimeString(),
                    'session_id' => session()->getId(),
                    'user_id' => auth()->id(),
                    ...$data, // Spread all the frontend data
                ])
                ->save();

            return response()->json([
                'success' => true,
                'message' => 'Activity tracked successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Activity tracking failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Activity tracking failed',
            ], 500);
        }
    }

    /**
     * Create gorgeous button click descriptions
     */
    protected function createButtonClickDescription(array $data, string $userName): string
    {
        $buttonText = $data['button_text'] ?? 'Unknown Button';
        $pageUrl = $data['page_url'] ?? 'Unknown Page';
        $pageSection = $data['page_section'] ?? null;

        $description = "{$userName} clicked '{$buttonText}' button on {$this->getPageName($pageUrl)}";

        if ($pageSection) {
            $description .= " in {$pageSection} section";
        }

        return $description;
    }

    /**
     * Create gorgeous form submission descriptions
     */
    protected function createFormSubmissionDescription(array $data, string $userName): string
    {
        $formName = $data['form_name'] ?? 'Unknown Form';
        $fieldCount = $data['field_count'] ?? 0;
        $method = $data['form_method'] ?? 'GET';

        return "{$userName} submitted '{$formName}' form with {$fieldCount} fields using {$method} method";
    }

    /**
     * Create gorgeous page view descriptions
     */
    protected function createPageViewDescription(array $data, string $userName): string
    {
        $pageUrl = $data['page_url'] ?? 'Unknown Page';
        $referrer = $data['referrer'] ?? null;
        $resolution = $data['screen_resolution'] ?? 'unknown resolution';

        $description = "{$userName} viewed {$this->getPageName($pageUrl)} at {$resolution}";

        if ($referrer) {
            $description .= " (referred from {$this->getPageName($referrer)})";
        }

        return $description;
    }

    /**
     * Create gorgeous page navigation descriptions
     */
    protected function createPageNavigationDescription(array $data, string $userName): string
    {
        $pageUrl = $data['page_url'] ?? 'Unknown Page';
        $navigationType = $data['navigation_type'] ?? 'unknown';

        return "{$userName} navigated to {$this->getPageName($pageUrl)} via {$navigationType}";
    }

    /**
     * Create gorgeous search descriptions
     */
    protected function createSearchDescription(array $data, string $userName): string
    {
        $query = $data['search_query'] ?? 'empty query';
        $queryLength = $data['query_length'] ?? 0;
        $searchField = $data['search_field'] ?? 'unknown field';

        return "{$userName} searched for '{$query}' ({$queryLength} characters) in {$searchField}";
    }

    /**
     * Extract a friendly page name from URL
     */
    protected function getPageName(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '/';

        // Convert common paths to friendly names
        $friendlyNames = [
            '/' => 'Homepage',
            '/dashboard' => 'Dashboard',
            '/products' => 'Products List',
            '/pricing' => 'Pricing Dashboard',
            '/logs' => 'Log Dashboard',
            '/users' => 'Users Management',
            '/settings' => 'Settings',
        ];

        foreach ($friendlyNames as $pattern => $name) {
            if (str_starts_with($path, $pattern)) {
                return $name;
            }
        }

        // Extract product/resource names from URLs
        if (preg_match('/\/products\/(\d+)/', $path, $matches)) {
            return "Product #{$matches[1]} Details";
        }

        if (preg_match('/\/users\/(\d+)/', $path, $matches)) {
            return "User #{$matches[1]} Profile";
        }

        // Fallback to cleaning up the path
        return ucwords(str_replace(['/', '-', '_'], [' ', ' ', ' '], trim($path, '/'))) ?: 'Unknown Page';
    }

    /**
     * Get activity summary for the current user (bonus endpoint!)
     */
    public function summary(Request $request)
    {
        $hours = $request->input('hours', 24);

        $activities = Activity::recent($hours)
            ->where('user_id', auth()->id())
            ->get();

        $summary = [
            'total_activities' => $activities->count(),
            'button_clicks' => $activities->where('event', 'ui.button_clicked')->count(),
            'form_submissions' => $activities->where('event', 'ui.form_submitted')->count(),
            'page_views' => $activities->where('event', 'ui.page_viewed')->count(),
            'searches' => $activities->where('event', 'ui.search_performed')->count(),
            'most_clicked_buttons' => $activities
                ->where('event', 'ui.button_clicked')
                ->groupBy('data.button_text')
                ->map->count()
                ->sortDesc()
                ->take(5),
            'most_visited_pages' => $activities
                ->whereIn('event', ['ui.page_viewed', 'ui.page_navigated'])
                ->groupBy('data.page_url')
                ->map->count()
                ->sortDesc()
                ->take(5),
        ];

        return response()->json($summary);
    }
}
