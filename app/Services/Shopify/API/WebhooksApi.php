<?php

namespace App\Services\Shopify\API;

use Illuminate\Support\Facades\Log;

/**
 * 🪝 SHOPIFY WEBHOOKS API
 *
 * Handles webhook subscription management and verification.
 * Supports real-time bidirectional sync between PIM and Shopify.
 */
class WebhooksApi extends BaseShopifyApi
{
    /**
     * 📝 CREATE WEBHOOK SUBSCRIPTION
     *
     * Creates a new webhook subscription for specified topics
     *
     * @param  array<string>  $topics  Webhook topics to subscribe to
     * @param  string  $callbackUrl  URL to receive webhook notifications
     * @param  array<string, mixed>  $options  Additional options
     * @return array<string, mixed>
     */
    public function createSubscription(array $topics, string $callbackUrl, array $options = []): array
    {
        try {
            $createdSubscriptions = [];
            $errors = [];

            foreach ($topics as $topic) {
                $result = $this->createSingleSubscription($topic, $callbackUrl, $options);

                if ($result['success']) {
                    $createdSubscriptions[] = $result['subscription'];
                } else {
                    $errors[] = [
                        'topic' => $topic,
                        'error' => $result['error'],
                    ];
                }
            }

            Log::info('Webhook subscriptions created', [
                'shop_domain' => $this->shopDomain,
                'topics' => $topics,
                'callback_url' => $callbackUrl,
                'subscriptions_created' => count($createdSubscriptions),
                'errors' => count($errors),
            ]);

            return [
                'success' => ! empty($createdSubscriptions),
                'subscriptions' => $createdSubscriptions,
                'errors' => $errors,
                'summary' => [
                    'total_topics' => count($topics),
                    'subscriptions_created' => count($createdSubscriptions),
                    'errors_count' => count($errors),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create webhook subscriptions', [
                'shop_domain' => $this->shopDomain,
                'topics' => $topics,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 🎯 CREATE SINGLE SUBSCRIPTION
     *
     * Creates one webhook subscription for a specific topic
     *
     * @param  string  $topic  Webhook topic
     * @param  string  $callbackUrl  Callback URL
     * @param  array<string, mixed>  $options  Additional options
     * @return array<string, mixed>
     */
    protected function createSingleSubscription(string $topic, string $callbackUrl, array $options = []): array
    {
        $mutation = <<<'GRAPHQL'
        mutation CreateWebhookSubscription($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) {
            webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) {
                webhookSubscription {
                    id
                    callbackUrl
                    format
                    includeFields
                    metafieldNamespaces
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $variables = [
            'topic' => strtoupper($topic),
            'webhookSubscription' => [
                'callbackUrl' => $callbackUrl,
                'format' => $options['format'] ?? 'JSON',
                'includeFields' => $options['include_fields'] ?? [],
                'metafieldNamespaces' => $options['metafield_namespaces'] ?? ['pim'],
            ],
        ];

        $result = $this->graphql($mutation, $variables);

        if ($result['success']) {
            $userErrors = $result['data']['webhookSubscriptionCreate']['userErrors'] ?? [];
            if (! empty($userErrors)) {
                return [
                    'success' => false,
                    'error' => 'Webhook subscription creation failed: '.collect($userErrors)->pluck('message')->implode(', '),
                    'user_errors' => $userErrors,
                ];
            }

            $subscription = $result['data']['webhookSubscriptionCreate']['webhookSubscription'];

            return [
                'success' => true,
                'subscription' => [
                    'id' => $subscription['id'],
                    'topic' => $topic,
                    'callback_url' => $subscription['callbackUrl'],
                    'format' => $subscription['format'],
                    'include_fields' => $subscription['includeFields'],
                    'metafield_namespaces' => $subscription['metafieldNamespaces'],
                ],
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Unknown error creating webhook subscription',
        ];
    }

    /**
     * 📋 GET WEBHOOK SUBSCRIPTIONS
     *
     * Retrieves all existing webhook subscriptions
     *
     * @param  array<string, mixed>  $options  Query options
     * @return array<string, mixed>
     */
    public function getSubscriptions(array $options = []): array
    {
        $first = $options['first'] ?? 50;
        $after = isset($options['after']) ? ", after: \"{$options['after']}\"" : '';
        $topics = isset($options['topics']) ? ', topics: ['.implode(', ', array_map(fn ($t) => strtoupper($t), $options['topics'])).']' : '';

        $query = <<<GRAPHQL
        query GetWebhookSubscriptions {
            webhookSubscriptions(first: $first$after$topics) {
                edges {
                    cursor
                    node {
                        id
                        callbackUrl
                        format
                        includeFields
                        metafieldNamespaces
                        createdAt
                        updatedAt
                    }
                }
                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }
        GRAPHQL;

        $result = $this->graphql($query);

        if ($result['success']) {
            $subscriptions = [];
            foreach ($result['data']['webhookSubscriptions']['edges'] ?? [] as $edge) {
                $subscriptions[] = $edge['node'];
            }

            return [
                'success' => true,
                'subscriptions' => $subscriptions,
                'page_info' => $result['data']['webhookSubscriptions']['pageInfo'] ?? [],
                'total' => count($subscriptions),
            ];
        }

        return $result;
    }

    /**
     * 🗑️ DELETE WEBHOOK SUBSCRIPTION
     *
     * Deletes an existing webhook subscription
     *
     * @param  string  $subscriptionId  Webhook subscription ID
     * @return array<string, mixed>
     */
    public function deleteSubscription(string $subscriptionId): array
    {
        $mutation = <<<'GRAPHQL'
        mutation DeleteWebhookSubscription($id: ID!) {
            webhookSubscriptionDelete(id: $id) {
                deletedWebhookSubscriptionId
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $result = $this->graphql($mutation, ['id' => $subscriptionId]);

        if ($result['success']) {
            $userErrors = $result['data']['webhookSubscriptionDelete']['userErrors'] ?? [];
            if (! empty($userErrors)) {
                return [
                    'success' => false,
                    'error' => 'Webhook subscription deletion failed: '.collect($userErrors)->pluck('message')->implode(', '),
                    'user_errors' => $userErrors,
                ];
            }

            return [
                'success' => true,
                'deleted_id' => $result['data']['webhookSubscriptionDelete']['deletedWebhookSubscriptionId'],
            ];
        }

        return $result;
    }

    /**
     * 🔄 UPDATE WEBHOOK SUBSCRIPTION
     *
     * Updates an existing webhook subscription
     *
     * @param  string  $subscriptionId  Webhook subscription ID
     * @param  array<string, mixed>  $input  Update input
     * @return array<string, mixed>
     */
    public function updateSubscription(string $subscriptionId, array $input): array
    {
        $input['id'] = $subscriptionId;

        $mutation = <<<'GRAPHQL'
        mutation UpdateWebhookSubscription($webhookSubscription: WebhookSubscriptionInput!) {
            webhookSubscriptionUpdate(webhookSubscription: $webhookSubscription) {
                webhookSubscription {
                    id
                    callbackUrl
                    format
                    includeFields
                    metafieldNamespaces
                    updatedAt
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $result = $this->graphql($mutation, ['webhookSubscription' => $input]);

        if ($result['success']) {
            $userErrors = $result['data']['webhookSubscriptionUpdate']['userErrors'] ?? [];
            if (! empty($userErrors)) {
                return [
                    'success' => false,
                    'error' => 'Webhook subscription update failed: '.collect($userErrors)->pluck('message')->implode(', '),
                    'user_errors' => $userErrors,
                ];
            }
        }

        return $result;
    }

    /**
     * 🛡️ VERIFY WEBHOOK SIGNATURE
     *
     * Verifies the HMAC signature of a webhook payload
     *
     * @param  string  $payload  Raw webhook payload
     * @param  string  $signature  HMAC signature from headers
     * @param  string|null  $webhookSecret  Webhook secret (optional, uses config if not provided)
     */
    public static function verifyWebhookSignature(string $payload, string $signature, ?string $webhookSecret = null): bool
    {
        $webhookSecret = $webhookSecret ?? config('services.shopify.webhook_secret');

        if (! $webhookSecret) {
            Log::warning('Webhook secret not configured');

            return false;
        }

        // Remove 'sha256=' prefix if present
        $signature = str_replace('sha256=', '', $signature);

        // Calculate expected signature
        $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $webhookSecret, true));

        // Constant time comparison to prevent timing attacks
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * 📦 GET PRODUCT WEBHOOK TOPICS
     *
     * Returns list of product-related webhook topics for PIM sync
     *
     * @return array<string>
     */
    public static function getProductWebhookTopics(): array
    {
        return [
            'products/create',
            'products/update',
            'products/delete',
            'product_listings/add',
            'product_listings/remove',
            'product_listings/update',
            'inventory_levels/connect',
            'inventory_levels/update',
            'inventory_levels/disconnect',
        ];
    }

    /**
     * 📦 GET ORDER WEBHOOK TOPICS
     *
     * Returns list of order-related webhook topics
     *
     * @return array<string>
     */
    public static function getOrderWebhookTopics(): array
    {
        return [
            'orders/create',
            'orders/update',
            'orders/paid',
            'orders/cancelled',
            'orders/fulfilled',
            'orders/partially_fulfilled',
            'order_transactions/create',
        ];
    }

    /**
     * 🛍️ GET ALL RECOMMENDED TOPICS
     *
     * Returns comprehensive list of recommended webhook topics for PIM integration
     *
     * @return array<string>
     */
    public static function getAllRecommendedTopics(): array
    {
        return array_merge(
            self::getProductWebhookTopics(),
            self::getOrderWebhookTopics(),
            [
                'app/uninstalled',
                'shop/update',
                'customers/create',
                'customers/update',
                'customers/delete',
                'collections/create',
                'collections/update',
                'collections/delete',
            ]
        );
    }

    /**
     * 🚀 SETUP COMPLETE WEBHOOK SYSTEM
     *
     * Sets up all recommended webhooks for PIM integration
     *
     * @param  string  $callbackUrl  Base callback URL
     * @param  array<string, mixed>  $options  Setup options
     * @return array<string, mixed>
     */
    public function setupCompleteWebhookSystem(string $callbackUrl, array $options = []): array
    {
        $topics = $options['topics'] ?? self::getAllRecommendedTopics();
        $setupResults = [];
        $errors = [];

        // Group topics for better organization
        $topicGroups = [
            'products' => array_intersect($topics, self::getProductWebhookTopics()),
            'orders' => array_intersect($topics, self::getOrderWebhookTopics()),
            'other' => array_diff($topics, self::getProductWebhookTopics(), self::getOrderWebhookTopics()),
        ];

        foreach ($topicGroups as $groupName => $groupTopics) {
            if (empty($groupTopics)) {
                continue;
            }

            $groupCallbackUrl = $callbackUrl."/webhooks/shopify/{$groupName}";
            $result = $this->createSubscription($groupTopics, $groupCallbackUrl, $options);

            $setupResults[$groupName] = $result;

            if (! $result['success']) {
                $errors = array_merge($errors, $result['errors'] ?? []);
            }
        }

        Log::info('Complete webhook system setup completed', [
            'shop_domain' => $this->shopDomain,
            'total_topics' => count($topics),
            'groups_processed' => count($topicGroups),
            'errors' => count($errors),
        ]);

        return [
            'success' => empty($errors),
            'setup_results' => $setupResults,
            'errors' => $errors,
            'summary' => [
                'total_topics' => count($topics),
                'groups_configured' => count($setupResults),
                'total_errors' => count($errors),
            ],
        ];
    }

    /**
     * 🧹 CLEANUP WEBHOOKS
     *
     * Removes all existing webhook subscriptions (useful for testing)
     *
     * @return array<string, mixed>
     */
    public function cleanupAllWebhooks(): array
    {
        $subscriptions = $this->getSubscriptions();

        if (! $subscriptions['success']) {
            return $subscriptions;
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($subscriptions['subscriptions'] as $subscription) {
            $result = $this->deleteSubscription($subscription['id']);

            if ($result['success']) {
                $deletedCount++;
            } else {
                $errors[] = [
                    'id' => $subscription['id'],
                    'error' => $result['error'],
                ];
            }
        }

        Log::info('Webhook cleanup completed', [
            'shop_domain' => $this->shopDomain,
            'subscriptions_deleted' => $deletedCount,
            'errors' => count($errors),
        ]);

        return [
            'success' => empty($errors),
            'deleted_count' => $deletedCount,
            'errors' => $errors,
        ];
    }

    /**
     * 📊 GET WEBHOOK STATISTICS
     *
     * Get statistics about webhook subscriptions
     *
     * @return array<string, mixed>
     */
    public function getWebhookStatistics(): array
    {
        $subscriptions = $this->getSubscriptions();

        if (! $subscriptions['success']) {
            return $subscriptions;
        }

        $stats = [
            'total_subscriptions' => count($subscriptions['subscriptions']),
            'topics' => [],
            'formats' => [],
            'callback_urls' => [],
        ];

        foreach ($subscriptions['subscriptions'] as $subscription) {
            // Extract topic from callback URL or subscription data
            $callbackPath = parse_url($subscription['callbackUrl'], PHP_URL_PATH);
            $pathSegments = explode('/', trim($callbackPath, '/'));
            $topic = end($pathSegments);

            $stats['topics'][$topic] = ($stats['topics'][$topic] ?? 0) + 1;
            $stats['formats'][$subscription['format']] = ($stats['formats'][$subscription['format']] ?? 0) + 1;

            $baseUrl = parse_url($subscription['callbackUrl'], PHP_URL_SCHEME).'://'.parse_url($subscription['callbackUrl'], PHP_URL_HOST);
            $stats['callback_urls'][$baseUrl] = ($stats['callback_urls'][$baseUrl] ?? 0) + 1;
        }

        return [
            'success' => true,
            'statistics' => $stats,
            'recommended_topics' => self::getAllRecommendedTopics(),
            'missing_topics' => array_diff(self::getAllRecommendedTopics(), array_keys($stats['topics'])),
        ];
    }
}
