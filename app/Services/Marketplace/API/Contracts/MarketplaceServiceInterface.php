<?php

namespace App\Services\Marketplace\API\Contracts;

use App\Models\SyncAccount;
use App\Services\Marketplace\API\Builders\MarketplaceClientBuilder;

/**
 * 🎯 MARKETPLACE SERVICE INTERFACE
 *
 * Defines the contract for all marketplace service implementations.
 * Ensures consistent API across all marketplace integrations.
 */
interface MarketplaceServiceInterface
{
    /**
     * 🏗️ Create a marketplace client builder
     */
    public static function for(string $marketplace): MarketplaceClientBuilder;

    /**
     * 🔧 Configure service with sync account credentials
     */
    public function withAccount(SyncAccount $account): static;

    /**
     * 🧪 Test connection to marketplace API
     */
    public function testConnection(): array;

    /**
     * 📋 Get marketplace-specific requirements and constraints
     */
    public function getRequirements(): array;

    /**
     * 📊 Get marketplace capabilities and features
     */
    public function getCapabilities(): array;

    /**
     * 🔍 Validate configuration and credentials
     */
    public function validateConfiguration(): array;

    /**
     * 📈 Get API rate limit information
     */
    public function getRateLimits(): array;

    /**
     * 🛡️ Get supported authentication methods
     */
    public function getSupportedAuthMethods(): array;
}
