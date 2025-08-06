<?php

namespace App\Providers;

use App\Events\ProductImported;
use App\Events\ProductVariantImported;
use App\Listeners\ProcessProductImagesListener;
use App\Listeners\ProcessVariantImagesListener;
use App\Listeners\ProcessVariantImagesWithMediaLibraryListener;
use Illuminate\Support\Facades\Event;
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
}
