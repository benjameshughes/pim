<?php

namespace App\Services\Marketplace\API\Builders;

use App\Models\SyncAccount;
use App\Services\Marketplace\API\AbstractMarketplaceService;
use App\Services\Marketplace\API\Implementations\AmazonMarketplaceService;
use App\Services\Marketplace\API\Implementations\EbayMarketplaceService;
use App\Services\Marketplace\API\Implementations\MiraklMarketplaceService;
use App\Services\Marketplace\API\Implementations\ShopifyMarketplaceService;
use InvalidArgumentException;

/**
 * 🏗️ MARKETPLACE CLIENT BUILDER
 *
 * Fluent API builder for creating and configuring marketplace service clients.
 * Implements the Builder Pattern for elegant service configuration.
 *
 * Usage:
 * $client = MarketplaceClient::for('shopify')
 *     ->withAccount($syncAccount)
 *     ->withRetryPolicy(3, 1000)
 *     ->withRateLimiting(40, 60)
 *     ->enableDebugMode()
 *     ->build();
 */
class MarketplaceClientBuilder
{
    protected string $marketplace;

    protected ?SyncAccount $account = null;

    protected array $config = [];

    protected int $retryAttempts = 3;

    protected int $retryDelay = 1000;

    protected array $rateLimits = [];

    protected bool $debugMode = false;

    protected $errorHandler = null;

    protected array $defaultHeaders = [];

    protected int $timeout = 30;

    public function __construct(string $marketplace)
    {
        $this->marketplace = strtolower($marketplace);
        $this->validateMarketplace();
    }

    /**
     * 🔗 Set the sync account for authentication
     */
    public function withAccount(SyncAccount $account): static
    {
        $this->account = $account;

        return $this;
    }

    /**
     * ⚙️ Set custom configuration options
     */
    public function withConfig(array $config): static
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * 🔄 Configure retry policy
     */
    public function withRetryPolicy(int $attempts, int $delayMs = 1000): static
    {
        $this->retryAttempts = max(1, $attempts);
        $this->retryDelay = max(100, $delayMs);

        return $this;
    }

    /**
     * ⏱️ Configure rate limiting
     */
    public function withRateLimiting(int $requestsPerMinute, ?int $burstLimit = null): static
    {
        $this->rateLimits = [
            'requests_per_minute' => $requestsPerMinute,
            'burst_limit' => $burstLimit ?? $requestsPerMinute,
        ];

        return $this;
    }

    /**
     * ⏲️ Set request timeout
     */
    public function withTimeout(int $seconds): static
    {
        $this->timeout = max(5, $seconds);

        return $this;
    }

    /**
     * 📋 Add default headers for all requests
     */
    public function withHeaders(array $headers): static
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);

        return $this;
    }

    /**
     * 🐛 Enable debug mode for detailed logging
     */
    public function enableDebugMode(): static
    {
        $this->debugMode = true;

        return $this;
    }

    /**
     * 🚫 Disable debug mode
     */
    public function disableDebugMode(): static
    {
        $this->debugMode = false;

        return $this;
    }

    /**
     * ⚠️ Set custom error handler
     */
    public function withErrorHandler(callable $handler): static
    {
        $this->errorHandler = $handler;

        return $this;
    }

    /**
     * 📊 Enable performance monitoring
     */
    public function withPerformanceMonitoring(): static
    {
        $this->config['performance_monitoring'] = true;

        return $this;
    }

    /**
     * 🔒 Enable sandbox mode (for testing)
     */
    public function enableSandboxMode(): static
    {
        $this->config['sandbox_mode'] = true;

        return $this;
    }

    /**
     * 🏭 Build the marketplace service instance
     */
    public function build(): AbstractMarketplaceService
    {
        // Validate that we have required configuration
        $this->validateBuildConfiguration();

        // Create the appropriate service instance
        $service = $this->createServiceInstance();

        // Configure the service with account
        if ($this->account) {
            $service->withAccount($this->account);
        }

        // Apply configuration
        $this->applyConfiguration($service);

        return $service;
    }

    /**
     * 🏭 Create the appropriate service instance based on marketplace
     */
    protected function createServiceInstance(): AbstractMarketplaceService
    {
        return match ($this->marketplace) {
            'shopify' => new ShopifyMarketplaceService,
            'ebay' => new EbayMarketplaceService,
            'amazon' => new AmazonMarketplaceService,
            'mirakl' => new MiraklMarketplaceService,
            default => throw new InvalidArgumentException("Unsupported marketplace: {$this->marketplace}")
        };
    }

    /**
     * ⚙️ Apply builder configuration to service instance
     */
    protected function applyConfiguration(AbstractMarketplaceService $service): void
    {
        // Use reflection to set protected properties if service supports it
        $reflection = new \ReflectionClass($service);

        // Set retry configuration
        if ($reflection->hasProperty('retryAttempts')) {
            $property = $reflection->getProperty('retryAttempts');
            $property->setAccessible(true);
            $property->setValue($service, $this->retryAttempts);
        }

        if ($reflection->hasProperty('retryDelay')) {
            $property = $reflection->getProperty('retryDelay');
            $property->setAccessible(true);
            $property->setValue($service, $this->retryDelay);
        }

        // Set rate limits
        if ($reflection->hasProperty('rateLimits') && ! empty($this->rateLimits)) {
            $property = $reflection->getProperty('rateLimits');
            $property->setAccessible(true);
            $property->setValue($service, $this->rateLimits);
        }

        // Merge additional config
        if ($reflection->hasProperty('config')) {
            $property = $reflection->getProperty('config');
            $property->setAccessible(true);
            $currentConfig = $property->getValue($service) ?? [];
            $mergedConfig = array_merge($currentConfig, $this->config, [
                'debug_mode' => $this->debugMode,
                'timeout' => $this->timeout,
                'default_headers' => $this->defaultHeaders,
                'error_handler' => $this->errorHandler,
            ]);
            $property->setValue($service, $mergedConfig);
        }
    }

    /**
     * ✅ Validate that the marketplace is supported
     */
    protected function validateMarketplace(): void
    {
        $supportedMarketplaces = ['shopify', 'ebay', 'amazon', 'mirakl'];

        if (! in_array($this->marketplace, $supportedMarketplaces)) {
            throw new InvalidArgumentException(
                "Unsupported marketplace '{$this->marketplace}'. Supported: ".
                implode(', ', $supportedMarketplaces)
            );
        }
    }

    /**
     * ✅ Validate configuration before building
     */
    protected function validateBuildConfiguration(): void
    {
        if (! $this->account) {
            throw new InvalidArgumentException(
                'SyncAccount is required. Use withAccount() to set the account before building.'
            );
        }

        if (! $this->account->is_active) {
            throw new InvalidArgumentException(
                'Cannot build client for inactive sync account.'
            );
        }

        if (empty($this->account->credentials)) {
            throw new InvalidArgumentException(
                'Sync account must have credentials configured.'
            );
        }
    }

    /**
     * 📋 Get list of supported marketplaces
     */
    public static function getSupportedMarketplaces(): array
    {
        return [
            'shopify' => 'Shopify',
            'ebay' => 'eBay',
            'amazon' => 'Amazon',
            'mirakl' => 'Mirakl',
        ];
    }

    /**
     * 📊 Get builder configuration summary
     */
    public function getConfigurationSummary(): array
    {
        return [
            'marketplace' => $this->marketplace,
            'account_id' => $this->account?->id,
            'retry_attempts' => $this->retryAttempts,
            'retry_delay_ms' => $this->retryDelay,
            'rate_limits' => $this->rateLimits,
            'timeout_seconds' => $this->timeout,
            'debug_mode' => $this->debugMode,
            'custom_config_keys' => array_keys($this->config),
            'custom_headers_count' => count($this->defaultHeaders),
        ];
    }
}
