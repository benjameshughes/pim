<?php

namespace App\ValueObjects;

/**
 * 🧪 CONNECTION TEST RESULT VALUE OBJECT
 *
 * Immutable value object representing the result of a marketplace connection test.
 * Provides structured feedback with recommendations for failed connections.
 */
readonly class ConnectionTestResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public array $details = [],
        public ?array $recommendations = null,
        public ?int $responseTime = null,
        public ?string $endpoint = null,
        public ?int $statusCode = null
    ) {}

    /**
     * 🎯 CREATE SUCCESSFUL RESULT
     */
    public static function success(
        string $message = 'Connection successful',
        array $details = [],
        ?int $responseTime = null,
        ?string $endpoint = null
    ): self {
        return new self(
            success: true,
            message: $message,
            details: $details,
            responseTime: $responseTime,
            endpoint: $endpoint,
            statusCode: 200
        );
    }

    /**
     * ❌ CREATE FAILED RESULT
     */
    public static function failure(
        string $message,
        array $details = [],
        ?array $recommendations = null,
        ?int $statusCode = null,
        ?string $endpoint = null
    ): self {
        return new self(
            success: false,
            message: $message,
            details: $details,
            recommendations: $recommendations ?? self::getDefaultRecommendations(),
            endpoint: $endpoint,
            statusCode: $statusCode
        );
    }

    /**
     * 💡 GET DEFAULT RECOMMENDATIONS FOR FAILED CONNECTIONS
     */
    private static function getDefaultRecommendations(): array
    {
        return [
            'Verify your API credentials are correct',
            'Check that your account has the required permissions',
            'Ensure your network allows outbound connections',
            'Confirm the API endpoint URL is correct',
            'Check if there are any API rate limits in effect',
        ];
    }

    /**
     * 🕒 GET FORMATTED RESPONSE TIME
     */
    public function getFormattedResponseTime(): ?string
    {
        if ($this->responseTime === null) {
            return null;
        }

        return $this->responseTime.'ms';
    }

    /**
     * 🎨 GET STATUS COLOR FOR UI
     */
    public function getStatusColor(): string
    {
        return $this->success ? 'green' : 'red';
    }

    /**
     * 📊 GET STATUS ICON FOR UI
     */
    public function getStatusIcon(): string
    {
        return $this->success ? 'check-circle' : 'x-circle';
    }

    /**
     * 📄 TO ARRAY FOR SERIALIZATION
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'details' => $this->details,
            'recommendations' => $this->recommendations,
            'response_time' => $this->responseTime,
            'formatted_response_time' => $this->getFormattedResponseTime(),
            'endpoint' => $this->endpoint,
            'status_code' => $this->statusCode,
            'status_color' => $this->getStatusColor(),
            'status_icon' => $this->getStatusIcon(),
        ];
    }
}
