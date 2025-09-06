<?php

namespace App\Services\Attributes\Actions;

use App\Models\AttributeDefinition;
use App\Services\Attributes\Exceptions\AttributeOperationException;
use App\Services\Attributes\Exceptions\AttributeValidationException;
use App\Services\Attributes\Exceptions\UnknownAttributeException;

class SetAttributeAction
{
    /**
     * Set a single attribute value on an attributable model.
     * @param object $model Attributable model using HasAttributesTrait
     * @param string $key Attribute key
     * @param mixed $value Value to assign
     * @param array $options ['source' => string, 'metadata' => array]
     * @return void
     * @throws UnknownAttributeException|AttributeValidationException|AttributeOperationException
     */
    public function execute(object $model, string $key, $value, array $options = []): void
    {
        $definition = AttributeDefinition::findByKey($key);
        if (! $definition) {
            throw new UnknownAttributeException("Attribute definition '{$key}' not found");
        }

        // Validate value using definition
        $validation = $definition->validateValue($value);
        if (!($validation['valid'] ?? false)) {
            $errors = [$key => $validation['errors'] ?? ['Invalid value']];
            throw new AttributeValidationException($errors);
        }

        // Write through model helper
        if (! method_exists($model, 'setTypedAttributeValue')) {
            throw new AttributeOperationException('Model does not support attributes');
        }

        $ok = $model->setTypedAttributeValue($key, $value, [
            'source' => $options['source'] ?? 'manual',
            'metadata' => $options['metadata'] ?? null,
        ]);

        if (! $ok) {
            throw new AttributeOperationException("Failed to set attribute '{$key}'");
        }
    }
}

