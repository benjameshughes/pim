<?php

namespace App\Services\Attributes\Facades;

use App\Services\Attributes\AttributesManager;
use Illuminate\Support\Facades\Facade;

/**
 * Attributes Facade
 *
 * Fluent entry to the attribute system.
 * Usage: Attributes::for($model)->set('brand','Nike')->validate()->save();
 */
class Attributes extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AttributesManager::class;
    }
}

