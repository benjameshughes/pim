<?php

namespace App\ValueObjects;

/**
 * 🔐 MARKETPLACE CREDENTIALS VALUE OBJECT
 *
 * Immutable value object for handling marketplace credentials securely.
 * Provides type safety and validation for sensitive data.
 */
readonly class MarketplaceCredentials
{
    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public string $type,
        public array $credentials,
        public array $settings = [],
        public ?string $operator = null
    ) {}

    /**
     * 🔍 CHECK IF CREDENTIAL EXISTS
     */
    public function hasCredential(string $key): bool
    {
        return isset($this->credentials[$key]);
    }

    /**
     * 🔑 GET CREDENTIAL VALUE
     */
    public function getCredential(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }

    /**
     * ⚙️ GET SETTING VALUE
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * 🏷️ CHECK IF MARKETPLACE USES OPERATORS
     */
    public function hasOperator(): bool
    {
        return $this->operator !== null;
    }

    /**
     * 🏢 GET OPERATOR
     */
    public function getOperator(): ?string
    {
        return $this->operator;
    }

    /**
     * 🔒 GET MASKED CREDENTIALS FOR DISPLAY
     *
     * @return array<string, string>
     */
    public function getMaskedCredentials(): array
    {
        $masked = [];

        foreach ($this->credentials as $key => $value) {
            if (is_string($value) && strlen($value) > 4) {
                $masked[$key] = substr($value, 0, 4).str_repeat('*', strlen($value) - 4);
            } else {
                $masked[$key] = str_repeat('*', 8);
            }
        }

        return $masked;
    }

    /**
     * ✅ VALIDATE REQUIRED CREDENTIALS
     *
     * @param  array<int, string>  $requiredFields
     * @return array<int, string>
     */
    public function validateRequired(array $requiredFields): array
    {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (! $this->hasCredential($field) || empty($this->getCredential($field))) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * 📄 TO ARRAY FOR STORAGE
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'credentials' => $this->credentials,
            'settings' => $this->settings,
            'operator' => $this->operator,
        ];
    }

    /**
     * 🎨 TO DISPLAY ARRAY
     *
     * @return array<string, mixed>
     */
    public function toDisplayArray(): array
    {
        return [
            'type' => $this->type,
            'credentials' => $this->getMaskedCredentials(),
            'settings' => $this->settings,
            'operator' => $this->operator,
        ];
    }
}
