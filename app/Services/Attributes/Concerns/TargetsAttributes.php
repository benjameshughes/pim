<?php

namespace App\Services\Attributes\Concerns;

use App\Models\AttributeDefinition;

trait TargetsAttributes
{
    /** Currently targeted keys (optional for bulk ops). */
    protected array $targetKeys = [];

    public function key(string $key): self
    {
        $this->targetKeys = [$key];
        return $this;
    }

    public function keys(array $keys): self
    {
        $this->targetKeys = array_values(array_unique($keys));
        return $this;
    }

    public function group(string $group): self
    {
        // Expand keys by group from definitions
        $keys = AttributeDefinition::active()->where('group', $group)->pluck('key')->all();
        $this->targetKeys = $keys;
        return $this;
    }
}