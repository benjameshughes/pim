<?php

namespace App\Services\Marketplace;

use App\ValueObjects\MarketplaceTemplate;
use Illuminate\Support\Collection;

/**
 * 🏪 MARKETPLACE REGISTRY SERVICE
 *
 * Central registry for all supported marketplace types and their configurations.
 * Follows single responsibility principle and provides type-safe templates.
 */
class MarketplaceRegistry
{
    private Collection $templates;

    public function __construct()
    {
        $this->templates = $this->buildMarketplaceTemplates();
    }

    /**
     * 📋 GET ALL AVAILABLE MARKETPLACES
     */
    public function getAvailableMarketplaces(): Collection
    {
        return $this->templates;
    }

    /**
     * 🔍 GET MARKETPLACE TEMPLATE BY TYPE
     */
    public function getMarketplaceTemplate(string $type): ?MarketplaceTemplate
    {
        return $this->templates->get($type);
    }

    /**
     * 🏷️ GET SUPPORTED OPERATORS FOR MARKETPLACE
     */
    public function getSupportedOperators(string $marketplace): array
    {
        $template = $this->getMarketplaceTemplate($marketplace);

        return $template?->supportedOperators ? array_keys($template->supportedOperators) : [];
    }

    /**
     * ✅ CHECK IF MARKETPLACE TYPE IS SUPPORTED
     */
    public function isSupported(string $type): bool
    {
        return $this->templates->has($type);
    }

    /**
     * 🔧 GET VALIDATION RULES FOR MARKETPLACE
     */
    public function getValidationRules(string $type, ?string $operator = null): array
    {
        $template = $this->getMarketplaceTemplate($type);

        if (! $template) {
            return [];
        }

        if ($operator && $template->hasOperators()) {
            return $template->getOperatorValidationRules($operator);
        }

        return $template->validationRules;
    }

    /**
     * 📝 GET REQUIRED FIELDS FOR MARKETPLACE
     */
    public function getRequiredFields(string $type, ?string $operator = null): array
    {
        $template = $this->getMarketplaceTemplate($type);

        if (! $template) {
            return [];
        }

        if ($operator && $template->hasOperators()) {
            return $template->getOperatorRequiredFields($operator);
        }

        return $template->requiredFields;
    }

    /**
     * 🏗️ BUILD MARKETPLACE TEMPLATES
     */
    private function buildMarketplaceTemplates(): Collection
    {
        return collect([
            'shopify' => new MarketplaceTemplate(
                type: 'shopify',
                name: 'Shopify',
                description: 'Connect your Shopify store for seamless product synchronization',
                requiredFields: ['store_url', 'access_token'],
                validationRules: [
                    'store_url' => ['required', 'url', 'regex:/\.myshopify\.com$/'],
                    'access_token' => ['required', 'string', 'min:32'],
                    'api_version' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
                ],
                connectionTestEndpoints: [
                    'shop' => '/admin/api/{version}/shop.json',
                    'products' => '/admin/api/{version}/products/count.json',
                ],
                documentationUrl: 'https://shopify.dev/docs/admin-api',
                logoUrl: '/images/marketplace-logos/shopify.svg',
                defaultSettings: [
                    'api_version' => '2024-07',
                    'sync_inventory' => true,
                    'sync_pricing' => true,
                ]
            ),

            'ebay' => new MarketplaceTemplate(
                type: 'ebay',
                name: 'eBay',
                description: 'Integrate with eBay marketplace using their modern REST API',
                requiredFields: ['client_id', 'client_secret', 'dev_id'],
                validationRules: [
                    'environment' => ['required', 'in:SANDBOX,PRODUCTION'],
                    'client_id' => ['required', 'string'],
                    'client_secret' => ['required', 'string'],
                    'dev_id' => ['required', 'string'],
                    'redirect_uri' => ['nullable', 'url'],
                ],
                connectionTestEndpoints: [
                    'inventory' => '/sell/inventory/v1/inventory_item',
                    'account' => '/sell/account/v1/privilege',
                ],
                documentationUrl: 'https://developer.ebay.com/api-docs/static/rest-request-components.html',
                logoUrl: '/images/marketplace-logos/ebay.svg',
                defaultSettings: [
                    'environment' => 'SANDBOX',
                    'auto_publish' => false,
                ]
            ),

            'amazon' => new MarketplaceTemplate(
                type: 'amazon',
                name: 'Amazon',
                description: 'Connect to Amazon Seller Central via SP-API',
                requiredFields: ['seller_id', 'marketplace_id', 'access_key', 'secret_key'],
                validationRules: [
                    'seller_id' => ['required', 'string'],
                    'marketplace_id' => ['required', 'string'],
                    'access_key' => ['required', 'string'],
                    'secret_key' => ['required', 'string'],
                    'region' => ['required', 'in:NA,EU,FE'],
                ],
                connectionTestEndpoints: [
                    'seller' => '/sellers/v1/marketplaceParticipations',
                    'catalog' => '/catalog/v0/items',
                ],
                documentationUrl: 'https://developer-docs.amazon.com/sp-api/',
                logoUrl: '/images/marketplace-logos/amazon.svg',
                defaultSettings: [
                    'region' => 'EU',
                    'fulfillment_method' => 'FBM',
                ]
            ),

            'mirakl' => new MarketplaceTemplate(
                type: 'mirakl',
                name: 'Mirakl',
                description: 'Connect to Mirakl-powered marketplaces with automatic operator detection',
                requiredFields: ['base_url', 'api_key'],
                validationRules: [
                    'base_url' => ['required', 'url'],
                    'api_key' => ['required', 'string', 'min:8'],
                ],
                connectionTestEndpoints: [
                    'account' => '/api/account',
                    'offers' => '/api/offers',
                ],
                documentationUrl: 'https://help.mirakl.com/hc/en-us/categories/360002444833-Technical-Documentation',
                logoUrl: '/images/marketplace-logos/mirakl.svg',
                defaultSettings: [
                    'auto_sync' => false,
                    'auto_detect_operator' => true,
                    'sync_frequency' => 'daily',
                ]
            ),
        ]);
    }

    /**
     * 🔍 SEARCH MARKETPLACES BY NAME
     */
    public function searchByName(string $query): Collection
    {
        return $this->templates->filter(function (MarketplaceTemplate $template) use ($query) {
            return stripos($template->name, $query) !== false ||
                   stripos($template->description, $query) !== false;
        });
    }

    /**
     * 📊 GET MARKETPLACE STATISTICS
     */
    public function getStatistics(): array
    {
        $total = $this->templates->count();
        $withOperators = $this->templates->filter(fn ($template) => $template->hasOperators())->count();
        $totalOperators = $this->templates->sum(fn ($template) => count($template->supportedOperators));

        return [
            'total_marketplaces' => $total,
            'marketplaces_with_operators' => $withOperators,
            'total_operators' => $totalOperators,
            'supported_types' => $this->templates->keys()->toArray(),
        ];
    }
}
