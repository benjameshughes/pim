<?php

namespace App\ValueObjects;

/**
 * 🏷️ MARKETPLACE TEMPLATE VALUE OBJECT
 *
 * Immutable value object representing a marketplace configuration template.
 * Follows Laravel best practices with readonly properties for type safety.
 */
readonly class MarketplaceTemplate
{
    public function __construct(
        public string $type,
        public string $name,
        public string $description,
        public array $requiredFields,
        public array $validationRules,
        public array $connectionTestEndpoints,
        public array $supportedOperators = [],
        public ?string $documentationUrl = null,
        public ?string $logoUrl = null,
        public array $defaultSettings = []
    ) {}

    /**
     * 🔍 CHECK IF MARKETPLACE SUPPORTS OPERATORS
     */
    public function hasOperators(): bool
    {
        return ! empty($this->supportedOperators);
    }

    /**
     * 📋 GET VALIDATION RULES FOR SPECIFIC OPERATOR
     */
    public function getOperatorValidationRules(string $operator): array
    {
        if (! $this->hasOperators()) {
            return $this->validationRules;
        }

        return array_merge(
            $this->validationRules,
            $this->supportedOperators[$operator]['validation_rules'] ?? []
        );
    }

    /**
     * 🔧 GET REQUIRED FIELDS FOR SPECIFIC OPERATOR
     */
    public function getOperatorRequiredFields(string $operator): array
    {
        if (! $this->hasOperators()) {
            return $this->requiredFields;
        }

        return array_merge(
            $this->requiredFields,
            $this->supportedOperators[$operator]['required_fields'] ?? []
        );
    }

    /**
     * 🎨 GET DISPLAY NAME FOR OPERATOR
     */
    public function getOperatorDisplayName(string $operator): string
    {
        return $this->supportedOperators[$operator]['display_name'] ?? ucfirst($operator);
    }

    /**
     * 📋 GET SUPPORTED OPERATORS LIST
     */
    public function getSupportedOperators(): array
    {
        return array_keys($this->supportedOperators);
    }

    /**
     * 📄 TO ARRAY FOR SERIALIZATION
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'required_fields' => $this->requiredFields,
            'validation_rules' => $this->validationRules,
            'connection_test_endpoints' => $this->connectionTestEndpoints,
            'supported_operators' => $this->supportedOperators,
            'documentation_url' => $this->documentationUrl,
            'logo_url' => $this->logoUrl,
            'default_settings' => $this->defaultSettings,
            'has_operators' => $this->hasOperators(),
        ];
    }
}
