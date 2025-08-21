<?php

namespace App\Services\Marketplace\API;

use App\Models\SyncAccount;
use App\Services\Marketplace\API\Builders\InventoryOperationBuilder;
use App\Services\Marketplace\API\Builders\MarketplaceClientBuilder;
use App\Services\Marketplace\API\Builders\OrderOperationBuilder;
use App\Services\Marketplace\API\Builders\ProductOperationBuilder;
use App\Services\Marketplace\API\Contracts\InventoryOperationsInterface;
use App\Services\Marketplace\API\Contracts\MarketplaceServiceInterface;
use App\Services\Marketplace\API\Contracts\OrderOperationsInterface;
use App\Services\Marketplace\API\Contracts\ProductOperationsInterface;
use App\Services\Marketplace\API\Repositories\MarketplaceInventoryRepository;
use App\Services\Marketplace\API\Repositories\MarketplaceOrderRepository;
use App\Services\Marketplace\API\Repositories\MarketplaceProductRepository;
use App\ValueObjects\MarketplaceCredentials;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 🌟 ABSTRACT MARKETPLACE SERVICE
 *
 * Base class for all marketplace service implementations.
 * Provides common functionality, error handling, rate limiting,
 * and standardized API patterns using the Builder Pattern.
 */
abstract class AbstractMarketplaceService implements InventoryOperationsInterface, MarketplaceServiceInterface, OrderOperationsInterface, ProductOperationsInterface
{
    protected ?SyncAccount $account = null;

    protected ?MarketplaceCredentials $credentials = null;

    protected array $config = [];

    protected array $rateLimits = [];

    protected int $retryAttempts = 3;

    protected int $retryDelay = 1000; // milliseconds

    /**
     * 🏗️ Factory method to create marketplace client builder
     */
    public static function for(string $marketplace): MarketplaceClientBuilder
    {
        return new MarketplaceClientBuilder($marketplace);
    }

    /**
     * 🔧 Configure service with sync account credentials
     */
    public function withAccount(SyncAccount $account): static
    {
        $this->account = $account;
        $this->credentials = $account->getMarketplaceCredentials();
        $this->config = $this->buildConfigFromAccount($account);

        Log::info('🔗 Marketplace service configured', [
            'marketplace' => $this->getMarketplaceName(),
            'account_id' => $account->id,
            'account_name' => $account->display_name,
        ]);

        return $this;
    }

    /**
     * 🛍️ Create product operation builder
     */
    public function products(): ProductOperationBuilder
    {
        return new ProductOperationBuilder($this);
    }

    /**
     * 📦 Create order operation builder
     */
    public function orders(): OrderOperationBuilder
    {
        return new OrderOperationBuilder($this);
    }

    /**
     * 📊 Create inventory operation builder
     */
    public function inventory(): InventoryOperationBuilder
    {
        return new InventoryOperationBuilder($this);
    }

    /**
     * 🗂️ Get product repository
     */
    public function productRepository(): MarketplaceProductRepository
    {
        return new MarketplaceProductRepository($this);
    }

    /**
     * 📦 Get order repository
     */
    public function orderRepository(): MarketplaceOrderRepository
    {
        return new MarketplaceOrderRepository($this);
    }

    /**
     * 📊 Get inventory repository
     */
    public function inventoryRepository(): MarketplaceInventoryRepository
    {
        return new MarketplaceInventoryRepository($this);
    }

    /**
     * 🌐 Configure HTTP client with common settings
     */
    protected function getHttpClient(): PendingRequest
    {
        $client = Http::timeout(30)
            ->retry($this->retryAttempts, $this->retryDelay)
            ->withHeaders($this->getDefaultHeaders());

        // Add authentication headers
        $authHeaders = $this->getAuthenticationHeaders();
        if (! empty($authHeaders)) {
            $client = $client->withHeaders($authHeaders);
        }

        // Add user agent
        $client = $client->withUserAgent($this->getUserAgent());

        return $client;
    }

    /**
     * 📊 Execute request with rate limiting and error handling
     */
    protected function executeRequest(callable $requestCallback): array
    {
        $startTime = microtime(true);

        try {
            // Apply rate limiting
            $this->applyRateLimit();

            // Execute the request
            $response = $requestCallback($this->getHttpClient());

            $duration = microtime(true) - $startTime;

            // Log successful request
            $this->logRequest('success', $duration, [
                'status' => $response->status(),
                'response_size' => strlen($response->body()),
            ]);

            // Handle response
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'response' => $response,
                    'duration_ms' => round($duration * 1000, 2),
                ];
            } else {
                return $this->handleErrorResponse($response, $duration);
            }

        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;

            $this->logRequest('error', $duration, [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'duration_ms' => round($duration * 1000, 2),
            ];
        }
    }

    /**
     * ⚠️ Handle error responses from marketplace API
     */
    protected function handleErrorResponse($response, float $duration): array
    {
        $errorData = [
            'success' => false,
            'status' => $response->status(),
            'error' => $response->body(),
            'duration_ms' => round($duration * 1000, 2),
        ];

        // Try to parse JSON error response
        try {
            $errorJson = $response->json();
            $errorData['error_details'] = $errorJson;
            $errorData['error'] = $this->extractErrorMessage($errorJson);
        } catch (\Exception $e) {
            // Keep original body as error message
        }

        // Add specific handling for common HTTP status codes
        switch ($response->status()) {
            case 401:
                $errorData['error_type'] = 'authentication_failed';
                $errorData['recommendation'] = 'Check your API credentials and ensure they are valid';
                break;
            case 403:
                $errorData['error_type'] = 'authorization_failed';
                $errorData['recommendation'] = 'Insufficient permissions for this operation';
                break;
            case 429:
                $errorData['error_type'] = 'rate_limit_exceeded';
                $errorData['recommendation'] = 'Reduce request frequency or implement exponential backoff';
                break;
            case 500:
                $errorData['error_type'] = 'server_error';
                $errorData['recommendation'] = 'Marketplace API is experiencing issues, try again later';
                break;
        }

        return $errorData;
    }

    /**
     * 🔒 Apply rate limiting before making requests
     */
    protected function applyRateLimit(): void
    {
        $rateLimits = $this->getRateLimits();

        if (! empty($rateLimits['requests_per_minute'])) {
            // Simple rate limiting implementation
            // In production, use Redis or more sophisticated rate limiting
            $cacheKey = "rate_limit:{$this->getMarketplaceName()}:{$this->account?->id}";

            // This is a simplified implementation
            // Real implementation would use Redis with sliding window
            usleep(1000 * 60 / $rateLimits['requests_per_minute']); // Spread requests evenly
        }
    }

    /**
     * 📝 Log API requests for monitoring and debugging
     */
    protected function logRequest(string $status, float $duration, array $context = []): void
    {
        Log::info("🌐 {$this->getMarketplaceName()} API request", array_merge([
            'marketplace' => $this->getMarketplaceName(),
            'account_id' => $this->account?->id,
            'status' => $status,
            'duration_ms' => round($duration * 1000, 2),
        ], $context));
    }

    /**
     * 🔧 Build configuration from sync account
     */
    protected function buildConfigFromAccount(SyncAccount $account): array
    {
        $credentials = $account->credentials ?? [];
        $settings = $account->settings ?? [];

        return array_merge($credentials, $settings);
    }

    /**
     * 📱 Get user agent string
     */
    protected function getUserAgent(): string
    {
        return 'LaravelPIM/1.0 (Marketplace Integration)';
    }

    /**
     * 📋 Get default headers for all requests
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    // Abstract methods that must be implemented by subclasses
    abstract protected function getMarketplaceName(): string;

    abstract protected function getAuthenticationHeaders(): array;

    abstract protected function extractErrorMessage(array $errorResponse): string;

    abstract public function testConnection(): array;

    abstract public function getRequirements(): array;

    abstract public function getCapabilities(): array;

    abstract public function validateConfiguration(): array;

    abstract public function getRateLimits(): array;

    abstract public function getSupportedAuthMethods(): array;
}
