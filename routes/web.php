<?php

use App\Livewire\Dashboard;
use App\Livewire\PIM\Products\Management\ProductIndex;
use App\Livewire\PIM\Products\Management\ProductForm;
use App\Livewire\PIM\Products\Variants\VariantIndex;
use App\Livewire\PIM\Products\Variants\VariantForm;
use App\Livewire\PIM\Products\Variants\VariantView;
use App\Livewire\PIM\Products\Variants\VariantEdit;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Livewire\DataExchange\Import\ImportData;
use App\Livewire\DataExchange\Import\ImportDataRefactored;
use App\Livewire\DataExchange\Import\BulkOperations;
use App\Livewire\PIM\Barcodes\BarcodeIndex;
use App\Livewire\PIM\Barcodes\Pool\PoolManager;
use App\Livewire\PIM\Barcodes\Pool\BarcodePoolImport;
use App\Livewire\PIM\Pricing\PricingManager;
use App\Livewire\PIM\Media\ImageManager;
use App\Livewire\PIM\Attributes\AttributeDefinitionsManager;
use App\Livewire\DataExchange\Export\ShopifyExport;
use App\Livewire\PIM\Products\Management\DeleteProduct;
use App\Livewire\PIM\Products\Variants\DeleteVariant;
use App\Livewire\Archive\DeletedProductsArchive;
use App\Livewire\DataExchange\Sync\MiraklSync;
use App\Livewire\DataExchange\Sync\ShopifySync;
use App\Livewire\DataExchange\Sync\EbaySync;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('dashboard', Dashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');

    // Barcode management routes
    Route::prefix('barcodes')->name('barcodes.')->group(function () {
        Route::get('/', BarcodeIndex::class)->name('index');
        
        // Pool management routes
        Route::prefix('pool')->name('pool.')->group(function () {
            Route::get('/', \App\Livewire\PIM\Barcodes\Pool\PoolManagerLite::class)->name('index');
            Route::get('/full', PoolManager::class)->name('full');
            Route::get('/import', BarcodePoolImport::class)->name('import');
        });
    });

    // Pricing management route
    Route::get('/pricing', PricingManager::class)->name('pricing.index');

    // Images management route
    Route::get('/images', ImageManager::class)->name('images.index');

    // Import/Export routes
    Route::get('/import', ImportData::class)->name('import');
    Route::get('/import-v2', ImportDataRefactored::class)->name('import.v2');
    Route::get('/export', function () { return 'Export Data - Coming Soon'; })->name('export');

    // Marketplace Sync routes
    Route::prefix('sync')->name('sync.')->group(function () {
        Route::get('/mirakl', MiraklSync::class)->name('mirakl');
        Route::get('/shopify', ShopifySync::class)->name('shopify');
        Route::get('/ebay', EbaySync::class)->name('ebay');
        
        // eBay OAuth Routes
        Route::prefix('ebay/oauth')->name('ebay.oauth.')->group(function () {
            Route::post('authorize', [App\Http\Controllers\EbayOAuthController::class, 'authorize'])->name('authorize');
            Route::get('callback', [App\Http\Controllers\EbayOAuthController::class, 'callback'])->name('callback');
            Route::get('accounts', [App\Http\Controllers\EbayOAuthController::class, 'accounts'])->name('accounts');
            Route::delete('accounts/{account}/revoke', [App\Http\Controllers\EbayOAuthController::class, 'revoke'])->name('revoke');
            Route::post('accounts/{account}/test', [App\Http\Controllers\EbayOAuthController::class, 'test'])->name('test');
        });
    });

    // Operations routes
    Route::prefix('operations')->name('operations.')->group(function () {
        Route::get('/bulk', \App\Livewire\Operations\BulkOperationsIndex::class)->name('bulk');
        
        Route::prefix('bulk')->name('bulk.')->group(function () {
            Route::get('/overview', \App\Livewire\Operations\BulkOperationsOverview::class)->name('overview');
            Route::get('/templates', \App\Livewire\Operations\BulkOperationsTemplates::class)->name('templates');
            Route::get('/attributes', \App\Livewire\Operations\BulkOperationsAttributes::class)->name('attributes');
            Route::get('/quality', \App\Livewire\Operations\BulkOperationsQuality::class)->name('quality');
            Route::get('/recommendations', \App\Livewire\Operations\BulkOperationsRecommendations::class)->name('recommendations');
            Route::get('/ai', \App\Livewire\Operations\BulkOperationsAi::class)->name('ai');
        });
    });

    // Attributes routes
    Route::prefix('attributes')->name('attributes.')->group(function () {
        Route::get('/definitions', AttributeDefinitionsManager::class)->name('definitions');
    });

    // Archive route
    Route::get('/archive', DeletedProductsArchive::class)->name('archive');

    // Product Management Routes
    Route::prefix('products')->name('products.')->group(function () {
        // Specific routes MUST come before wildcard routes
        Route::get('/', ProductIndex::class)->name('index');
        Route::get('create', \App\Livewire\PIM\Products\Management\ProductWizard::class)->name('create');
        
        // Variants
        Route::get('variants', VariantIndex::class)->name('variants.index');
        Route::get('variants/create', function() { return 'Variant Create - Coming Soon'; })->name('variants.create');
        
        // Variant tabs - specific routes MUST come before wildcard
        Route::get('variants/{variant}/details', VariantView::class)->name('variants.details');
        Route::get('variants/{variant}/inventory', VariantView::class)->name('variants.inventory');
        Route::get('variants/{variant}/attributes', VariantView::class)->name('variants.attributes');
        Route::get('variants/{variant}/data', VariantView::class)->name('variants.data');
        Route::get('variants/{variant}/images', VariantView::class)->name('variants.images');
        
        Route::get('variants/{variant}', VariantView::class)->name('variants.view');
        Route::get('variants/{variant}/edit', VariantEdit::class)->name('variants.edit');




        Route::get('export/shopify', ShopifyExport::class)->name('export.shopify');
        
        
        
        
        
        // Deletion routes (keep under products as they're product-specific)
        Route::get('{product}/delete', DeleteProduct::class)->name('delete');
        Route::get('variants/{variant}/delete', DeleteVariant::class)->name('variants.delete');
        
        // Product view routes - organized like bulk operations
        Route::prefix('{product}')->name('product.')->group(function () {
            Route::get('/overview', \App\Livewire\PIM\Products\Management\ProductView::class)->name('overview');
            Route::get('/variants', \App\Livewire\PIM\Products\Management\ProductView::class)->name('variants');
            Route::get('/images', \App\Livewire\PIM\Products\Management\ProductView::class)->name('images');
            Route::get('/attributes', \App\Livewire\PIM\Products\Management\ProductView::class)->name('attributes');
            Route::get('/sync', \App\Livewire\PIM\Products\Management\ProductView::class)->name('sync');
            Route::get('/edit', function(Product $product) { return view('products.products.edit', compact('product')); })->name('edit');
        });
        
        // Wildcard routes MUST come last
        Route::get('{product}', \App\Livewire\PIM\Products\Management\ProductView::class)->name('view');
    });

    // Admin Routes
    Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', function () { return 'Users Management - Coming Soon'; })->name('users.index');
        Route::get('roles', function () { return 'Roles Management - Coming Soon'; })->name('roles.index');
    });
});

require __DIR__.'/auth.php';
