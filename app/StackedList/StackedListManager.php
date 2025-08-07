<?php

namespace App\StackedList;

use Illuminate\Foundation\Application;

class StackedListManager
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Create a new StackedListBuilder instance.
     */
    public function make(): StackedListBuilder
    {
        return new StackedListBuilder();
    }

    /**
     * Forward calls to a new builder instance.
     */
    public function __call($method, $parameters)
    {
        return $this->make()->{$method}(...$parameters);
    }
}