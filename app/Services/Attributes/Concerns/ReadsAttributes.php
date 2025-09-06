<?php

namespace App\Services\Attributes\Concerns;

trait ReadsAttributes
{
    /** Get a single typed value. */
    public function get(string $key)
    {
        if (method_exists($this->model, 'getTypedAttributeValue')) {
            return $this->model->getTypedAttributeValue($key);
        }
        return null;
    }

    /** Get all typed attributes as key => value. */
    public function all(): array
    {
        if (method_exists($this->model, 'getTypedAttributesArray')) {
            return $this->model->getTypedAttributesArray();
        }
        return [];
    }

    /** Grouped attributes for display. */
    public function byGroup(): array
    {
        if (method_exists($this->model, 'getAttributesByGroup')) {
            return $this->model->getAttributesByGroup()->toArray();
        }
        return [];
    }
}