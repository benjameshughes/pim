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
        $this->registerCustomNavigation();
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

    /**
     * Register custom navigation items.
     * 
     * This demonstrates the new Navigation system that works alongside ResourceManager.
     * Gradually migrate web.php routes here for unified navigation management.
     */
    protected function registerCustomNavigation(): void
    {
        // Import/Export Navigation Group
        \App\Atom\Navigation\Navigation::make()
            ->label('Import Data')
            ->route('import')
            ->icon('upload')
            ->group('Data Management')
            ->sort(10)
            ->register();

        \App\Atom\Navigation\Navigation::make()
            ->label('Import Data v2')
            ->route('import.v2')
            ->icon('upload')
            ->group('Data Management')
            ->sort(11)
            ->register();

        // Archive
        \App\Atom\Navigation\Navigation::make()
            ->label('Archive')
            ->route('archive')
            ->icon('archive')
            ->group('Data Management')
            ->sort(20)
            ->register();

        // Example external links
        \App\Atom\Navigation\Navigation::external('Documentation', 'https://laravel.com/docs')
            ->group('External Links')
            ->sort(100)
            ->register();

        // Dashboard (could be migrated from web.php)
        \App\Atom\Navigation\Navigation::make()
            ->label('Dashboard')
            ->route('dashboard')
            ->icon('chart-bar')
            ->sort(1)
            ->register();

        // PIM System Navigation Group
        \App\Atom\Navigation\Navigation::make()
            ->label('Barcodes')
            ->route('barcodes.index')
            ->group('PIM System')
            ->icon('scan-barcode')
            ->sort(10)
            ->register();

        \App\Atom\Navigation\Navigation::make()
            ->label('Pricing Manager')
            ->route('pricing.index')
            ->group('PIM System')
            ->icon('dollar-sign')
            ->sort(11)
            ->register();

        \App\Atom\Navigation\Navigation::make()
            ->label('Image Manager')
            ->route('images.index')
            ->group('PIM System')
            ->icon('image')
            ->sort(12)
            ->register();

        // Operations Navigation Group
        \App\Atom\Navigation\Navigation::make()
            ->label('Bulk Operations')
            ->route('operations.bulk')
            ->group('Operations')
            ->icon('settings')
            ->sort(10)
            ->register();

        // Marketplace Sync Navigation Group
        \App\Atom\Navigation\Navigation::make()
            ->label('Mirakl Sync')
            ->route('sync.mirakl')
            ->group('Marketplace Sync')
            ->icon('refresh-cw')
            ->sort(10)
            ->register();

        \App\Atom\Navigation\Navigation::make()
            ->label('Shopify Sync')
            ->route('sync.shopify')
            ->group('Marketplace Sync')
            ->icon('shopping-bag')
            ->sort(11)
            ->register();

        \App\Atom\Navigation\Navigation::make()
            ->label('eBay Sync')
            ->route('sync.ebay')
            ->group('Marketplace Sync')
            ->icon('globe')
            ->sort(12)
            ->register();
    }
}
