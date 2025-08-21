<?php

namespace App\Http\Requests\Marketplace;

/**
 * 📦 EBAY INTEGRATION REQUEST
 *
 * Form validation for eBay marketplace integrations.
 * Validates client credentials, dev ID, and environment settings.
 */
class EbayIntegrationRequest extends BaseMarketplaceRequest
{
    /**
     * 📋 GET VALIDATION RULES
     */
    public function rules(): array
    {
        $baseRules = [
            'marketplace_type' => ['required', 'in:ebay'],
            'display_name' => ['required', 'string', 'max:255'],
            'credentials' => ['required', 'array'],
            'credentials.environment' => ['required', 'in:SANDBOX,PRODUCTION'],
            'credentials.client_id' => ['required', 'string'],
            'credentials.client_secret' => ['required', 'string'],
            'credentials.dev_id' => ['required', 'string'],
            'credentials.redirect_uri' => ['nullable', 'url'],
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
            'credentials.environment.required' => 'Environment selection is required (SANDBOX or PRODUCTION).',
            'credentials.environment.in' => 'Environment must be either SANDBOX or PRODUCTION.',
            'credentials.client_id.required' => 'eBay Client ID (App ID) is required.',
            'credentials.client_secret.required' => 'eBay Client Secret (Cert ID) is required.',
            'credentials.dev_id.required' => 'eBay Developer ID is required.',
            'credentials.redirect_uri.url' => 'Redirect URI must be a valid URL.',
        ]);
    }

    /**
     * 🏷️ CUSTOM ATTRIBUTE NAMES
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'credentials.environment' => 'environment',
            'credentials.client_id' => 'client ID',
            'credentials.client_secret' => 'client secret',
            'credentials.dev_id' => 'developer ID',
            'credentials.redirect_uri' => 'redirect URI',
        ]);
    }

    /**
     * 🔧 PREPARE FOR VALIDATION
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // Normalize environment to uppercase
        if ($this->has('credentials.environment')) {
            $this->merge([
                'credentials' => array_merge($this->input('credentials', []), [
                    'environment' => strtoupper($this->input('credentials.environment')),
                ]),
            ]);
        }

        // Set default environment if not provided
        if ($this->has('credentials') && ! $this->has('credentials.environment')) {
            $this->merge([
                'credentials' => array_merge($this->input('credentials', []), [
                    'environment' => 'SANDBOX',
                ]),
            ]);
        }
    }
}
