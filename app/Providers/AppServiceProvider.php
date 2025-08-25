<?php

namespace App\Providers;

use App\Events\ProductImported;
use App\Events\ProductVariantImported;
use App\Listeners\ProcessProductImagesListener;
use App\Listeners\ProcessVariantImagesWithMediaLibraryListener;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // TEMPORARILY DISABLED ALL BOOTSTRAP CODE FOR PRODUCTION DEBUGGING
        // $this->configureRateLimiting();
        // $this->registerEventListeners();  
        // $this->configureUrlGeneration();
    }
}
