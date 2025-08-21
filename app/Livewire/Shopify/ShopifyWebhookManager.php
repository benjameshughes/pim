<?php

namespace App\Livewire\Shopify;

use App\Services\Shopify\API\ShopifyApiClient;
use App\Services\Shopify\API\WebhooksApi;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ShopifyWebhookManager extends Component
{
    public string $shopDomain = '';

    public array $recentEvents = [];

    public array $webhookStats = [];

    public array $connectionStatus = [];

    public bool $isConnected = false;

    public bool $isLoading = false;

    public string $callbackUrl = '';

    public array $subscriptions = [];

    public array $missingTopics = [];

    public function mount(?string $shopDomain = null): void
    {
        $this->shopDomain = $shopDomain ?? config('services.shopify.default_shop_domain', '');
        $this->callbackUrl = url('/webhooks/shopify');

        if ($this->shopDomain) {
            $this->loadWebhookData();
        }
    }

    /**
     * 📊 LOAD WEBHOOK DATA
     *
     * Load webhook statistics and connection status
     */
    public function loadWebhookData(): void
    {
        $this->isLoading = true;

        try {
            $client = ShopifyApiClient::for($this->shopDomain);

            // Test connection
            $this->connectionStatus = $client->testAllConnections();
            $this->isConnected = $this->connectionStatus['overall_success'] ?? false;

            // Get webhook statistics and subscriptions
            $webhookStats = $client->webhooks()->getWebhookStatistics();
            if ($webhookStats['success']) {
                $this->webhookStats = $webhookStats['statistics'];
                $this->missingTopics = $webhookStats['missing_topics'] ?? [];
            }

            // Get current subscriptions
            $subscriptionsResult = $client->webhooks()->getSubscriptions();
            if ($subscriptionsResult['success']) {
                $this->subscriptions = $subscriptionsResult['subscriptions'];
            }

            $this->dispatch('webhook-data-loaded', [
                'connected' => $this->isConnected,
                'stats' => $this->webhookStats,
                'subscriptions' => count($this->subscriptions),
            ]);

        } catch (\Exception $e) {
            $this->dispatch('error', [
                'message' => 'Failed to load webhook data: '.$e->getMessage(),
            ]);
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * 🚀 SETUP WEBHOOKS
     *
     * Set up complete webhook system for the shop
     */
    public function setupWebhooks(): void
    {
        $this->isLoading = true;

        try {
            $client = ShopifyApiClient::for($this->shopDomain);
            $result = $client->webhooks()->setupCompleteWebhookSystem($this->callbackUrl);

            if ($result['success']) {
                $totalCreated = collect($result['setup_results'])
                    ->sum(fn ($group) => $group['summary']['subscriptions_created'] ?? 0);

                $this->dispatch('success', [
                    'message' => "Webhooks set up successfully! {$totalCreated} subscriptions created across ".
                                count($result['setup_results']).' categories.',
                ]);

                $this->loadWebhookData(); // Refresh data
            } else {
                $errors = collect($result['errors'])->pluck('error')->implode(', ');
                $this->dispatch('error', [
                    'message' => 'Failed to set up webhooks: '.$errors,
                ]);
            }

        } catch (\Exception $e) {
            $this->dispatch('error', [
                'message' => 'Error setting up webhooks: '.$e->getMessage(),
            ]);
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * 🧹 CLEANUP WEBHOOKS
     *
     * Remove all webhook subscriptions
     */
    public function cleanupWebhooks(): void
    {
        $this->isLoading = true;

        try {
            $client = ShopifyApiClient::for($this->shopDomain);
            $result = $client->webhooks()->cleanupAllWebhooks();

            if ($result['success']) {
                $this->dispatch('success', [
                    'message' => "Webhooks cleaned up successfully! {$result['deleted_count']} subscriptions removed.",
                ]);

                $this->loadWebhookData(); // Refresh data
            } else {
                $errors = collect($result['errors'])->pluck('error')->implode(', ');
                $this->dispatch('error', [
                    'message' => 'Failed to cleanup webhooks: '.$errors,
                ]);
            }

        } catch (\Exception $e) {
            $this->dispatch('error', [
                'message' => 'Error cleaning up webhooks: '.$e->getMessage(),
            ]);
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * ➕ SETUP MISSING TOPICS
     *
     * Set up only the missing recommended topics
     */
    public function setupMissingTopics(): void
    {
        if (empty($this->missingTopics)) {
            $this->dispatch('info', ['message' => 'No missing topics to set up']);

            return;
        }

        $this->isLoading = true;

        try {
            $client = ShopifyApiClient::for($this->shopDomain);
            $result = $client->webhooks()->createSubscription(
                $this->missingTopics,
                $this->callbackUrl.'/other'
            );

            if ($result['success']) {
                $this->dispatch('success', [
                    'message' => "Missing topics set up successfully! {$result['summary']['subscriptions_created']} new subscriptions created.",
                ]);

                $this->loadWebhookData(); // Refresh data
            } else {
                $errors = collect($result['errors'])->pluck('error')->implode(', ');
                $this->dispatch('error', [
                    'message' => 'Failed to set up missing topics: '.$errors,
                ]);
            }

        } catch (\Exception $e) {
            $this->dispatch('error', [
                'message' => 'Error setting up missing topics: '.$e->getMessage(),
            ]);
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * 🔄 REFRESH DATA
     *
     * Refresh all webhook data
     */
    public function refreshData(): void
    {
        $this->loadWebhookData();
        $this->dispatch('success', ['message' => 'Data refreshed successfully']);
    }

    /**
     * 📡 LISTEN FOR WEBHOOK EVENTS
     *
     * Real-time webhook event listener
     */
    #[On('webhook-received')]
    public function onWebhookReceived(array $eventData): void
    {
        // Add to recent events (keep last 20)
        array_unshift($this->recentEvents, [
            'id' => uniqid(),
            'topic' => $eventData['topic'],
            'shop_domain' => $eventData['shop_domain'],
            'summary' => $eventData['summary'],
            'timestamp' => $eventData['timestamp'],
            'result' => $eventData['result'],
        ]);

        $this->recentEvents = array_slice($this->recentEvents, 0, 20);

        // Update statistics
        $this->updateEventStats($eventData['topic']);

        // Show toast notification for important events
        $this->showWebhookToast($eventData);

        // Trigger UI update
        $this->dispatch('webhook-event-received', $eventData);
    }

    /**
     * 📊 UPDATE EVENT STATS
     *
     * Update webhook event statistics
     */
    protected function updateEventStats(string $topic): void
    {
        if (! isset($this->webhookStats['topics'])) {
            $this->webhookStats['topics'] = [];
        }

        $this->webhookStats['topics'][$topic] = ($this->webhookStats['topics'][$topic] ?? 0) + 1;
        $this->webhookStats['total_events'] = ($this->webhookStats['total_events'] ?? 0) + 1;
    }

    /**
     * 🍞 SHOW WEBHOOK TOAST
     *
     * Show toast notification for webhook events
     */
    protected function showWebhookToast(array $eventData): void
    {
        $importantTopics = [
            'products/create',
            'products/update',
            'products/delete',
            'orders/create',
            'orders/paid',
            'app/uninstalled',
        ];

        if (in_array($eventData['topic'], $importantTopics)) {
            $type = match ($eventData['topic']) {
                'products/delete', 'app/uninstalled' => 'warning',
                'orders/create', 'orders/paid' => 'success',
                default => 'info',
            };

            $this->dispatch('show-toast', [
                'type' => $type,
                'message' => $eventData['summary'],
                'duration' => 5000,
            ]);
        }
    }

    /**
     * 🔍 GET RECENT EVENTS
     *
     * Get recent webhook events for display
     */
    public function getRecentEventsProperty(): Collection
    {
        return collect($this->recentEvents);
    }

    /**
     * 📊 GET WEBHOOK STATISTICS
     *
     * Get formatted webhook statistics
     */
    public function getWebhookStatisticsProperty(): array
    {
        if (empty($this->webhookStats)) {
            return [
                'total_subscriptions' => 0,
                'total_events' => 0,
                'top_topics' => [],
                'recent_activity' => 'No activity',
                'coverage_percentage' => 0,
            ];
        }

        $topTopics = collect($this->webhookStats['topics'] ?? [])
            ->sortDesc()
            ->take(5)
            ->toArray();

        $recommendedTopics = WebhooksApi::getAllRecommendedTopics();
        $configuredTopics = array_keys($this->webhookStats['topics'] ?? []);
        $coveragePercentage = count($recommendedTopics) > 0
            ? round((count($configuredTopics) / count($recommendedTopics)) * 100, 1)
            : 0;

        return [
            'total_subscriptions' => $this->webhookStats['total_subscriptions'] ?? 0,
            'total_events' => $this->webhookStats['total_events'] ?? 0,
            'top_topics' => $topTopics,
            'recent_activity' => empty($this->recentEvents) ? 'No recent activity' : 'Active',
            'coverage_percentage' => $coveragePercentage,
            'missing_count' => count($this->missingTopics),
        ];
    }

    /**
     * 🎨 GET EVENT TYPE COLOR
     *
     * Get color class for event type
     */
    public function getEventTypeColor(string $topic): string
    {
        return match (true) {
            str_starts_with($topic, 'products/') => 'text-blue-600 bg-blue-50 border-blue-200',
            str_starts_with($topic, 'orders/') => 'text-green-600 bg-green-50 border-green-200',
            str_starts_with($topic, 'inventory_') => 'text-yellow-600 bg-yellow-50 border-yellow-200',
            str_starts_with($topic, 'customers/') => 'text-purple-600 bg-purple-50 border-purple-200',
            $topic === 'app/uninstalled' => 'text-red-600 bg-red-50 border-red-200',
            default => 'text-gray-600 bg-gray-50 border-gray-200',
        };
    }

    /**
     * 📱 GET EVENT ICON
     *
     * Get icon for event type
     */
    public function getEventIcon(string $topic): string
    {
        return match (true) {
            str_starts_with($topic, 'products/') => 'package',
            str_starts_with($topic, 'orders/') => 'shopping-cart',
            str_starts_with($topic, 'inventory_') => 'archive',
            str_starts_with($topic, 'customers/') => 'users',
            $topic === 'app/uninstalled' => 'alert-triangle',
            default => 'activity',
        };
    }

    /**
     * ⏰ FORMAT TIMESTAMP
     *
     * Format timestamp for display
     */
    public function formatTimestamp(string $timestamp): string
    {
        try {
            $date = new \DateTime($timestamp);
            $now = new \DateTime;
            $diff = $now->getTimestamp() - $date->getTimestamp();

            if ($diff < 60) {
                return 'Just now';
            } elseif ($diff < 3600) {
                return floor($diff / 60).'m ago';
            } elseif ($diff < 86400) {
                return floor($diff / 3600).'h ago';
            } else {
                return $date->format('M j, Y');
            }
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * 🎯 CLEAR RECENT EVENTS
     *
     * Clear the recent events list
     */
    public function clearRecentEvents(): void
    {
        $this->recentEvents = [];
        $this->dispatch('success', ['message' => 'Recent events cleared']);
    }

    /**
     * 📋 GET RECOMMENDED TOPICS
     *
     * Get list of recommended webhook topics
     */
    public function getRecommendedTopicsProperty(): array
    {
        return WebhooksApi::getAllRecommendedTopics();
    }

    /**
     * 📊 GET SUBSCRIPTION BREAKDOWN
     *
     * Get breakdown of subscriptions by category
     */
    public function getSubscriptionBreakdownProperty(): array
    {
        $productTopics = WebhooksApi::getProductWebhookTopics();
        $orderTopics = WebhooksApi::getOrderWebhookTopics();

        $breakdown = [
            'products' => 0,
            'orders' => 0,
            'other' => 0,
        ];

        foreach ($this->subscriptions as $subscription) {
            $callbackPath = parse_url($subscription['callbackUrl'], PHP_URL_PATH);
            $category = match (true) {
                str_contains($callbackPath, '/products') => 'products',
                str_contains($callbackPath, '/orders') => 'orders',
                default => 'other',
            };

            $breakdown[$category]++;
        }

        return $breakdown;
    }

    /**
     * 💾 EXPORT WEBHOOK DATA
     *
     * Export webhook statistics and events
     */
    public function exportWebhookData(): void
    {
        $data = [
            'shop_domain' => $this->shopDomain,
            'export_timestamp' => now()->toISOString(),
            'statistics' => $this->webhookStats,
            'subscriptions' => $this->subscriptions,
            'recent_events' => $this->recentEvents,
            'connection_status' => $this->connectionStatus,
            'recommended_topics' => $this->getRecommendedTopicsProperty(),
            'missing_topics' => $this->missingTopics,
        ];

        $filename = "shopify_webhook_data_{$this->shopDomain}_".now()->format('Y-m-d_H-i-s').'.json';

        $this->dispatch('download-json', [
            'filename' => $filename,
            'data' => $data,
        ]);

        $this->dispatch('success', ['message' => 'Webhook data exported successfully']);
    }

    /**
     * 🧪 TEST WEBHOOK SYSTEM
     *
     * Test the complete webhook system
     */
    public function testWebhookSystem(): void
    {
        $this->isLoading = true;

        try {
            $client = ShopifyApiClient::for($this->shopDomain);
            $result = $client->testAllConnections();

            if ($result['overall_success']) {
                $this->dispatch('success', [
                    'message' => "Webhook system test passed! All {$result['summary']['passed_tests']} tests successful.",
                ]);
            } else {
                $this->dispatch('warning', [
                    'message' => "Webhook system test completed with issues: {$result['summary']['failed_tests']} tests failed.",
                ]);
            }

            // Update connection status
            $this->connectionStatus = $result;
            $this->isConnected = $result['overall_success'];

        } catch (\Exception $e) {
            $this->dispatch('error', [
                'message' => 'Error testing webhook system: '.$e->getMessage(),
            ]);
        } finally {
            $this->isLoading = false;
        }
    }

    public function render()
    {
        return view('livewire.shopify.shopify-webhook-manager');
    }
}
