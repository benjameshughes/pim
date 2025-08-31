<?php

namespace App\Facades;

use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Facade;

class Activity extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ActivityLogger::class;
    }
}