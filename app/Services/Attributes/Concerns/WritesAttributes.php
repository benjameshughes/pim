<?php

namespace App\Services\Attributes\Concerns;

trait WritesAttributes
{
    public function value($value): self
    {
        // Set value for targeted key(s)
        if (count($this->targetKeys) === 1) {
            $key = $this->targetKeys[0];
            app(\App\Services\Attributes\Actions\SetAttributeAction::class)
                ->execute($this->model, $key, $value, $this->writeOptions());

            if ($this->shouldLog) {
                $this->logActivity('attribute_set', [
                    'attribute_key' => $key,
                    'attribute_value' => $value,
                ]);
            }
        } elseif (count($this->targetKeys) > 1) {
            // Bulk set - value should be array matching keys
            $values = is_array($value) ? $value : array_fill(0, count($this->targetKeys), $value);
            $map = array_combine($this->targetKeys, $values);
            
            app(\App\Services\Attributes\Actions\SetManyAttributesAction::class)
                ->execute($this->model, $map, $this->writeOptions());

            if ($this->shouldLog) {
                $this->logActivity('attributes_set_many', [
                    'attribute_keys' => $this->targetKeys,
                    'attributes_count' => count($this->targetKeys),
                ]);
            }
        }

        return $this;
    }

    public function unset(string $key = null): self
    {
        // If no key provided, use targeted keys
        $keysToUnset = $key ? [$key] : $this->targetKeys;
        
        foreach ($keysToUnset as $keyToUnset) {
            app(\App\Services\Attributes\Actions\UnsetAttributeAction::class)
                ->execute($this->model, $keyToUnset, $this->writeOptions());

            if ($this->shouldLog) {
                $this->logActivity('attribute_unset', [
                    'attribute_key' => $keyToUnset,
                ]);
            }
        }

        return $this;
    }

    /** Build write options for actions from source. */
    protected function writeOptions(): array
    {
        return [
            'source' => $this->source ?? 'manual',
        ];
    }
}