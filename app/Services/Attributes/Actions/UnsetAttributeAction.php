<?php

namespace App\Services\Attributes\Actions;

class UnsetAttributeAction
{
    /**
     * Unset (null) an attribute value.
     */
    public function execute(object $model, string $key, array $options = []): void
    {
        app(SetAttributeAction::class)->execute($model, $key, null, $options);
    }
}

