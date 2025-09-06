<?php

namespace App\Services\Attributes;

use App\Services\Attributes\AttributeBuilder;

class AttributesManager
{
    /**
     * Start a fluent attribute builder for an attributable model.
     */
    public function for(object $model): AttributeBuilder
    {
        return new AttributeBuilder($model);
    }
}

