<?php

namespace App\Services\Attributes\Actions;

use App\Services\Attributes\Exceptions\AttributeValidationException;
use Illuminate\Support\Facades\DB;

class SetManyAttributesAction
{
    /**
     * Set many attributes atomically; throws on first failure.
     * @param object $model
     * @param array<string,mixed> $map
     * @param array $options
     */
    public function execute(object $model, array $map, array $options = []): void
    {
        $single = app(SetAttributeAction::class);
        // Group validation first to surface all errors together
        $errors = [];
        foreach ($map as $key => $value) {
            try {
                // will validate
                $single->execute($model, $key, $value, $options);
            } catch (AttributeValidationException $e) {
                $errors = array_merge($errors, $e->errors());
            } catch (\Throwable $e) {
                // propagate non-validation immediately
                throw $e;
            }
        }
        if (!empty($errors)) {
            throw new AttributeValidationException($errors);
        }
    }
}

