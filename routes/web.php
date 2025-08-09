<?php

// Legacy framework navigation disabled
use App\Livewire\DataExchange\Export\ShopifyExport;
use App\Livewire\DataExchange\Sync\EbaySync;
use App\Livewire\DataExchange\Sync\MiraklSync;
use App\Livewire\DataExchange\Sync\ShopifySync;
use App\Livewire\Pim\Attributes\AttributeDefinitionsManager;
use App\Livewire\Pim\Barcodes\Pool\BarcodePoolImport;
use App\Livewire\Pim\Barcodes\Pool\PoolManager;
use App\Livewire\Pim\Products\Management\DeleteProduct;
use App\Livewire\Pim\Products\Variants\DeleteVariant;
use App\Livewire\Pim\Products\Variants\VariantEdit;
use App\Livewire\Pim\Products\Variants\VariantIndex;
use App\Livewire\Pim\Products\Variants\VariantView;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Legacy test navigation routes removed

Route::get('dashboard', function () {
    return view('dashboard');
})
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');

    // Barcode management routes
    Route::prefix('barcodes')->name('barcodes.')->group(function () {
        // Barcode management component
        Route::get('/', function () {
            return view('barcodes.index');
        })->name('index');

        // Pool management routes
        Route::prefix('pool')->name('pool.')->group(function () {
            Route::get('/', \App\Livewire\Pim\Barcodes\Pool\PoolManagerLite::class)->name('index');
            Route::get('/full', PoolManager::class)->name('full');
            Route::get('/import', BarcodePoolImport::class)->name('import');
        });
    });

    // Pricing management route
    Route::get('/pricing', function () {
        return view('pricing.index');
    })->name('pricing.index');

    // Images management route
    Route::get('/images', function () {
        return view('images.index');
    })->name('images.index');

    // Import/Export routes - New System
    Route::prefix('import')->name('import.')->group(function () {
        // Main import dashboard and create
        Route::get('/', [\App\Http\Controllers\ImportController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\ImportController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\ImportController::class, 'store'])->name('store');
        
        // Session-specific routes
        Route::get('/{sessionId}', [\App\Http\Controllers\ImportController::class, 'show'])->name('show');
        Route::get('/{sessionId}/status', [\App\Http\Controllers\ImportController::class, 'status'])->name('status');
        
        // Column mapping
        Route::get('/{sessionId}/mapping', [\App\Http\Controllers\ImportController::class, 'mapping'])->name('mapping');
        Route::post('/{sessionId}/mapping', [\App\Http\Controllers\ImportController::class, 'saveMapping'])->name('save-mapping');
        
        // Processing control
        Route::post('/{sessionId}/start', [\App\Http\Controllers\ImportController::class, 'startProcessing'])->name('start-processing');
        Route::post('/{sessionId}/cancel', [\App\Http\Controllers\ImportController::class, 'cancel'])->name('cancel');
        
        // Downloads
        Route::get('/{sessionId}/download/{type?}', [\App\Http\Controllers\ImportController::class, 'download'])->name('download');
        
        // Session management
        Route::delete('/{sessionId}', [\App\Http\Controllers\ImportController::class, 'destroy'])->name('destroy');
    });

    // Legacy Import Routes removed - using new import system
    Route::get('/import/test', function () {
        return view('import.test');
    })->name('import.test');
    Route::get('/export', function () {
        return view('export.index');
    })->name('export');

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
    Route::get('/archive', function () {
        return view('archive.index');
    })->name('archive');

    // Clean Product Management Routes - Builder Pattern + Actions Pattern
    Route::resource('products', \App\Http\Controllers\Products\ProductController::class, [
        'except' => ['index']  // Use Livewire component for index instead
    ]);
    
    // Unified Products & Variants Index - Clean Blade Wrapper
    Route::view('products', 'products.index')->name('products.index');

    // Additional product routes
    Route::prefix('products')->name('products.')->group(function () {
        Route::post('{product}/restore', [\App\Http\Controllers\Products\ProductController::class, 'restore'])->name('restore');
        Route::delete('{product}/force', [\App\Http\Controllers\Products\ProductController::class, 'forceDestroy'])->name('force-destroy');
    });

    // Legacy routes that still need manual registration
    Route::prefix('products')->name('products.')->group(function () {
        // Product Creation Wizard - Enhanced with Builder patterns  
        Route::get('create/wizard', \App\Livewire\Pim\Products\Management\ProductWizard::class)->name('create.wizard');

        // Variants
        // Removed separate variants index - now unified in products table
        Route::get('variants/create', \App\Livewire\Products\VariantCreate::class)->name('variants.create');
        Route::get('{product}/variants/create', \App\Livewire\Products\VariantCreate::class)->name('variants.create-for-product');

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
            Route::get('/overview', \App\Livewire\Pim\Products\Management\ProductView::class)->name('overview');
            Route::get('/variants', \App\Livewire\Pim\Products\Management\ProductView::class)->name('variants');
            Route::get('/images', \App\Livewire\Pim\Products\Management\ProductView::class)->name('images');
            Route::get('/attributes', \App\Livewire\Pim\Products\Management\ProductView::class)->name('attributes');
            Route::get('/sync', \App\Livewire\Pim\Products\Management\ProductView::class)->name('sync');
            Route::get('/edit', function (Product $product) {
                return view('products.products.edit', compact('product'));
            })->name('edit');
        });

        // Wildcard routes MUST come last
        Route::get('{product}', \App\Livewire\Pim\Products\Management\ProductView::class)->name('view');
    });

    // Image Upload API Routes (for AJAX/API usage)
    Route::middleware(['auth'])->prefix('api/images')->name('api.images.')->group(function () {
        Route::post('upload', [\App\Http\Controllers\ImageUploadController::class, 'upload'])->name('upload');
        Route::delete('{image}', [\App\Http\Controllers\ImageUploadController::class, 'delete'])->name('delete');
        Route::put('reorder', [\App\Http\Controllers\ImageUploadController::class, 'reorder'])->name('reorder');
        Route::get('{modelType}/{modelId}', [\App\Http\Controllers\ImageUploadController::class, 'index'])->name('index');
    });

    // Example Routes (for demonstration)
    Route::middleware(['auth'])->prefix('examples')->name('examples.')->group(function () {
        Route::get('variant/{variant}/images', \App\Livewire\Examples\VariantImageManager::class)->name('variant.images');
        Route::get('product-creation', \App\Livewire\Examples\ProductCreationWithImages::class)->name('product.creation');
        Route::get('toast-demo', \App\Livewire\Examples\ToastDemo::class)->name('toast.demo');
        Route::get('attributes-demo', \App\Livewire\Attributes\AttributeDemo::class)->name('attributes.demo');
        // Route::get('stacked-list', \App\Livewire\ExampleStackedList::class)->name('stacked.list'); // Removed with old system
    });

    // Admin Routes
    Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', function () {
            return view('admin.users.index');
        })->name('users.index');
        Route::get('roles', function () {
            return view('admin.roles.index');
        })->name('roles.index');
    });
});

require __DIR__.'/auth.php';
