<?php

namespace App\Http\Requests\Marketplace;

use App\Services\Marketplace\MarketplaceRegistry;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 🏪 BASE MARKETPLACE REQUEST
 *
 * Base form request class for marketplace integrations.
 * Provides common validation logic and marketplace registry access.
 */
abstract class BaseMarketplaceRequest extends FormRequest
{
    protected MarketplaceRegistry $marketplaceRegistry;

    public function __construct(MarketplaceRegistry $marketplaceRegistry)
    {
        parent::__construct();
        $this->marketplaceRegistry = $marketplaceRegistry;
    }

    /**
     * 🔐 AUTHORIZATION - Allow authenticated users
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * 🏷️ GET MARKETPLACE TYPE
     */
    protected function getMarketplaceType(): string
    {
        return $this->input('marketplace_type', '');
    }

    /**
     * 🏷️ GET MARKETPLACE OPERATOR
     */
    protected function getMarketplaceOperator(): ?string
    {
        return $this->input('marketplace_subtype');
    }

    /**
     * 📋 GET MARKETPLACE SPECIFIC VALIDATION RULES
     */
    protected function getMarketplaceValidationRules(): array
    {
        $type = $this->getMarketplaceType();
        $operator = $this->getMarketplaceOperator();

        if (! $type || ! $this->marketplaceRegistry->isSupported($type)) {
            return [];
        }

        return $this->marketplaceRegistry->getValidationRules($type, $operator);
    }

    /**
     * 🔧 GET REQUIRED FIELDS FOR MARKETPLACE
     */
    protected function getMarketplaceRequiredFields(): array
    {
        $type = $this->getMarketplaceType();
        $operator = $this->getMarketplaceOperator();

        if (! $type || ! $this->marketplaceRegistry->isSupported($type)) {
            return [];
        }

        return $this->marketplaceRegistry->getRequiredFields($type, $operator);
    }

    /**
     * 📝 GET CUSTOM VALIDATION MESSAGES
     */
    public function messages(): array
    {
        return [
            'marketplace_type.required' => 'Please select a marketplace type.',
            'marketplace_type.in' => 'The selected marketplace type is not supported.',
            'marketplace_subtype.required_if' => 'Please select an operator for this marketplace.',
            'credentials.required' => 'Marketplace credentials are required.',
            'credentials.array' => 'Credentials must be provided as a valid data structure.',
            'display_name.required' => 'Please provide a display name for this integration.',
            'display_name.string' => 'Display name must be a valid text string.',
            'display_name.max' => 'Display name cannot exceed 255 characters.',
        ];
    }

    /**
     * 🏷️ GET CUSTOM ATTRIBUTE NAMES
     */
    public function attributes(): array
    {
        return [
            'marketplace_type' => 'marketplace type',
            'marketplace_subtype' => 'marketplace operator',
            'display_name' => 'display name',
            'credentials' => 'credentials',
        ];
    }

    /**
     * 🔧 PREPARE DATA FOR VALIDATION
     */
    protected function prepareForValidation(): void
    {
        // Clean and normalize marketplace type
        if ($this->has('marketplace_type')) {
            $this->merge([
                'marketplace_type' => strtolower(trim($this->marketplace_type)),
            ]);
        }

        // Clean and normalize operator type
        if ($this->has('marketplace_subtype')) {
            $this->merge([
                'marketplace_subtype' => strtolower(trim($this->marketplace_subtype)),
            ]);
        }
    }
}
