<?php

namespace App\Providers;

use App\Events\ProductImported;
use App\Events\ProductVariantImported;
use App\Listeners\ProcessProductImagesListener;
use App\Listeners\ProcessVariantImagesListener;
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
        // Register StackedListBuilder in container
        $this->app->bind('stacked-list', function () {
            return new \App\StackedList\StackedListBuilder();
        });
        
        // Register current Livewire component for easy access in StackedList classes
        $this->app->bind('stacked-list.component', function () {
            return app('livewire')->current();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->registerEventListeners();
        $this->configureUrlGeneration();
    }

    /**
     * Configure rate limiting for various features.
     */
    protected function configureRateLimiting(): void
    {
        // Rate limit for image processing jobs
        RateLimiter::for('image-processing', function ($job) {
            return Limit::perMinute(30); // Process max 30 images per minute
        });

        // General API rate limiting
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Register event listeners.
     */
    protected function registerEventListeners(): void
    {
        // Register event listeners
        Event::listen(
            ProductImported::class,
            ProcessProductImagesListener::class
        );
        
        // Use the enhanced Media Library listener for better image processing
        Event::listen(
            ProductVariantImported::class,
            ProcessVariantImagesWithMediaLibraryListener::class
        );
    }

    /**
     * Configure URL generation for HTTPS.
     */
    protected function configureUrlGeneration(): void
    {
        // Force HTTPS URLs when running on HTTPS (like products.test with Valet/Herd)
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }

}
