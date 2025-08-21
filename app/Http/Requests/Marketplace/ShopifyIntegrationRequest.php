<?php

namespace App\Http\Requests\Marketplace;

/**
 * 🛍️ SHOPIFY INTEGRATION REQUEST
 *
 * Form validation for Shopify marketplace integrations.
 * Validates store URL, access token, and API version.
 */
class ShopifyIntegrationRequest extends BaseMarketplaceRequest
{
    /**
     * 📋 GET VALIDATION RULES
     */
    public function rules(): array
    {
        $baseRules = [
            'marketplace_type' => ['required', 'in:shopify'],
            'display_name' => ['required', 'string', 'max:255'],
            'credentials' => ['required', 'array'],
            'credentials.store_url' => ['required', 'url', 'regex:/\.myshopify\.com$/'],
            'credentials.access_token' => ['required', 'string', 'min:32'],
            'credentials.api_version' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ];

        // Add marketplace-specific validation rules
        $marketplaceRules = $this->getMarketplaceValidationRules();

        return array_merge($baseRules, $marketplaceRules);
    }

    /**
     * 📝 CUSTOM VALIDATION MESSAGES
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'credentials.store_url.required' => 'Store URL is required for Shopify integration.',
            'credentials.store_url.regex' => 'Store URL must be a valid Shopify domain (ending with .myshopify.com).',
            'credentials.access_token.required' => 'Access token is required for Shopify API access.',
            'credentials.access_token.min' => 'Access token must be at least 32 characters long.',
            'credentials.api_version.regex' => 'API version must be in YYYY-MM format (e.g., 2024-07).',
        ]);
    }

    /**
     * 🏷️ CUSTOM ATTRIBUTE NAMES
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'credentials.store_url' => 'store URL',
            'credentials.access_token' => 'access token',
            'credentials.api_version' => 'API version',
        ]);
    }

    /**
     * 🔧 PREPARE FOR VALIDATION
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // Normalize store URL to remove protocol and trailing slashes
        if ($this->has('credentials.store_url')) {
            $storeUrl = $this->input('credentials.store_url');
            $storeUrl = preg_replace('/^https?:\/\//', '', $storeUrl);
            $storeUrl = rtrim($storeUrl, '/');

            $this->merge([
                'credentials' => array_merge($this->input('credentials', []), [
                    'store_url' => 'https://'.$storeUrl,
                ]),
            ]);
        }

        // Set default API version if not provided
        if ($this->has('credentials') && ! $this->has('credentials.api_version')) {
            $this->merge([
                'credentials' => array_merge($this->input('credentials', []), [
                    'api_version' => '2024-07',
                ]),
            ]);
        }
    }
}
