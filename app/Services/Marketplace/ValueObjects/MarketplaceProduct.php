<?php

namespace App\Services\Marketplace\ValueObjects;

/**
 * 📦 MARKETPLACE PRODUCT VALUE OBJECT
 *
 * Holds marketplace-specific transformed product data.
 * Each marketplace adapter creates this with their own data structure.
 */
class MarketplaceProduct
{
    public function __construct(
        public readonly mixed $data,
        public readonly array $metadata = []
    ) {}

    /**
     * Get the transformed data
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Get metadata about the transformation
     */
    public function getMetadata(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if the product has data
     */
    public function hasData(): bool
    {
        return ! empty($this->data);
    }
}
