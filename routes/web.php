<?php

use App\Livewire\Pricing\PricingDashboard;
use App\Livewire\Shopify\ShopifyColorDashboard;
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

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {

    // 👤 USER SETTINGS
    Route::redirect('settings', 'settings/profile');
    Route::view('settings/profile', 'settings.profile')->name('settings.profile');
    Route::view('settings/password', 'settings.password')->name('settings.password');
    Route::view('settings/appearance', 'settings.appearance')->name('settings.appearance');

    // 📦 PRODUCTS
    Route::view('products', 'products.index')->name('products.index');
    Route::get('products/create', function () {
        return view('products.create', ['product' => null]);
    })->name('products.create');

    // 🏗️ BUILDER PATTERN WIZARD
    Route::view('products/builder', 'products.builder')->name('products.builder');

    Route::get('products/{product}/builder', function (App\Models\Product $product) {
        return view('products.builder', compact('product'));
    })->name('products.builder.edit');

    Route::get('products/{product}', function (App\Models\Product $product) {
        return view('products.show', compact('product'));
    })->name('products.show');

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
    Route::view('import/products', 'import.products')->name('import.products');

    // 🎨 DIGITAL ASSET MANAGEMENT (DAM) - CONSISTENT MODEL BINDING
    Route::view('dam', 'dam.index')->name('dam.index');
    
    // Main image view (like products show)
    Route::get('dam/{image}', function (App\Models\Image $image) {
        return view('dam.show', compact('image'));
    })->name('dam.images.show');
    
    // Image tabs - consistent with products pattern
    Route::get('dam/{image}/overview', function (App\Models\Image $image) {
        return view('dam.show', compact('image'));
    })->name('dam.images.show.overview');
    
    Route::get('dam/{image}/edit', function (App\Models\Image $image) {
        return view('dam.show', compact('image'));
    })->name('dam.images.show.edit');
    
    Route::get('dam/{image}/attachments', function (App\Models\Image $image) {
        return view('dam.show', compact('image'));
    })->name('dam.images.show.attachments');
    
    Route::get('dam/{image}/history', function (App\Models\Image $image) {
        return view('dam.show', compact('image'));
    })->name('dam.images.show.history');
    

    // 💎 VARIANTS - UNIFIED WITH PRODUCTS
    Route::redirect('variants', 'products')->name('variants.index');
    Route::view('variants/create', 'variants.create')->name('variants.create');
    Route::get('variants/{variant}', function (App\Models\ProductVariant $variant) {
        return view('variants.show', compact('variant'));
    })->name('variants.show');
    Route::get('variants/{variant}/edit', function (App\Models\ProductVariant $variant) {
        return view('variants.edit', compact('variant'));
    })->name('variants.edit');

    // 🔢 BARCODES
    Route::view('barcodes', 'barcodes.index')->name('barcodes.index');
    Route::view('barcodes/create', 'barcodes.create')->name('barcodes.create');
    Route::get('barcodes/{barcode}', function (App\Models\Barcode $barcode) {
        return view('barcodes.show', compact('barcode'));
    })->name('barcodes.show');
    Route::get('barcodes/{barcode}/edit', function (App\Models\Barcode $barcode) {
        return view('barcodes.edit', compact('barcode'));
    })->name('barcodes.edit');

    // 🛍️ SHOPIFY SYNC
    Route::get('shopify', ShopifyDashboard::class)->name('shopify.sync');
    Route::get('shopify/colors', ShopifyColorDashboard::class)->name('shopify.colors');
    Route::get('shopify/webhooks', WebhookDashboard::class)->name('shopify.webhooks');

    // 💰 PRICING MANAGEMENT
    Route::get('pricing', PricingDashboard::class)->name('pricing.dashboard');
    Route::get('pricing/create', \App\Livewire\Pricing\PricingForm::class)->name('pricing.create');
    Route::get('pricing/{pricing}', \App\Livewire\Pricing\PricingShow::class)->name('pricing.show');
    Route::get('pricing/{pricing}/edit', \App\Livewire\Pricing\PricingForm::class)->name('pricing.edit');

    // 🚀 BULK OPERATIONS
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

    // 🏷️ MARKETPLACE IDENTIFIERS & INTEGRATIONS
    Route::get('marketplace/identifiers', \App\Livewire\Marketplace\IdentifiersDashboard::class)->name('marketplace.identifiers');
    Route::get('marketplace/add-integration', \App\Livewire\Marketplace\AddIntegrationWizard::class)->name('marketplace.add-integration');

    // 🔗 SYNC ACCOUNTS MANAGEMENT
    Route::get('sync-accounts', \App\Livewire\SyncAccounts\SyncAccountsIndex::class)->name('sync-accounts.index');
    Route::get('sync-accounts/create', \App\Livewire\SyncAccounts\CreateSyncAccount::class)->name('sync-accounts.create');
    Route::get('sync-accounts/{syncAccount}', function (App\Models\SyncAccount $syncAccount) {
        return view('sync-accounts.show', compact('syncAccount'));
    })->name('sync-accounts.show');

    // 🎛️ CHANNEL MAPPING SYSTEM
    Route::get('channel-mapping', \App\Livewire\ChannelMapping\ChannelMapperDashboard::class)->name('channel.mapping.dashboard');

    // 📊 LOG DASHBOARD
    Route::get('logs', \App\Livewire\LogDashboard::class)->name('logs.dashboard');

});

require __DIR__.'/auth.php';
