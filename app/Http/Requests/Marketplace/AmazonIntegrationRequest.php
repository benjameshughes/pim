<?php

namespace App\Http\Requests\Marketplace;

/**
 * 📦 AMAZON INTEGRATION REQUEST
 *
 * Form validation for Amazon SP-API integrations.
 * Validates seller credentials, marketplace regions, and API access keys.
 */
class AmazonIntegrationRequest extends BaseMarketplaceRequest
{
    /**
     * 📋 GET VALIDATION RULES
     */
    public function rules(): array
    {
        $baseRules = [
            'marketplace_type' => ['required', 'in:amazon'],
            'display_name' => ['required', 'string', 'max:255'],
            'credentials' => ['required', 'array'],
            'credentials.seller_id' => ['required', 'string'],
            'credentials.marketplace_id' => ['required', 'string'],
            'credentials.access_key' => ['required', 'string'],
            'credentials.secret_key' => ['required', 'string'],
            'credentials.region' => ['required', 'in:NA,EU,FE'],
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
            'credentials.seller_id.required' => 'Amazon Seller ID is required.',
            'credentials.marketplace_id.required' => 'Amazon Marketplace ID is required.',
            'credentials.access_key.required' => 'AWS Access Key ID is required.',
            'credentials.secret_key.required' => 'AWS Secret Access Key is required.',
            'credentials.region.required' => 'Amazon region is required.',
            'credentials.region.in' => 'Region must be one of: NA (North America), EU (Europe), FE (Far East).',
        ]);
    }

    /**
     * 🏷️ CUSTOM ATTRIBUTE NAMES
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'credentials.seller_id' => 'seller ID',
            'credentials.marketplace_id' => 'marketplace ID',
            'credentials.access_key' => 'access key',
            'credentials.secret_key' => 'secret key',
            'credentials.region' => 'region',
        ]);
    }

    /**
     * 🔧 PREPARE FOR VALIDATION
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // Normalize region to uppercase
        if ($this->has('credentials.region')) {
            $this->merge([
                'credentials' => array_merge($this->input('credentials', []), [
                    'region' => strtoupper($this->input('credentials.region')),
                ]),
            ]);
        }

        // Set default region if not provided
        if ($this->has('credentials') && ! $this->has('credentials.region')) {
            $this->merge([
                'credentials' => array_merge($this->input('credentials', []), [
                    'region' => 'EU',
                ]),
            ]);
        }
    }

    /**
     * 🌍 GET REGION DISPLAY NAMES
     */
    public function getRegionDisplayNames(): array
    {
        return [
            'NA' => 'North America (US, CA, MX)',
            'EU' => 'Europe (UK, DE, FR, IT, ES)',
            'FE' => 'Far East (JP, AU, SG, IN)',
        ];
    }

    /**
     * 🏪 GET MARKETPLACE IDS BY REGION
     */
    public function getMarketplaceIdsByRegion(): array
    {
        return [
            'NA' => [
                'ATVPDKIKX0DER' => 'Amazon.com (US)',
                'A2EUQ1WTGCTBG2' => 'Amazon.ca (Canada)',
                'A1AM78C64UM0Y8' => 'Amazon.com.mx (Mexico)',
            ],
            'EU' => [
                'A1F83G8C2ARO7P' => 'Amazon.co.uk (UK)',
                'A1PA6795UKMFR9' => 'Amazon.de (Germany)',
                'A13V1IB3VIYZZH' => 'Amazon.fr (France)',
                'APJ6JRA9NG5V4' => 'Amazon.it (Italy)',
                'A1RKKUPIHCS9HS' => 'Amazon.es (Spain)',
            ],
            'FE' => [
                'A1VC38T7YXB528' => 'Amazon.co.jp (Japan)',
                'A39IBJ37TRP1C6' => 'Amazon.com.au (Australia)',
                'A17E79C6D8DWNP' => 'Amazon.sg (Singapore)',
                'A21TJRUUN4KGV' => 'Amazon.in (India)',
            ],
        ];
    }
}
