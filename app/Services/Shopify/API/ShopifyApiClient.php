<?php

namespace App\Services\Shopify\API;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 🏪 SHOPIFY API CLIENT
 *
 * Unified interface for all Shopify API operations.
 * Provides access to Products, Webhooks, and other APIs.
 */
class ShopifyApiClient
{
    protected string $shopDomain;

    protected ProductsApi $productsApi;

    protected WebhooksApi $webhooksApi;

    public function __construct(string $shopDomain)
    {
        $this->shopDomain = $shopDomain;
        $this->productsApi = new ProductsApi($shopDomain);
        $this->webhooksApi = new WebhooksApi($shopDomain);
    }

    /**
     * 🏭 STATIC FACTORY METHOD
     *
     * Create API client for specific shop domain
     *
     * @param  string  $shopDomain  Shop domain (e.g., 'example.myshopify.com')
     */
    public static function for(string $shopDomain): static
    {
        return new static($shopDomain);
    }

    /**
     * 🛍️ GET PRODUCTS API
     *
     * Access to products and variants management
     */
    public function products(): ProductsApi
    {
        return $this->productsApi;
    }

    /**
     * 🪝 GET WEBHOOKS API
     *
     * Access to webhook subscription management
     */
    public function webhooks(): WebhooksApi
    {
        return $this->webhooksApi;
    }

    /**
     * 🔍 GET SHOP SUMMARY
     *
     * Get comprehensive summary of shop information
     *
     * @return array<string, mixed>
     */
    public function getShopSummary(): array
    {
        $cacheKey = "shopify_shop_summary_{$this->shopDomain}";

        return Cache::remember($cacheKey, 1800, function () {
            try {
                // Get basic shop info
                $shopInfo = $this->productsApi->getShopInfo();

                // Test connection
                $connectionTest = $this->productsApi->testConnection();

                // Get product statistics
                $productStats = $this->productsApi->getProductStatistics();

                // Get webhook statistics
                $webhookStats = $this->webhooksApi->getWebhookStatistics();

                $summary = [
                    'shop_domain' => $this->shopDomain,
                    'shop_info' => $shopInfo,
                    'connection' => [
                        'status' => $connectionTest['success'] ? 'connected' : 'failed',
                        'last_tested' => now()->toISOString(),
                        'message' => $connectionTest['message'] ?? $connectionTest['error'] ?? '',
                    ],
                    'products' => $productStats['success'] ? $productStats : ['error' => 'Failed to fetch product stats'],
                    'webhooks' => $webhookStats['success'] ? $webhookStats : ['error' => 'Failed to fetch webhook stats'],
                    'api_capabilities' => [
                        'products' => [
                            'create_color_separated' => true,
                            'update_existing' => true,
                            'bulk_operations' => true,
                            'metafield_support' => true,
                        ],
                        'webhooks' => [
                            'subscription_management' => true,
                            'signature_verification' => true,
                            'real_time_sync' => true,
                            'topic_filtering' => true,
                        ],
                    ],
                    'integration_features' => [
                        'color_separation' => true,
                        'pim_sync' => true,
                        'real_time_updates' => true,
                        'bidirectional_sync' => true,
                        'metafield_tracking' => true,
                    ],
                ];

                Log::info('Shop summary generated', [
                    'shop_domain' => $this->shopDomain,
                    'connection_status' => $summary['connection']['status'],
                ]);

                return [
                    'success' => true,
                    'summary' => $summary,
                    'timestamp' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                Log::error('Failed to generate shop summary', [
                    'shop_domain' => $this->shopDomain,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'shop_domain' => $this->shopDomain,
                ];
            }
        });
    }

    /**
     * ✅ TEST ALL CONNECTIONS
     *
     * Test connectivity to all Shopify APIs
     *
     * @return array<string, mixed>
     */
    public function testAllConnections(): array
    {
        $results = [
            'shop_domain' => $this->shopDomain,
            'timestamp' => now()->toISOString(),
            'tests' => [],
            'overall_success' => false,
        ];

        // Test basic API connection
        $connectionTest = $this->productsApi->testConnection();
        $results['tests']['basic_connection'] = [
            'name' => 'Basic API Connection',
            'success' => $connectionTest['success'],
            'message' => $connectionTest['message'] ?? $connectionTest['error'] ?? '',
            'details' => $connectionTest,
        ];

        // Test products API
        $productsTest = $this->testProductsApi();
        $results['tests']['products_api'] = [
            'name' => 'Products API',
            'success' => $productsTest['success'],
            'message' => $productsTest['message'] ?? $productsTest['error'] ?? '',
            'details' => $productsTest,
        ];

        // Test webhooks API
        $webhooksTest = $this->testWebhooksApi();
        $results['tests']['webhooks_api'] = [
            'name' => 'Webhooks API',
            'success' => $webhooksTest['success'],
            'message' => $webhooksTest['message'] ?? $webhooksTest['error'] ?? '',
            'details' => $webhooksTest,
        ];

        // Test configuration
        $configTest = $this->productsApi->validateConfiguration();
        $results['tests']['configuration'] = [
            'name' => 'Configuration Validation',
            'success' => $configTest['valid'],
            'message' => $configTest['valid'] ? 'Configuration is valid' : 'Configuration errors found',
            'details' => $configTest,
        ];

        // Calculate overall success
        $results['overall_success'] = collect($results['tests'])->every(fn ($test) => $test['success']);

        // Summary
        $results['summary'] = [
            'total_tests' => count($results['tests']),
            'passed_tests' => collect($results['tests'])->where('success', true)->count(),
            'failed_tests' => collect($results['tests'])->where('success', false)->count(),
        ];

        Log::info('Connection tests completed', [
            'shop_domain' => $this->shopDomain,
            'overall_success' => $results['overall_success'],
            'passed_tests' => $results['summary']['passed_tests'],
            'failed_tests' => $results['summary']['failed_tests'],
        ]);

        return $results;
    }

    /**
     * 🛍️ TEST PRODUCTS API
     *
     * Test products API functionality
     *
     * @return array<string, mixed>
     */
    protected function testProductsApi(): array
    {
        try {
            // Try to get products (limit to 1 for testing)
            $result = $this->productsApi->getProducts(['first' => 1]);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Products API is working correctly',
                    'product_count' => count($result['data']['products']['edges'] ?? []),
                ];
            }

            return [
                'success' => false,
                'error' => $result['error'] ?? 'Unknown error',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 🪝 TEST WEBHOOKS API
     *
     * Test webhooks API functionality
     *
     * @return array<string, mixed>
     */
    protected function testWebhooksApi(): array
    {
        try {
            // Try to get existing webhooks
            $result = $this->webhooksApi->getSubscriptions(['first' => 1]);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Webhooks API is working correctly',
                    'subscription_count' => count($result['subscriptions'] ?? []),
                ];
            }

            return [
                'success' => false,
                'error' => $result['error'] ?? 'Unknown error',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 🚀 COMPLETE INTEGRATION SETUP
     *
     * Set up complete Shopify integration for PIM sync
     *
     * @param  string  $callbackUrl  Base callback URL for webhooks
     * @param  array<string, mixed>  $options  Setup options
     * @return array<string, mixed>
     */
    public function setupCompleteIntegration(string $callbackUrl, array $options = []): array
    {
        try {
            $setupResults = [
                'shop_domain' => $this->shopDomain,
                'timestamp' => now()->toISOString(),
                'steps' => [],
                'overall_success' => false,
            ];

            // Step 1: Test connections
            $connectionTest = $this->testAllConnections();
            $setupResults['steps']['connection_test'] = [
                'name' => 'Connection Test',
                'success' => $connectionTest['overall_success'],
                'details' => $connectionTest,
            ];

            if (! $connectionTest['overall_success']) {
                $setupResults['error'] = 'Connection tests failed';

                return $setupResults;
            }

            // Step 2: Setup webhooks
            $webhookSetup = $this->webhooksApi->setupCompleteWebhookSystem($callbackUrl, $options);
            $setupResults['steps']['webhook_setup'] = [
                'name' => 'Webhook Setup',
                'success' => $webhookSetup['success'],
                'details' => $webhookSetup,
            ];

            // Step 3: Validate final setup
            $finalValidation = $this->validateCompleteSetup();
            $setupResults['steps']['final_validation'] = [
                'name' => 'Final Validation',
                'success' => $finalValidation['success'],
                'details' => $finalValidation,
            ];

            // Calculate overall success
            $setupResults['overall_success'] = collect($setupResults['steps'])->every(fn ($step) => $step['success']);

            // Summary
            $setupResults['summary'] = [
                'total_steps' => count($setupResults['steps']),
                'completed_steps' => collect($setupResults['steps'])->where('success', true)->count(),
                'failed_steps' => collect($setupResults['steps'])->where('success', false)->count(),
                'webhook_subscriptions' => $webhookSetup['summary']['total_topics'] ?? 0,
            ];

            Log::info('Complete integration setup finished', [
                'shop_domain' => $this->shopDomain,
                'overall_success' => $setupResults['overall_success'],
                'webhook_subscriptions' => $setupResults['summary']['webhook_subscriptions'],
            ]);

            return $setupResults;

        } catch (\Exception $e) {
            Log::error('Complete integration setup failed', [
                'shop_domain' => $this->shopDomain,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'shop_domain' => $this->shopDomain,
            ];
        }
    }

    /**
     * ✅ VALIDATE COMPLETE SETUP
     *
     * Validate that the complete integration is properly configured
     *
     * @return array<string, mixed>
     */
    protected function validateCompleteSetup(): array
    {
        $validationResults = [
            'checks' => [],
            'overall_success' => false,
        ];

        // Check webhook coverage
        $webhookStats = $this->webhooksApi->getWebhookStatistics();
        $recommendedTopics = WebhooksApi::getAllRecommendedTopics();
        $missingTopics = $webhookStats['missing_topics'] ?? [];

        $validationResults['checks']['webhook_coverage'] = [
            'name' => 'Webhook Coverage',
            'success' => empty($missingTopics),
            'message' => empty($missingTopics)
                ? 'All recommended webhooks are configured'
                : 'Missing '.count($missingTopics).' recommended webhooks',
            'details' => [
                'total_recommended' => count($recommendedTopics),
                'configured' => count($recommendedTopics) - count($missingTopics),
                'missing' => $missingTopics,
            ],
        ];

        // Check API permissions
        $connectionTest = $this->testAllConnections();
        $validationResults['checks']['api_permissions'] = [
            'name' => 'API Permissions',
            'success' => $connectionTest['overall_success'],
            'message' => $connectionTest['overall_success']
                ? 'All API permissions are working'
                : 'Some API permissions are failing',
            'details' => $connectionTest['summary'],
        ];

        // Calculate overall success
        $validationResults['overall_success'] = collect($validationResults['checks'])->every(fn ($check) => $check['success']);

        return $validationResults;
    }

    /**
     * 🧹 CLEANUP INTEGRATION
     *
     * Remove all integration components (useful for testing/uninstall)
     *
     * @return array<string, mixed>
     */
    public function cleanupIntegration(): array
    {
        try {
            $cleanupResults = [
                'shop_domain' => $this->shopDomain,
                'timestamp' => now()->toISOString(),
                'steps' => [],
                'overall_success' => false,
            ];

            // Step 1: Cleanup webhooks
            $webhookCleanup = $this->webhooksApi->cleanupAllWebhooks();
            $cleanupResults['steps']['webhook_cleanup'] = [
                'name' => 'Webhook Cleanup',
                'success' => $webhookCleanup['success'],
                'details' => $webhookCleanup,
            ];

            // Step 2: Clear caches
            $cacheCleanup = $this->clearAllCaches();
            $cleanupResults['steps']['cache_cleanup'] = [
                'name' => 'Cache Cleanup',
                'success' => $cacheCleanup['success'],
                'details' => $cacheCleanup,
            ];

            // Calculate overall success
            $cleanupResults['overall_success'] = collect($cleanupResults['steps'])->every(fn ($step) => $step['success']);

            Log::info('Integration cleanup completed', [
                'shop_domain' => $this->shopDomain,
                'overall_success' => $cleanupResults['overall_success'],
            ]);

            return $cleanupResults;

        } catch (\Exception $e) {
            Log::error('Integration cleanup failed', [
                'shop_domain' => $this->shopDomain,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'shop_domain' => $this->shopDomain,
            ];
        }
    }

    /**
     * 🗑️ CLEAR ALL CACHES
     *
     * Clear all cached data for this shop
     *
     * @return array<string, mixed>
     */
    protected function clearAllCaches(): array
    {
        try {
            $cacheKeys = [
                "shopify_shop_summary_{$this->shopDomain}",
                "shopify_product_stats_{$this->shopDomain}",
            ];

            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }

            return [
                'success' => true,
                'cleared_keys' => count($cacheKeys),
                'keys' => $cacheKeys,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
