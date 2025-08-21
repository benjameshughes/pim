<?php

namespace App\Services\Shopify\Builders;

use App\Services\Shopify\API\ShopifyWebhookService;
use Illuminate\Support\Facades\Log;

/**
 * 🔔 WEBHOOK SUBSCRIPTION BUILDER 🔔
 *
 * Fluent API for building and managing Shopify webhook subscriptions like a NOTIFICATION ARCHITECT!
 * Uses the fabulous Builder Pattern for elegant webhook configuration! 💅
 */
class WebhookSubscriptionBuilder
{
    private ShopifyWebhookService $webhookService;

    private array $topics = [];

    private ?string $callbackUrl = null;

    private array $options = [];

    private bool $includeAllSyncTopics = false;

    public function __construct(ShopifyWebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Add a specific webhook topic
     */
    public function topic(string $topic): static
    {
        if (! in_array($topic, $this->topics)) {
            $this->topics[] = $topic;
        }

        Log::debug('📋 Added webhook topic', [
            'topic' => $topic,
            'total_topics' => count($this->topics),
        ]);

        return $this;
    }

    /**
     * Add multiple webhook topics at once
     */
    public function topics(array $topics): static
    {
        foreach ($topics as $topic) {
            $this->topic($topic);
        }

        return $this;
    }

    /**
     * Include ALL sync-related webhook topics for comprehensive monitoring
     */
    public function allSyncTopics(): static
    {
        $this->includeAllSyncTopics = true;
        $syncTopics = ShopifyWebhookService::getSyncWebhookTopics();

        Log::info('🚀 Including all sync-related topics', [
            'sync_topics' => $syncTopics,
            'count' => count($syncTopics),
        ]);

        return $this->topics($syncTopics);
    }

    /**
     * Product-related webhooks only
     */
    public function productWebhooks(): static
    {
        return $this->topics([
            'products/create',
            'products/update',
            'products/delete',
        ]);
    }

    /**
     * Inventory-related webhooks only
     */
    public function inventoryWebhooks(): static
    {
        return $this->topics([
            'inventory_levels/update',
            'inventory_levels/connect',
            'inventory_levels/disconnect',
        ]);
    }

    /**
     * Set the callback URL for webhook delivery
     */
    public function callbackUrl(string $url): static
    {
        $this->callbackUrl = $url;

        Log::debug('📞 Set webhook callback URL', [
            'callback_url' => $url,
        ]);

        return $this;
    }

    /**
     * Use the default application webhook endpoint
     */
    public function defaultCallback(): static
    {
        return $this->callbackUrl($this->webhookService->getCallbackUrl());
    }

    /**
     * Set webhook options/configuration
     */
    public function options(array $options): static
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * Enable webhook signature verification (recommended for production)
     */
    public function withSignatureVerification(): static
    {
        $this->options['verify_signature'] = true;

        return $this;
    }

    /**
     * Set custom timeout for webhook delivery
     */
    public function timeout(int $seconds): static
    {
        $this->options['timeout'] = $seconds;

        return $this;
    }

    /**
     * EXECUTE: Subscribe to all configured webhooks! ✨
     */
    public function execute(): array
    {
        Log::info('🔔 Executing webhook subscription builder', [
            'topics_count' => count($this->topics),
            'callback_url' => $this->callbackUrl,
            'options' => $this->options,
        ]);

        // Validate configuration
        $this->validate();

        try {
            // Subscribe to all configured topics
            $results = $this->webhookService->subscribeToSyncWebhooks($this->callbackUrl);

            Log::info('✅ Webhook subscriptions completed', [
                'success' => $results['success'],
                'successful_subscriptions' => $results['summary']['successful_subscriptions'] ?? 0,
                'failed_subscriptions' => $results['summary']['failed_subscriptions'] ?? 0,
            ]);

            return $this->buildResponse($results);

        } catch (\Exception $e) {
            Log::error('❌ Webhook subscription failed', [
                'error' => $e->getMessage(),
                'topics' => $this->topics,
                'callback_url' => $this->callbackUrl,
            ]);

            return [
                'success' => false,
                'message' => 'Webhook subscription failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
                'configuration' => [
                    'topics' => $this->topics,
                    'callback_url' => $this->callbackUrl,
                    'options' => $this->options,
                ],
            ];
        }
    }

    /**
     * Build a subscription configuration without executing
     */
    public function build(): array
    {
        return [
            'topics' => $this->topics,
            'callback_url' => $this->callbackUrl,
            'options' => $this->options,
            'sync_coverage' => $this->calculateSyncCoverage(),
            'setup_instructions' => $this->getSetupInstructions(),
        ];
    }

    /**
     * Get webhook health status after subscription
     */
    public function checkHealth(): array
    {
        return $this->webhookService->getWebhookHealth();
    }

    /**
     * Refresh/reset all webhook subscriptions
     */
    public function refresh(): array
    {
        if (! $this->callbackUrl) {
            $this->defaultCallback();
        }

        Log::info('🔄 Refreshing webhook subscriptions');

        return $this->webhookService->refreshWebhookSubscriptions($this->callbackUrl);
    }

    // ===== PRIVATE HELPER METHODS ===== //

    /**
     * Validate builder configuration
     */
    private function validate(): void
    {
        if (empty($this->topics)) {
            throw new \InvalidArgumentException('No webhook topics configured. Use ->topic() or ->allSyncTopics()');
        }

        if (! $this->callbackUrl) {
            throw new \InvalidArgumentException('No callback URL configured. Use ->callbackUrl() or ->defaultCallback()');
        }

        if (! filter_var($this->callbackUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid callback URL format: '.$this->callbackUrl);
        }
    }

    /**
     * Build standardized response
     */
    private function buildResponse(array $results): array
    {
        return [
            'success' => $results['success'],
            'message' => $results['message'] ?? 'Webhook subscriptions processed',
            'summary' => $results['summary'] ?? [],
            'details' => $results['details'] ?? [],
            'configuration' => [
                'topics' => $this->topics,
                'callback_url' => $this->callbackUrl,
                'options' => $this->options,
            ],
            'sync_coverage' => $this->calculateSyncCoverage(),
            'recommendations' => $this->generateRecommendations($results),
        ];
    }

    /**
     * Calculate sync topic coverage percentage
     */
    private function calculateSyncCoverage(): array
    {
        $allSyncTopics = ShopifyWebhookService::getSyncWebhookTopics();
        $coveredTopics = array_intersect($this->topics, $allSyncTopics);
        $coveragePercentage = count($allSyncTopics) > 0
            ? round((count($coveredTopics) / count($allSyncTopics)) * 100, 1)
            : 0;

        return [
            'covered_topics' => count($coveredTopics),
            'total_sync_topics' => count($allSyncTopics),
            'coverage_percentage' => $coveragePercentage,
            'missing_topics' => array_diff($allSyncTopics, $coveredTopics),
            'status' => $coveragePercentage >= 100 ? 'complete' : 'partial',
        ];
    }

    /**
     * Generate setup instructions
     */
    private function getSetupInstructions(): array
    {
        return [
            'steps' => [
                '1. Ensure your callback URL is publicly accessible',
                '2. Implement webhook signature verification for security',
                '3. Test webhook delivery with a sample event',
                '4. Monitor webhook health regularly',
                '5. Handle webhook failures gracefully',
            ],
            'security_checklist' => [
                'Verify webhook signatures',
                'Use HTTPS for callback URLs',
                'Implement proper error handling',
                'Log webhook events for debugging',
                'Rate limit webhook processing',
            ],
            'callback_url' => $this->callbackUrl,
            'topics_configured' => $this->topics,
        ];
    }

    /**
     * Generate recommendations based on results
     */
    private function generateRecommendations(array $results): array
    {
        $recommendations = [];

        $summary = $results['summary'] ?? [];
        $failedCount = $summary['failed_subscriptions'] ?? 0;

        if ($failedCount > 0) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Some webhook subscriptions failed',
                'description' => 'Review failed subscriptions and retry setup',
                'action' => 'Check error details and fix configuration issues',
            ];
        }

        $syncCoverage = $this->calculateSyncCoverage();
        if ($syncCoverage['coverage_percentage'] < 100) {
            $recommendations[] = [
                'type' => 'info',
                'title' => 'Incomplete sync coverage',
                'description' => 'Missing webhook subscriptions for complete sync monitoring',
                'action' => 'Use ->allSyncTopics() for full coverage',
            ];
        }

        if (empty($this->options['verify_signature'] ?? false)) {
            $recommendations[] = [
                'type' => 'security',
                'title' => 'Enable signature verification',
                'description' => 'Webhook signature verification is not enabled',
                'action' => 'Use ->withSignatureVerification() for security',
            ];
        }

        return $recommendations;
    }

    // ===== STATIC FACTORY METHODS ===== //

    /**
     * Create a new webhook subscription builder
     */
    public static function create(ShopifyWebhookService $webhookService): static
    {
        return new static($webhookService);
    }

    /**
     * Quick setup for complete sync monitoring
     */
    public static function fullSyncMonitoring(ShopifyWebhookService $webhookService): static
    {
        return static::create($webhookService)
            ->allSyncTopics()
            ->defaultCallback()
            ->withSignatureVerification();
    }

    /**
     * Minimal product monitoring setup
     */
    public static function productMonitoring(ShopifyWebhookService $webhookService): static
    {
        return static::create($webhookService)
            ->productWebhooks()
            ->defaultCallback();
    }
}
