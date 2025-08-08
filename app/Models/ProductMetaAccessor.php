<?php

namespace App\Models;

class ProductMetaAccessor
{
    protected $product;

    protected $metadata;

    public function __construct(Product $product)
    {
        $this->product = $product;
        $this->metadata = $product->metadata->pluck('value', 'key');
    }

    public function __get($key)
    {
        return $this->metadata->get($key);
    }

    public function __set($key, $value)
    {
        $this->product->metadata()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        $this->metadata[$key] = $value;
    }

    public function __isset($key)
    {
        return $this->metadata->has($key);
    }

    public function all()
    {
        return $this->metadata->toArray();
    }
}
