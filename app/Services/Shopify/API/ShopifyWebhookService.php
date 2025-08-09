<?php

namespace App\Services\Shopify\API;

use App\Services\ShopifyConnectService;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * 🔔 SHOPIFY WEBHOOK SERVICE 🔔
 * 
 * Handles real-time webhook subscriptions and management like a NOTIFICATION NINJA!
 * Sets up, manages, and processes Shopify webhooks for instant sync updates.
 * 
 * *adjusts ninja headband with sass* 💅
 */
class ShopifyWebhookService
{
    private ShopifyConnectService $shopifyService;
    
    // Webhook topics we care about for sync monitoring
    private const SYNC_WEBHOOK_TOPICS = [
        'products/create',
        'products/update', 
        'products/delete',
        'inventory_levels/update',
        'inventory_levels/connect',
        'inventory_levels/disconnect'
    ];

    public function __construct(ShopifyConnectService $shopifyService)
    {
        $this->shopifyService = $shopifyService;
    }

    /**
     * Subscribe to all sync-related webhooks for real-time monitoring
     */
    public function subscribeToSyncWebhooks(string $callbackUrl): array
    {
        Log::info("🔔 Subscribing to sync webhooks with callback: {$callbackUrl}");

        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach (self::SYNC_WEBHOOK_TOPICS as $topic) {
            try {
                $result = $this->subscribeToWebhook($topic, $callbackUrl);
                $results[$topic] = $result;
                
                if ($result['success']) {
                    $successCount++;
                    Log::info("✅ Successfully subscribed to {$topic}");
                } else {
                    $failureCount++;
                    Log::error("❌ Failed to subscribe to {$topic}: " . $result['error']);
                }
            } catch (Exception $e) {
                $failureCount++;
                $results[$topic] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                Log::error("❌ Exception subscribing to {$topic}: " . $e->getMessage());
            }
        }

        return [
            'success' => $failureCount === 0,
            'summary' => [
                'total_topics' => count(self::SYNC_WEBHOOK_TOPICS),
                'successful_subscriptions' => $successCount,
                'failed_subscriptions' => $failureCount
            ],
            'details' => $results,
            'message' => $failureCount === 0 
                ? "All {$successCount} webhooks subscribed successfully! 🎉"
                : "{$successCount} successful, {$failureCount} failed subscriptions"
        ];
    }

    /**
     * Subscribe to a specific webhook topic
     */
    public function subscribeToWebhook(string $topic, string $callbackUrl): array
    {
        Log::info("📋 Subscribing to webhook topic: {$topic}");

        // Use GraphQL to create webhook subscription
        $graphQL = $this->buildWebhookSubscriptionMutation($topic, $callbackUrl);
        
        try {
            $response = $this->shopifyService->getShopifySDK()->GraphQL->post($graphQL);
            
            if (isset($response['data']['webhookSubscriptionCreate']['webhookSubscription'])) {
                return [
                    'success' => true,
                    'webhook_id' => $response['data']['webhookSubscriptionCreate']['webhookSubscription']['id'],
                    'topic' => $topic,
                    'callback_url' => $callbackUrl
                ];
            } else {
                $errors = $response['data']['webhookSubscriptionCreate']['userErrors'] ?? [];
                return [
                    'success' => false,
                    'error' => 'Webhook creation failed',
                    'details' => $errors
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all current webhook subscriptions
     */
    public function getWebhookSubscriptions(): array
    {
        Log::info("📋 Getting current webhook subscriptions");

        $graphQL = <<<Query
query {
  webhookSubscriptions(first: 50) {
    edges {
      node {
        id
        topic
        callbackUrl
        createdAt
        updatedAt
        apiVersion
      }
    }
  }
}
Query;

        try {
            $response = $this->shopifyService->getShopifySDK()->GraphQL->post($graphQL);
            
            if (isset($response['data']['webhookSubscriptions']['edges'])) {
                $subscriptions = [];
                foreach ($response['data']['webhookSubscriptions']['edges'] as $edge) {
                    $node = $edge['node'];
                    $subscriptions[] = [
                        'id' => $node['id'],
                        'topic' => $node['topic'],
                        'callback_url' => $node['callbackUrl'],
                        'created_at' => $node['createdAt'],
                        'updated_at' => $node['updatedAt'],
                        'api_version' => $node['apiVersion'],
                        'is_sync_related' => in_array($node['topic'], self::SYNC_WEBHOOK_TOPICS)
                    ];
                }
                
                return [
                    'success' => true,
                    'subscriptions' => $subscriptions,
                    'total_count' => count($subscriptions),
                    'sync_related_count' => count(array_filter($subscriptions, fn($s) => $s['is_sync_related']))
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Unable to fetch webhook subscriptions',
                'response' => $response
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete a webhook subscription
     */
    public function deleteWebhookSubscription(string $webhookId): array
    {
        Log::info("🗑️ Deleting webhook subscription: {$webhookId}");

        $graphQL = <<<Mutation
mutation {
  webhookSubscriptionDelete(id: "$webhookId") {
    deletedWebhookSubscriptionId
    userErrors {
      field
      message
    }
  }
}
Mutation;

        try {
            $response = $this->shopifyService->getShopifySDK()->GraphQL->post($graphQL);
            
            if (isset($response['data']['webhookSubscriptionDelete']['deletedWebhookSubscriptionId'])) {
                return [
                    'success' => true,
                    'deleted_id' => $response['data']['webhookSubscriptionDelete']['deletedWebhookSubscriptionId']
                ];
            } else {
                $errors = $response['data']['webhookSubscriptionDelete']['userErrors'] ?? [];
                return [
                    'success' => false,
                    'error' => 'Webhook deletion failed',
                    'details' => $errors
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify webhook signature for security
     */
    public function verifyWebhookSignature(string $data, string $hmacHeader): bool
    {
        $webhookSecret = config('services.shopify.webhook_secret');
        
        if (!$webhookSecret || !$hmacHeader) {
            Log::warning("⚠️ Missing webhook secret or HMAC header");
            return false;
        }

        $calculatedHmac = base64_encode(hash_hmac('sha256', $data, $webhookSecret, true));
        $isValid = hash_equals($calculatedHmac, $hmacHeader);
        
        if (!$isValid) {
            Log::warning("🚨 Webhook signature verification failed", [
                'calculated' => substr($calculatedHmac, 0, 10) . '...',
                'received' => substr($hmacHeader, 0, 10) . '...'
            ]);
        }
        
        return $isValid;
    }

    /**
     * Get webhook health status and statistics
     */
    public function getWebhookHealth(): array
    {
        $subscriptions = $this->getWebhookSubscriptions();
        
        if (!$subscriptions['success']) {
            return [
                'status' => 'error',
                'error' => 'Unable to fetch webhook subscriptions',
                'details' => $subscriptions
            ];
        }

        $syncSubscriptions = array_filter($subscriptions['subscriptions'], fn($s) => $s['is_sync_related']);
        $missingSyncTopics = array_diff(self::SYNC_WEBHOOK_TOPICS, array_column($syncSubscriptions, 'topic'));
        
        $status = 'healthy';
        $recommendations = [];
        
        if (!empty($missingSyncTopics)) {
            $status = 'degraded';
            $recommendations[] = 'Missing webhook subscriptions for: ' . implode(', ', $missingSyncTopics);
        }
        
        if (empty($syncSubscriptions)) {
            $status = 'critical';
            $recommendations[] = 'No sync-related webhooks are active - real-time sync monitoring is disabled';
        }

        return [
            'status' => $status,
            'total_subscriptions' => $subscriptions['total_count'],
            'sync_subscriptions' => count($syncSubscriptions),
            'missing_sync_topics' => $missingSyncTopics,
            'recommendations' => $recommendations,
            'sync_coverage' => count(self::SYNC_WEBHOOK_TOPICS) - count($missingSyncTopics),
            'coverage_percentage' => round((count(self::SYNC_WEBHOOK_TOPICS) - count($missingSyncTopics)) / count(self::SYNC_WEBHOOK_TOPICS) * 100, 1)
        ];
    }

    /**
     * Clean up and re-subscribe all webhooks (maintenance operation)
     */
    public function refreshWebhookSubscriptions(string $callbackUrl): array
    {
        Log::info("🔄 Refreshing webhook subscriptions");

        // Get current subscriptions
        $current = $this->getWebhookSubscriptions();
        $deletedCount = 0;
        $errors = [];

        if ($current['success']) {
            // Delete existing sync-related webhooks
            foreach ($current['subscriptions'] as $subscription) {
                if ($subscription['is_sync_related']) {
                    $result = $this->deleteWebhookSubscription($subscription['id']);
                    if ($result['success']) {
                        $deletedCount++;
                    } else {
                        $errors[] = "Failed to delete {$subscription['topic']}: " . $result['error'];
                    }
                }
            }
        }

        // Re-subscribe to all sync webhooks
        $subscribeResult = $this->subscribeToSyncWebhooks($callbackUrl);

        return [
            'success' => $subscribeResult['success'] && empty($errors),
            'deleted_count' => $deletedCount,
            'subscription_result' => $subscribeResult,
            'errors' => $errors,
            'message' => "Deleted {$deletedCount} old webhooks, " . $subscribeResult['message']
        ];
    }

    // ===== PRIVATE HELPER METHODS ===== //

    private function buildWebhookSubscriptionMutation(string $topic, string $callbackUrl): string
    {
        return <<<Mutation
mutation {
  webhookSubscriptionCreate(topic: $topic, webhookSubscription: {
    callbackUrl: "$callbackUrl"
    format: JSON
  }) {
    webhookSubscription {
      id
      topic
      callbackUrl
      createdAt
    }
    userErrors {
      field
      message
    }
  }
}
Mutation;
    }

    /**
     * Get supported webhook topics for sync monitoring
     */
    public static function getSyncWebhookTopics(): array
    {
        return self::SYNC_WEBHOOK_TOPICS;
    }

    /**
     * Check if a topic is sync-related
     */
    public static function isSyncRelatedTopic(string $topic): bool
    {
        return in_array($topic, self::SYNC_WEBHOOK_TOPICS);
    }

    /**
     * Get webhook callback URL for the application
     */
    public function getCallbackUrl(): string
    {
        return url('/api/shopify/webhooks');
    }

    /**
     * Generate webhook setup instructions for the user
     */
    public function getSetupInstructions(): array
    {
        return [
            'callback_url' => $this->getCallbackUrl(),
            'required_topics' => self::SYNC_WEBHOOK_TOPICS,
            'setup_steps' => [
                '1. Ensure your Shopify app has webhook permissions',
                '2. Use the callback URL: ' . $this->getCallbackUrl(),
                '3. Subscribe to all sync-related webhook topics',
                '4. Set webhook secret in your .env file',
                '5. Test webhook delivery with a sample event'
            ],
            'security_notes' => [
                'Always verify webhook signatures',
                'Use HTTPS for webhook endpoints',
                'Store webhook secret securely',
                'Log webhook events for debugging'
            ]
        ];
    }
}