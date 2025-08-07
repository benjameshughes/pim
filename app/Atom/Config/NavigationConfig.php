<?php

namespace App\Atom\Config;

use App\Atom\Navigation\Navigation;

/**
 * Navigation Configuration
 * 
 * Define your application's navigation structure here.
 * This keeps the AppServiceProvider clean and makes navigation
 * easily configurable and maintainable.
 */
class NavigationConfig
{
    /**
     * Register all application navigation items.
     */
    public static function register(): void
    {
        static::registerDataManagement();
        static::registerDashboard();
        static::registerPimSystem();
        static::registerOperations();
        static::registerMarketplaceSync();
        static::registerExternalLinks();
    }
    
    /**
     * Data Management navigation group.
     */
    protected static function registerDataManagement(): void
    {
        Navigation::make()
            ->label('Import Data')
            ->route('import')
            ->icon('upload')
            ->group('Data Management')
            ->sort(10)
            ->register();

        Navigation::make()
            ->label('Import Data v2')
            ->route('import.v2')
            ->icon('upload')
            ->group('Data Management')
            ->sort(11)
            ->register();

        Navigation::make()
            ->label('Archive')
            ->route('archive')
            ->icon('archive')
            ->group('Data Management')
            ->sort(20)
            ->register();
    }
    
    /**
     * Dashboard navigation.
     */
    protected static function registerDashboard(): void
    {
        Navigation::make()
            ->label('Dashboard')
            ->route('dashboard')
            ->icon('chart-bar')
            ->sort(1)
            ->register();
    }
    
    /**
     * PIM System navigation group.
     */
    protected static function registerPimSystem(): void
    {
        Navigation::make()
            ->label('Barcodes')
            ->route('barcodes.index')
            ->group('PIM System')
            ->icon('scan-barcode')
            ->sort(10)
            ->register();

        Navigation::make()
            ->label('Pricing Manager')
            ->route('pricing.index')
            ->group('PIM System')
            ->icon('dollar-sign')
            ->sort(11)
            ->register();

        Navigation::make()
            ->label('Image Manager')
            ->route('images.index')
            ->group('PIM System')
            ->icon('image')
            ->sort(12)
            ->register();
    }
    
    /**
     * Operations navigation group.
     */
    protected static function registerOperations(): void
    {
        Navigation::make()
            ->label('Bulk Operations')
            ->route('operations.bulk')
            ->group('Operations')
            ->icon('settings')
            ->sort(10)
            ->register();
    }
    
    /**
     * Marketplace Sync navigation group.
     */
    protected static function registerMarketplaceSync(): void
    {
        Navigation::make()
            ->label('Mirakl Sync')
            ->route('sync.mirakl')
            ->group('Marketplace Sync')
            ->icon('refresh-cw')
            ->sort(10)
            ->register();

        Navigation::make()
            ->label('Shopify Sync')
            ->route('sync.shopify')
            ->group('Marketplace Sync')
            ->icon('shopping-bag')
            ->sort(11)
            ->register();

        Navigation::make()
            ->label('eBay Sync')
            ->route('sync.ebay')
            ->group('Marketplace Sync')
            ->icon('globe')
            ->sort(12)
            ->register();
    }
    
    /**
     * External links navigation group.
     */
    protected static function registerExternalLinks(): void
    {
        Navigation::external('Documentation', 'https://laravel.com/docs')
            ->group('External Links')
            ->sort(100)
            ->register();
    }
}