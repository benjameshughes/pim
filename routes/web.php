<?php

use App\Livewire\Pricing\PricingDashboard;
use App\Livewire\Shopify\ShopifyDashboard;
use App\Livewire\Shopify\WebhookDashboard;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ✨ PHOENIX ROUTES - CLEAN & ORGANIZED
|--------------------------------------------------------------------------
|
| Beautiful, minimal routes for our Phoenix PIM system
| Organized by our Sacred Three: Products, Variants, Barcodes + Shopify
|
*/

Route::redirect('/', 'login')->name('home');

Route::get('dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {

    // 👤 USER SETTINGS
    Route::redirect('settings', 'settings/profile');
    Route::view('settings/profile', 'settings.profile')->name('settings.profile');
    Route::view('settings/password', 'settings.password')->name('settings.password');
    Route::view('settings/appearance', 'settings.appearance')->name('settings.appearance');

    // 📦 PRODUCTS - with authorization
    Route::middleware('can:view-products')->group(function () {
        Route::view('products', 'products.index')->name('products.index');

        Route::get('products/{product}', function (App\Models\Product $product) {
            return view('products.show', compact('product'));
        })->name('products.show');
    });

    Route::middleware('can:create-products')->group(function () {
        Route::get('products/create', function () {
            return view('products.create', ['product' => null]);
        })->name('products.create');

        // 🏗️ BUILDER PATTERN WIZARD
        Route::view('products/builder', 'products.builder')->name('products.builder');
    });

    Route::middleware('can:edit-products')->group(function () {
        Route::get('products/{product}/builder', function (App\Models\Product $product) {
            return view('products.builder', compact('product'));
        })->name('products.builder.edit');
    });

    // 📑 PRODUCT TABS - Clean TabSet Integration
    Route::get('products/{product}/overview', function (App\Models\Product $product) {
        return view('products.show', compact('product'));
    })->name('products.show.overview');

    Route::get('products/{product}/variants', function (App\Models\Product $product) {
        return view('products.show', compact('product'));
    })->name('products.show.variants');

    Route::get('products/{product}/marketplace', function (App\Models\Product $product) {
        return view('products.show', compact('product'));
    })->name('products.show.marketplace');

    Route::get('products/{product}/attributes', function (App\Models\Product $product) {
        return view('products.show', compact('product'));
    })->name('products.show.attributes');

    Route::get('products/{product}/pricing', function (App\Models\Product $product) {
        return view('products.show', compact('product'));
    })->name('products.show.pricing');

    Route::get('products/{product}/images', function (App\Models\Product $product) {
        return view('products.show', compact('product'));
    })->name('products.show.images');

    Route::get('products/{product}/history', function (App\Models\Product $product) {
        return view('products.show', compact('product'));
    })->name('products.show.history');

    Route::get('products/{product}/edit', function (App\Models\Product $product) {
        return view('products.create', compact('product'));
    })->name('products.edit');

    // 📤 IMPORT
    Route::middleware('can:import-products')->group(function () {
        Route::view('import/products', 'import.products')->name('import.products');
    });

    // 🖼️ IMAGES MANAGEMENT
    Route::middleware('can:view-images')->group(function () {
        Route::view('images', 'images.index')->name('images.index');
        Route::get('images/{image}', function (App\Models\Image $image) {
            return view('images.show', compact('image'));
        })->name('images.show');

        // 📑 IMAGE TABS - Clean TabSet Integration
        Route::get('images/{image}/overview', function (App\Models\Image $image) {
            return view('images.show', compact('image'));
        })->name('images.show.overview');

        Route::get('images/{image}/attachments', function (App\Models\Image $image) {
            return view('images.show', compact('image'));
        })->name('images.show.attachments');

        Route::get('images/{image}/history', function (App\Models\Image $image) {
            return view('images.show', compact('image'));
        })->name('images.show.history');
    });
    Route::middleware('can:manage-images')->group(function () {
        Route::get('images/{image}/edit', function (App\Models\Image $image) {
            return view('images.show', compact('image'));
        })->name('images.show.edit');
    });

    // 💎 VARIANTS - UNIFIED WITH PRODUCTS
    Route::redirect('variants', 'products')->name('variants.index');
    Route::middleware('can:create-variants')->group(function () {
        Route::view('variants/create', 'variants.create')->name('variants.create');
    });
    Route::middleware('can:view-variant-details')->group(function () {
        Route::get('variants/{variant}', function (App\Models\ProductVariant $variant) {
            return view('variants.show', compact('variant'));
        })->name('variants.show');
    });
    Route::middleware('can:edit-variants')->group(function () {
        Route::get('variants/{variant}/edit', function (App\Models\ProductVariant $variant) {
            return view('variants.edit', compact('variant'));
        })->name('variants.edit');
    });

    // 📊 BARCODES
    Route::middleware('can:view-barcodes')->group(function () {
        Route::view('barcodes', 'barcodes.index')->name('barcodes.index');
    });
    Route::middleware('can:import-barcodes')->group(function () {
        Route::view('barcodes/import', 'barcodes.import')->name('barcodes.import');
    });

    // 🛍️ SHOPIFY SYNC
    Route::middleware('can:sync-to-marketplace')->group(function () {
        Route::get('shopify', ShopifyDashboard::class)->name('shopify.sync');
        Route::get('shopify/webhooks', WebhookDashboard::class)->name('shopify.webhooks');
    });

    // 💰 PRICING MANAGEMENT
    Route::middleware('can:view-pricing')->group(function () {
        Route::get('pricing', PricingDashboard::class)->name('pricing.dashboard');
        Route::get('pricing/{pricing}', \App\Livewire\Pricing\PricingShow::class)->name('pricing.show');
    });
    Route::middleware('can:edit-pricing')->group(function () {
        Route::get('pricing/create', \App\Livewire\Pricing\PricingForm::class)->name('pricing.create');
        Route::get('pricing/{pricing}/edit', \App\Livewire\Pricing\PricingForm::class)->name('pricing.edit');
    });

    // 🚀 BULK OPERATIONS
    Route::middleware('can:bulk-operations')->group(function () {
        Route::view('bulk-operations', 'bulk-operations.index')->name('bulk.operations');
        Route::get('bulk-operations/pricing/{targetType}/{selectedItems}', function (string $targetType, string $selectedItems) {
            return view('bulk-operations.pricing', compact('targetType', 'selectedItems'));
        })->name('bulk.pricing');
        Route::get('bulk-operations/images/{targetType}/{selectedItems}', function (string $targetType, string $selectedItems) {
            return view('bulk-operations.images', compact('targetType', 'selectedItems'));
        })->name('bulk.images');
        Route::get('bulk-operations/attributes/{targetType}/{selectedItems}', function (string $targetType, string $selectedItems) {
            return view('bulk-operations.attributes', compact('targetType', 'selectedItems'));
        })->name('bulk.attributes');
    });

    // 🏷️ MARKETPLACE IDENTIFIERS & INTEGRATIONS
    Route::middleware('can:manage-marketplace-connections')->group(function () {
        // Legacy alias: point identifiers route to Sync Accounts Index
        Route::get('marketplace/identifiers', \App\Livewire\SyncAccounts\SyncAccountsIndex::class)->name('marketplace.identifiers');
    });

    // 🔗 SYNC ACCOUNTS MANAGEMENT
    Route::middleware('can:manage-marketplace-connections')->group(function () {
        Route::get('sync-accounts', \App\Livewire\SyncAccounts\SyncAccountsIndex::class)->name('sync-accounts.index');
        Route::get('sync-accounts/create', \App\Livewire\SyncAccounts\Form::class)->name('sync-accounts.create');
        Route::get('sync-accounts/{accountId}/edit', \App\Livewire\SyncAccounts\Form::class)->name('sync-accounts.edit');
        // Removed legacy add-integration wizard and show route; use central form instead
    });

    // 🎛️ CHANNEL MAPPING SYSTEM

    // 📊 LOG DASHBOARD
    Route::middleware('can:view-system-logs')->group(function () {
        Route::get('logs', \App\Livewire\LogDashboard::class)->name('log-dashboard');

        // 📑 LOG DASHBOARD TABS - Clean TabSet Integration
        Route::get('logs/overview', \App\Livewire\LogDashboard::class)->name('log-dashboard.overview');
        Route::get('logs/activity', \App\Livewire\LogDashboard::class)->name('log-dashboard.activity');
        Route::get('logs/performance', \App\Livewire\LogDashboard::class)->name('log-dashboard.performance');
        Route::get('logs/errors', \App\Livewire\LogDashboard::class)->name('log-dashboard.errors');
    });

    // 🏢 MANAGEMENT - USER ADMINISTRATION (Admin only)
    Route::prefix('management')->name('management.')->middleware('can:manage-system-settings')->group(function () {
        Route::get('users', \App\Livewire\Management\Users\UserIndex::class)->name('users.index');
        Route::get('user-roles', \App\Livewire\Management\UserRoleManagement::class)->name('user-roles.index');
    });

    // 🎯 ACTIVITY TRACKING API - For gorgeous verbose logging
    Route::post('api/activity-tracking', [App\Http\Controllers\Api\ActivityTrackingController::class, 'track'])
        ->name('api.activity.track');

    Route::get('api/activity-summary', [App\Http\Controllers\Api\ActivityTrackingController::class, 'summary'])
        ->name('api.activity.summary');

});

require __DIR__.'/auth.php';
