<?php

namespace App\Services\Pricing\Facades;

use App\Services\Pricing\PricingManager;
use Illuminate\Support\Facades\Facade;

/**
 * Pricing Facade
 *
 * Fluent, chainable API for reading and writing pricing across
 * variants/products and channels. All persistence is delegated to
 * action classes; the facade orchestrates and returns a builder.
 */
class Pricing extends Facade
{
    /**
     * Get the registered name (container binding) of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return PricingManager::class;
    }
}

