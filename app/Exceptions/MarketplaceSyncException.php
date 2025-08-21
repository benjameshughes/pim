<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * 🚨 MARKETPLACE SYNC EXCEPTION
 *
 * Custom exception for marketplace synchronization errors.
 * Provides user-friendly error messages and recovery suggestions.
 */
class MarketplaceSyncException extends Exception
{
    protected string $userMessage;

    /** @var array<string, mixed> */
    protected array $context;

    /** @var array<int, string> */
    protected array $recoverySuggestions;

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $recoverySuggestions
     */
    public function __construct(
        string $message = '',
        string $userMessage = '',
        array $context = [],
        array $recoverySuggestions = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->userMessage = $userMessage ?: $this->getDefaultUserMessage();
        $this->context = $context;
        $this->recoverySuggestions = $recoverySuggestions ?: $this->getDefaultRecoverySuggestions();
    }

    /**
     * Create exception for API connection failures
     *
     * @param  array<string, mixed>  $context
     */
    public static function connectionFailed(string $marketplace, string $details = '', array $context = []): self
    {
        return new self(
            message: "Failed to connect to {$marketplace} API: {$details}",
            userMessage: "Unable to connect to {$marketplace}. Please check your connection and credentials.",
            context: array_merge($context, ['marketplace' => $marketplace]),
            recoverySuggestions: [
                'Check your internet connection',
                'Verify your API credentials are correct',
                'Confirm the marketplace service is operational',
                'Try again in a few minutes',
            ]
        );
    }

    /**
     * Create exception for authentication failures
     *
     * @param  array<string, mixed>  $context
     */
    public static function authenticationFailed(string $marketplace, array $context = []): self
    {
        return new self(
            message: "Authentication failed for {$marketplace}",
            userMessage: "{$marketplace} rejected your credentials. Please check your API settings.",
            context: array_merge($context, ['marketplace' => $marketplace]),
            recoverySuggestions: [
                'Verify your API credentials are correct',
                'Check if your API access token has expired',
                'Confirm your account has necessary permissions',
                'Contact support if credentials are correct',
            ]
        );
    }

    /**
     * Create exception for rate limiting
     *
     * @param  array<string, mixed>  $context
     */
    public static function rateLimited(string $marketplace, int $retryAfter = 0, array $context = []): self
    {
        $message = $retryAfter > 0
            ? "Rate limited by {$marketplace}. Retry after {$retryAfter} seconds."
            : "Rate limited by {$marketplace}. Please wait before retrying.";

        return new self(
            message: $message,
            userMessage: "{$marketplace} is temporarily limiting requests. The sync will retry automatically.",
            context: array_merge($context, [
                'marketplace' => $marketplace,
                'retry_after' => $retryAfter,
            ]),
            recoverySuggestions: [
                'Wait for the rate limit to reset',
                'Reduce sync frequency if this happens often',
                'Check if multiple syncs are running simultaneously',
            ]
        );
    }

    /**
     * Create exception for data processing errors
     *
     * @param  array<string, mixed>  $context
     */
    public static function dataProcessingFailed(string $details, array $context = []): self
    {
        return new self(
            message: "Data processing failed: {$details}",
            userMessage: 'Error processing marketplace data. Some items may not have been synced.',
            context: $context,
            recoverySuggestions: [
                'Check the marketplace data format',
                'Try syncing again to process remaining items',
                'Contact support if this error persists',
            ]
        );
    }

    /**
     * Create exception for unsupported marketplace
     */
    public static function unsupportedMarketplace(string $marketplace): self
    {
        return new self(
            message: "Unsupported marketplace: {$marketplace}",
            userMessage: 'This marketplace is not yet supported for synchronization.',
            context: ['marketplace' => $marketplace],
            recoverySuggestions: [
                'Use a supported marketplace (Shopify, eBay, Amazon)',
                'Contact support to request this marketplace integration',
            ]
        );
    }

    /**
     * Get default user-friendly message
     */
    protected function getDefaultUserMessage(): string
    {
        return 'An error occurred during marketplace synchronization. Please try again.';
    }

    /**
     * Get default recovery suggestions
     *
     * @return array<int, string>
     */
    protected function getDefaultRecoverySuggestions(): array
    {
        return [
            'Try the sync operation again',
            'Check your internet connection',
            'Verify your marketplace account credentials',
            'Contact support if the problem persists',
        ];
    }

    /**
     * Get user-friendly message
     */
    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    /**
     * Get additional context
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get recovery suggestions
     *
     * @return array<int, string>
     */
    public function getRecoverySuggestions(): array
    {
        return $this->recoverySuggestions;
    }

    /**
     * Render the exception for HTTP responses
     */
    public function render(Request $request): Response|JsonResponse
    {
        // Log the technical details
        Log::error('Marketplace sync exception', [
            'message' => $this->getMessage(),
            'context' => $this->getContext(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => 'marketplace_sync_failed',
                'message' => $this->getUserMessage(),
                'suggestions' => $this->getRecoverySuggestions(),
                'context' => $this->getContext(),
            ], 422);
        }

        // For non-JSON requests, could redirect back with error
        return response()->view('errors.marketplace-sync', [
            'exception' => $this,
            'message' => $this->getUserMessage(),
            'suggestions' => $this->getRecoverySuggestions(),
        ], 422);
    }

    /**
     * Report the exception (for logging, notifications, etc.)
     */
    public function report(): void
    {
        Log::error('Marketplace sync exception reported', [
            'message' => $this->getMessage(),
            'user_message' => $this->getUserMessage(),
            'context' => $this->getContext(),
            'suggestions' => $this->getRecoverySuggestions(),
        ]);
    }
}
