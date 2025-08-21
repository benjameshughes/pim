<?php

namespace App\Livewire\Shopify;

use App\Actions\Shopify\ShopifyImport;
use App\Models\Product;
use App\Models\ShopifySyncStatus;
use App\Services\Marketplace\Facades\Sync;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 🎭💅 SHOPIFY DASHBOARD - THE ULTIMATE SYNC CONTROL CENTER! 💅🎭
 *
 * Honey, this dashboard is SERVING major sync energy!
 * Manage, discover, push, pull - we do it all with STYLE! ✨
 */
class ShopifyDashboard extends Component
{
    use WithPagination;

    // 🎯 Filter Properties
    public $activeTab = 'overview';

    public $syncStatus = 'all';

    public $searchQuery = '';

    public $selectedProducts = [];

    public $showDiscovery = false;

    // 🔍 Discovery Properties
    public $discoveredProducts = [];

    public $selectedDiscoveryProducts = [];

    public $rawShopifyProducts = []; // Store raw data for import

    public $groupedProducts = []; // Store grouped products for display

    // 🚀 Action Properties
    public $isLoading = false;

    public $loadingMessage = '';

    public $lastSyncResults = null;

    // 📊 Stats Cache
    public $statsCache = null;

    // 📤 Push Products Properties
    public $pushProductsFilter = 'ready_to_push'; // ready_to_push, never_synced, needs_update, all

    public $pushSearchQuery = '';

    public $selectedPushProducts = [];

    public $pushMethod = 'colors'; // colors, regular

    public $lastPushResults = null;

    /**
     * 🎪 COMPONENT INITIALIZATION - Set the stage!
     */
    public function mount()
    {
        $this->refreshStats();
    }

    /**
     * 🎨 RENDER - The main performance!
     */
    public function render()
    {
        $data = match ($this->activeTab) {
            'products' => $this->getProductsData(),
            'discovery' => $this->getDiscoveryData(),
            'sync_history' => $this->getSyncHistoryData(),
            'push_products' => $this->getPushProductsData(),
            default => $this->getOverviewData(),
        };

        return view('livewire.shopify.shopify-dashboard', $data);
    }

    /**
     * 📊 GET OVERVIEW DATA - The summary statistics
     */
    protected function getOverviewData(): array
    {
        return [
            'stats' => $this->getStats(),
            'recentActivity' => $this->getRecentActivity(),
            'quickActions' => $this->getQuickActions(),
        ];
    }

    /**
     * 📋 GET PRODUCTS DATA - Our product catalog view
     */
    protected function getProductsData(): array
    {
        $query = Product::shopify()
            ->when($this->searchQuery, fn ($q) => $q->where('name', 'like', "%{$this->searchQuery}%")
                ->orWhere('parent_sku', 'like', "%{$this->searchQuery}%")
            )
            ->when($this->syncStatus !== 'all', function ($q) {
                if ($this->syncStatus === 'never_synced') {
                    $q->whereDoesntHave('shopifySyncStatus');
                } else {
                    $q->whereHas('shopifySyncStatus', fn ($sq) => $sq->where('sync_status', $this->syncStatus)
                    );
                }
            });

        return [
            'products' => $query->paginate(20),
            'stats' => $this->getStats(),
        ];
    }

    /**
     * 🔍 GET DISCOVERY DATA - Find Shopify products not in our system
     */
    protected function getDiscoveryData(): array
    {
        return [
            'discoveredProducts' => $this->discoveredProducts,
            'selectedProducts' => $this->selectedDiscoveryProducts,
            'hasDiscovered' => ! empty($this->discoveredProducts),
        ];
    }

    /**
     * 📈 GET SYNC HISTORY DATA - Track our sync operations
     */
    protected function getSyncHistoryData(): array
    {
        $syncHistory = ShopifySyncStatus::with(['product'])
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return [
            'syncHistory' => $syncHistory,
        ];
    }

    /**
     * 📤 GET PUSH PRODUCTS DATA - Products ready to push to Shopify
     */
    protected function getPushProductsData(): array
    {
        $query = Product::with(['variants', 'shopifySyncStatus'])
            ->when($this->pushSearchQuery, fn ($q) => $q->where('name', 'like', "%{$this->pushSearchQuery}%")
                ->orWhere('parent_sku', 'like', "%{$this->pushSearchQuery}%")
            )
            ->when($this->pushProductsFilter !== 'all', function ($q) {
                switch ($this->pushProductsFilter) {
                    case 'ready_to_push':
                        $q->where('status', \App\Enums\ProductStatus::ACTIVE->value)->whereHas('variants');
                        break;
                    case 'never_synced':
                        $q->whereDoesntHave('shopifySyncStatus');
                        break;
                    case 'needs_update':
                        $q->whereHas('shopifySyncStatus', fn ($sq) => $sq->where('sync_status', '!=', 'synced'));
                        break;
                }
            });

        return [
            'pushProducts' => $query->paginate(20),
            'pushStats' => $this->getPushStats(),
        ];
    }

    /**
     * 📊 GET STATS - The vital numbers
     */
    protected function getStats(): array
    {
        if ($this->statsCache) {
            return $this->statsCache;
        }

        // Get basic product stats
        $totalProducts = Product::count();
        $syncableProducts = Product::where('status', \App\Enums\ProductStatus::ACTIVE->value)->whereHas('variants')->count();

        // Get sync status stats from ShopifySyncStatus if it exists
        $syncedCount = 0;
        $failedCount = 0;
        $pendingCount = 0;
        $lastSync = null;

        if (class_exists(\App\Models\ShopifySyncStatus::class)) {
            $syncedCount = \App\Models\ShopifySyncStatus::where('sync_status', 'synced')->count();
            $failedCount = \App\Models\ShopifySyncStatus::where('sync_status', 'failed')->count();
            $pendingCount = \App\Models\ShopifySyncStatus::where('sync_status', 'pending')->count();
            $lastSync = \App\Models\ShopifySyncStatus::orderBy('updated_at', 'desc')->first()?->updated_at;
        }

        $this->statsCache = [
            'total_products' => $totalProducts,
            'syncable' => $syncableProducts,
            'synced' => $syncedCount,
            'needs_update' => max(0, $syncableProducts - $syncedCount), // Rough estimate
            'never_synced' => $pendingCount,
            'failed' => $failedCount,
            'last_sync' => $lastSync,
        ];

        return $this->statsCache;
    }

    /**
     * 📝 GET RECENT ACTIVITY - Latest sync operations
     */
    protected function getRecentActivity(): array
    {
        $recentActivity = [];

        // Get recent ShopifySyncStatus records if they exist
        if (class_exists(\App\Models\ShopifySyncStatus::class)) {
            $recentSyncs = \App\Models\ShopifySyncStatus::with('product')
                ->orderBy('updated_at', 'desc')
                ->take(5)
                ->get();

            $recentActivity = $recentSyncs->map(function ($sync) {
                return [
                    'id' => $sync->id,
                    'product' => $sync->product,
                    'action' => 'sync',
                    'success' => $sync->sync_status === 'synced',
                    'created_at' => $sync->updated_at,
                    'error_message' => $sync->sync_status === 'failed' ? 'Sync failed' : null,
                    'status' => $sync->sync_status,
                ];
            })->toArray();
        }

        return $recentActivity;
    }

    /**
     * ⚡ GET QUICK ACTIONS - One-click operations
     */
    protected function getQuickActions(): array
    {
        $stats = $this->getStats();

        return [
            [
                'label' => 'Sync Ready Products',
                'action' => 'syncReady',
                'count' => $stats['syncable'],
                'icon' => 'arrow-up-tray',
                'color' => 'green',
            ],
            [
                'label' => 'Update Changed Products',
                'action' => 'updateChanged',
                'count' => $stats['needs_update'],
                'icon' => 'arrow-path',
                'color' => 'blue',
            ],
            [
                'label' => 'Discover Shopify Products',
                'action' => 'discoverProducts',
                'count' => '?',
                'icon' => 'magnifying-glass',
                'color' => 'purple',
            ],
        ];
    }

    /**
     * 🎭 TAB SWITCHING - Change the scene!
     */
    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();

        // Clear search when switching tabs
        if ($tab !== 'products') {
            $this->searchQuery = '';
        }

        // Clear push search when leaving push tab
        if ($tab !== 'push_products') {
            $this->pushSearchQuery = '';
            $this->selectedPushProducts = [];
        }
    }

    /**
     * 🚀 SYNC READY PRODUCTS - Push the stage-ready ones!
     */
    public function syncReady($dryRun = false)
    {
        $this->startLoading('Syncing ready products to Shopify...');

        try {
            // Get syncable products
            $products = Product::with('variants')->where('status', \App\Enums\ProductStatus::ACTIVE->value)->get();

            if ($dryRun) {
                // For dry run, just preview what would be synced
                $this->lastSyncResults = [
                    'success' => true,
                    'message' => "Dry run: {$products->count()} products would be synced",
                    'products_processed' => $products->count(),
                    'dry_run' => true,
                ];
            } else {
                // ULTRA-SIMPLE: Use the Sync facade with color splitting!
                $result = Sync::shopify()->pushWithColors($products->toArray());

                $successCount = collect($result)->where('success', true)->count();
                $failCount = collect($result)->where('success', false)->count();

                $this->lastSyncResults = [
                    'success' => $failCount === 0,
                    'message' => "Synced {$successCount} products successfully".($failCount > 0 ? ", {$failCount} failed" : ''),
                    'products_processed' => count($result),
                    'results' => $result,
                ];
            }

            $this->refreshStats();

            $this->dispatch('toast', [
                'type' => $this->lastSyncResults['success'] ? 'success' : 'warning',
                'message' => $this->lastSyncResults['message'],
            ]);

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Sync failed: '.$e->getMessage(),
            ]);
        }

        $this->stopLoading();
    }

    /**
     * 🔄 UPDATE CHANGED PRODUCTS - Refresh the modified ones!
     */
    public function updateChanged($dryRun = false)
    {
        $this->startLoading('Updating modified products...');

        try {
            // Get products that need updates
            $products = Product::with('variants')->where('status', \App\Enums\ProductStatus::ACTIVE->value)->get()
                ->filter(function ($product) {
                    // Add logic to determine if product needs update
                    return true; // Simplified for now
                });

            if ($dryRun) {
                // For dry run, just preview what would be updated
                $this->lastSyncResults = [
                    'success' => true,
                    'message' => "Dry run: {$products->count()} products would be updated",
                    'products_processed' => $products->count(),
                    'dry_run' => true,
                ];
            } else {
                // Use the new repository to push products in bulk
                $result = $this->getShopifyProducts()->pushBulk($products);

                $successCount = $result->where('success', true)->count();
                $failCount = $result->where('success', false)->count();

                $this->lastSyncResults = [
                    'success' => $failCount === 0,
                    'message' => "Updated {$successCount} products successfully".($failCount > 0 ? ", {$failCount} failed" : ''),
                    'products_processed' => $result->count(),
                    'results' => $result,
                ];
            }

            $this->refreshStats();

            $this->dispatch('toast', [
                'type' => $this->lastSyncResults['success'] ? 'success' : 'warning',
                'message' => $this->lastSyncResults['message'],
            ]);

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Update failed: '.$e->getMessage(),
            ]);
        }

        $this->stopLoading();
    }

    /**
     * 🔍 DISCOVER PRODUCTS - Find external beauties!
     * Now ULTRA-SIMPLE with the new API! ✨
     */
    public function discoverProducts()
    {
        $this->startLoading('Discovering Shopify products...');

        try {
            // Check cache first (1 hour cache)
            $cacheKey = 'shopify_products_'.auth()->id();
            $shopifyProducts = Cache::remember($cacheKey, 3600, function () {
                return Sync::shopify()->pull(['limit' => 50]);
            });

            if ($shopifyProducts->isNotEmpty()) {
                // Process discovered products to identify new vs existing
                $existingSkus = Product::pluck('parent_sku')->toArray();

                $discoveredData = $shopifyProducts->map(function ($product) use ($existingSkus) {
                    // Extract SKU from variants if available
                    $sku = $product['sku'] ?? '';
                    if (empty($sku) && isset($product['variants']) && ! empty($product['variants'])) {
                        $sku = $product['variants'][0]['sku'] ?? '';
                    }

                    $isExisting = in_array($sku, $existingSkus);

                    return [
                        'shopify_id' => $product['id'] ?? 'unknown',
                        'title' => $product['title'] ?? 'Unknown Product',
                        'sku' => $sku,
                        'status' => $product['status'] ?? 'unknown',
                        'match_status' => $isExisting ? 'existing' : 'new',
                        'created_at' => $product['created_at'] ?? null,
                        'variant_count' => isset($product['variants']) ? count($product['variants']) : 0,
                        'variants' => $product['variants'] ?? [], // Preserve original variants data
                        'vendor' => $product['vendor'] ?? 'Unknown',
                        'product_type' => $product['product_type'] ?? 'Unknown',
                    ];
                });

                $this->discoveredProducts = $discoveredData->toArray();
                $this->rawShopifyProducts = $shopifyProducts->toArray(); // Store raw data for import
                $this->showDiscovery = true;
                $this->activeTab = 'discovery';

                $newCount = $discoveredData->where('match_status', 'new')->count();
                $existingCount = $discoveredData->where('match_status', 'existing')->count();

                $message = "Discovered {$discoveredData->count()} products from Shopify! ";
                $message .= "({$newCount} new, {$existingCount} existing)";

                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => $message,
                ]);
            } else {
                $this->dispatch('toast', [
                    'type' => 'warning',
                    'message' => 'No products found on Shopify',
                ]);
            }

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Discovery failed: '.$e->getMessage(),
            ]);
        }

        $this->stopLoading();
    }

    /**
     * 📥 IMPORT SELECTED DISCOVERY PRODUCTS - Import chosen Shopify products
     */
    public function importSelectedDiscoveryProducts()
    {
        if (empty($this->selectedDiscoveryProducts)) {
            $this->dispatch('toast', [
                'type' => 'warning',
                'message' => 'Please select products to import!',
            ]);

            return;
        }

        $this->startLoading('Importing '.count($this->selectedDiscoveryProducts).' selected products...');

        try {
            // Get cached Shopify products
            $cacheKey = 'shopify_products_'.auth()->id();
            $cachedProducts = Cache::get($cacheKey, collect());

            if ($cachedProducts->isEmpty()) {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => 'No cached product data found. Please discover products first.',
                ]);
                $this->stopLoading();

                return;
            }

            // Key by ID for easy lookup
            $productsById = $cachedProducts->keyBy('id');

            $imported = 0;
            $errors = [];
            $importAction = new ShopifyImport;

            // Get the selected products
            $selectedProducts = [];
            foreach ($this->selectedDiscoveryProducts as $shopifyId) {
                $shopifyProduct = $productsById->get($shopifyId);
                if ($shopifyProduct) {
                    $selectedProducts[] = $shopifyProduct;
                } else {
                    $errors[] = "Product ID {$shopifyId} not found in cached data";
                }
            }

            if (! empty($selectedProducts)) {
                // Use the new grouped import method
                $result = $importAction->importProducts($selectedProducts);

                if ($result['success']) {
                    $imported = $result['groups_processed'];

                    // Count total variants across all groups
                    $totalVariants = collect($result['results'])->sum('variants_created');

                    $this->dispatch('toast', [
                        'type' => 'success',
                        'message' => "Successfully imported {$imported} product groups with {$totalVariants} total variants!",
                    ]);
                } else {
                    $errors[] = "Batch import failed: {$result['error']}";
                }

                // Add individual group errors if any
                foreach ($result['results'] ?? [] as $groupResult) {
                    if (! $groupResult['success']) {
                        $errors[] = "Failed to import group {$groupResult['parent_sku']}: {$groupResult['error']}";
                    }
                }
            }

            // Clear selection and refresh if any imports succeeded
            if ($imported > 0) {
                $this->selectedDiscoveryProducts = [];
                $this->discoverProducts(); // Refresh to update match status
                $this->refreshStats();
            }

            // Show individual errors if any
            foreach ($errors as $error) {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => $error,
                ]);
            }

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Import failed: '.$e->getMessage(),
            ]);
        }

        $this->stopLoading();
    }

    /**
     * 🎯 TOGGLE DISCOVERY PRODUCT SELECTION
     */
    public function toggleDiscoveryProduct($shopifyId)
    {
        if (in_array($shopifyId, $this->selectedDiscoveryProducts)) {
            $this->selectedDiscoveryProducts = array_filter(
                $this->selectedDiscoveryProducts,
                fn ($id) => $id !== $shopifyId
            );
        } else {
            $this->selectedDiscoveryProducts[] = $shopifyId;
        }
    }

    /**
     * ✅ SELECT ALL DISCOVERY PRODUCTS
     */
    public function selectAllDiscoveryProducts()
    {
        $this->selectedDiscoveryProducts = array_map(
            fn ($product) => $product['shopify_id'],
            array_filter($this->discoveredProducts, fn ($p) => $p['match_status'] === 'new')
        );
    }

    /**
     * 🗑️ CLEAR DISCOVERY SELECTION
     */
    public function clearDiscoverySelection()
    {
        $this->selectedDiscoveryProducts = [];
    }

    /**
     * 🎯 SYNC SELECTED - Push specific products
     */
    public function syncSelected($dryRun = false)
    {
        if (empty($this->selectedProducts)) {
            $this->dispatch('toast', [
                'type' => 'warning',
                'message' => 'Please select products to sync! 💫',
            ]);

            return;
        }

        $this->startLoading('Syncing '.count($this->selectedProducts).' selected products...');

        try {
            // Get the selected products with variants
            $products = Product::with('variants')->whereIn('id', $this->selectedProducts)->get();

            if ($dryRun) {
                // For dry run, just preview what would be synced
                $this->lastSyncResults = [
                    'success' => true,
                    'message' => "Dry run: {$products->count()} selected products would be synced",
                    'products_processed' => $products->count(),
                    'dry_run' => true,
                ];
            } else {
                // Use the new repository to push the selected products
                $result = $this->getShopifyProducts()->pushBulk($products);

                $successCount = $result->where('success', true)->count();
                $failCount = $result->where('success', false)->count();

                $this->lastSyncResults = [
                    'success' => $failCount === 0,
                    'message' => "Synced {$successCount} selected products successfully".($failCount > 0 ? ", {$failCount} failed" : ''),
                    'products_processed' => $result->count(),
                    'results' => $result,
                ];
            }

            $this->selectedProducts = []; // Clear selection
            $this->refreshStats();

            $this->dispatch('toast', [
                'type' => $this->lastSyncResults['success'] ? 'success' : 'warning',
                'message' => $this->lastSyncResults['message'],
            ]);

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Sync failed: '.$e->getMessage(),
            ]);
        }

        $this->stopLoading();
    }

    /**
     * 🔄 REFRESH STATS - Update our vital numbers
     */
    public function refreshStats()
    {
        $this->statsCache = null;
        $this->getStats();
    }

    /**
     * 🎬 START LOADING - Show the loading state
     */
    protected function startLoading($message)
    {
        $this->isLoading = true;
        $this->loadingMessage = $message;
    }

    /**
     * ⏹️ STOP LOADING - Hide the loading state
     */
    protected function stopLoading()
    {
        $this->isLoading = false;
        $this->loadingMessage = '';
    }

    /**
     * ✨ TOGGLE PRODUCT SELECTION - Manage selected products
     */
    public function toggleProduct($productId)
    {
        if (in_array($productId, $this->selectedProducts)) {
            $this->selectedProducts = array_filter(
                $this->selectedProducts,
                fn ($id) => $id !== $productId
            );
        } else {
            $this->selectedProducts[] = $productId;
        }
    }

    /**
     * 🎯 SELECT ALL VISIBLE - Mass selection
     */
    public function selectAllVisible()
    {
        $visibleProducts = $this->getProductsData()['products']->pluck('id')->toArray();
        $this->selectedProducts = array_unique(array_merge($this->selectedProducts, $visibleProducts));
    }

    /**
     * 🗑️ CLEAR SELECTION - Reset selection
     */
    public function clearSelection()
    {
        $this->selectedProducts = [];
    }

    /**
     * 🔍 UPDATING SEARCH - Reset pagination when searching
     */
    public function updatingSearchQuery()
    {
        $this->resetPage();
    }

    /**
     * 📊 UPDATING SYNC STATUS - Reset pagination when filtering
     */
    public function updatingSyncStatus()
    {
        $this->resetPage();
    }

    /**
     * 📤 GET PUSH STATS - Statistics for push tab
     */
    protected function getPushStats(): array
    {
        $readyToPush = Product::where('status', \App\Enums\ProductStatus::ACTIVE->value)->whereHas('variants')->count();
        $neverSynced = Product::whereDoesntHave('shopifySyncStatus')->count();
        $needsUpdate = 0;

        if (class_exists(\App\Models\ShopifySyncStatus::class)) {
            $needsUpdate = \App\Models\ShopifySyncStatus::where('sync_status', '!=', 'synced')->count();
        }

        return [
            'ready_to_push' => $readyToPush,
            'never_synced' => $neverSynced,
            'needs_update' => $needsUpdate,
            'total_pushable' => $readyToPush,
        ];
    }

    /**
     * 📤 PUSH SELECTED PRODUCTS - Send selected products to Shopify
     */
    public function pushSelectedProducts($dryRun = false)
    {
        if (empty($this->selectedPushProducts)) {
            $this->dispatch('toast', [
                'type' => 'warning',
                'message' => 'Please select products to push! 🚀',
            ]);

            return;
        }

        $this->startLoading('Pushing '.count($this->selectedPushProducts).' products to Shopify...');

        try {
            // Get the selected products with variants
            $products = Product::with('variants')->whereIn('id', $this->selectedPushProducts)->get();

            if ($dryRun) {
                // For dry run, just preview what would be pushed
                $this->lastPushResults = [
                    'success' => true,
                    'message' => "Dry run: {$products->count()} products would be pushed to Shopify",
                    'total' => $products->count(),
                    'successful' => $products->count(),
                    'failed' => 0,
                    'dry_run' => true,
                ];
            } else {
                // Filter out products with too many variants
                $validProducts = $products->filter(function ($product) {
                    return $product->variants->count() <= 100;
                });

                $skippedCount = $products->count() - $validProducts->count();

                if ($skippedCount > 0) {
                    $this->dispatch('toast', [
                        'type' => 'warning',
                        'message' => "⚠️ Skipped {$skippedCount} products with >100 variants (Shopify REST API limit)",
                    ]);
                }

                if ($validProducts->isEmpty()) {
                    $this->dispatch('toast', [
                        'type' => 'error',
                        'message' => '❌ No products can be pushed - all have >100 variants',
                    ]);
                    $this->stopLoading();

                    return;
                }

                // Use the appropriate push method based on selection
                if ($this->pushMethod === 'colors') {
                    $result = Sync::shopify()->pushWithColors($validProducts);  // Pass collection for colors
                } else {
                    // For regular method, ensure each product has title field
                    $productsArray = $validProducts->map(function ($product) {
                        $productArray = $product->toArray();
                        if (! isset($productArray['title'])) {
                            $productArray['title'] = $productArray['name'] ?? 'Untitled Product';
                        }

                        return $productArray;
                    })->toArray();
                    $result = Sync::shopify()->push($productsArray);  // Pass array for regular
                }

                $successCount = collect($result)->where('success', true)->count();
                $failCount = collect($result)->where('success', false)->count();

                $this->lastPushResults = [
                    'success' => $failCount === 0,
                    'message' => "Pushed {$successCount} products successfully".($failCount > 0 ? ", {$failCount} failed" : '')
                        .($skippedCount > 0 ? " ({$skippedCount} skipped)" : ''),
                    'total' => count($result),
                    'successful' => $successCount,
                    'failed' => $failCount,
                    'results' => $result,
                ];
            }

            if (! $dryRun) {
                $this->selectedPushProducts = []; // Clear selection after successful push
            }

            $this->refreshStats();

            $this->dispatch('toast', [
                'type' => $this->lastPushResults['success'] ? 'success' : 'warning',
                'message' => $this->lastPushResults['message'],
            ]);

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Push failed: '.$e->getMessage(),
            ]);
        }

        $this->stopLoading();
    }

    /**
     * 🎯 TOGGLE PUSH PRODUCT SELECTION
     */
    public function togglePushProduct($productId)
    {
        if (in_array($productId, $this->selectedPushProducts)) {
            $this->selectedPushProducts = array_filter(
                $this->selectedPushProducts,
                fn ($id) => $id !== $productId
            );
        } else {
            $this->selectedPushProducts[] = $productId;
        }
    }

    /**
     * ✅ SELECT ALL VISIBLE PUSH PRODUCTS
     */
    public function selectAllVisiblePushProducts()
    {
        $visibleProducts = $this->getPushProductsData()['pushProducts']->pluck('id')->toArray();
        $this->selectedPushProducts = array_unique(array_merge($this->selectedPushProducts, $visibleProducts));
    }

    /**
     * 🗑️ CLEAR PUSH SELECTION
     */
    public function clearPushSelection()
    {
        $this->selectedPushProducts = [];
    }

    /**
     * 🚀 BULK PUSH BY FILTER - Push products based on current filter
     */
    public function bulkPushByFilter($dryRun = false)
    {
        $this->startLoading('Pushing products based on filter: '.$this->pushProductsFilter.'...');

        try {
            // Get products based on current filter (without pagination limit for bulk operation)
            $query = Product::with(['variants', 'shopifySyncStatus'])
                ->when($this->pushSearchQuery, fn ($q) => $q->where('name', 'like', "%{$this->pushSearchQuery}%")
                    ->orWhere('parent_sku', 'like', "%{$this->pushSearchQuery}%")
                )
                ->when($this->pushProductsFilter !== 'all', function ($q) {
                    switch ($this->pushProductsFilter) {
                        case 'ready_to_push':
                            $q->where('status', \App\Enums\ProductStatus::ACTIVE->value)->whereHas('variants');
                            break;
                        case 'never_synced':
                            $q->whereDoesntHave('shopifySyncStatus');
                            break;
                        case 'needs_update':
                            $q->whereHas('shopifySyncStatus', fn ($sq) => $sq->where('sync_status', '!=', 'synced'));
                            break;
                    }
                })
                ->limit(50); // Safety limit for bulk operations

            $products = $query->get();

            if ($products->isEmpty()) {
                $this->dispatch('toast', [
                    'type' => 'warning',
                    'message' => 'No products found matching current filter! 🔍',
                ]);
                $this->stopLoading();

                return;
            }

            if ($dryRun) {
                // For dry run, just preview what would be pushed
                $this->lastPushResults = [
                    'success' => true,
                    'message' => "Dry run: {$products->count()} products would be pushed to Shopify",
                    'total' => $products->count(),
                    'successful' => $products->count(),
                    'failed' => 0,
                    'dry_run' => true,
                ];
            } else {
                // Filter out products with too many variants
                $validProducts = $products->filter(function ($product) {
                    return $product->variants->count() <= 100;
                });

                $skippedCount = $products->count() - $validProducts->count();

                if ($skippedCount > 0) {
                    $this->dispatch('toast', [
                        'type' => 'warning',
                        'message' => "⚠️ Skipped {$skippedCount} products with >100 variants (Shopify REST API limit)",
                    ]);
                }

                if ($validProducts->isEmpty()) {
                    $this->dispatch('toast', [
                        'type' => 'error',
                        'message' => '❌ No products can be pushed - all have >100 variants',
                    ]);
                    $this->stopLoading();

                    return;
                }

                // Use the appropriate push method based on selection
                if ($this->pushMethod === 'colors') {
                    $result = Sync::shopify()->pushWithColors($validProducts);  // Pass collection for colors
                } else {
                    // For regular method, ensure each product has title field
                    $productsArray = $validProducts->map(function ($product) {
                        $productArray = $product->toArray();
                        if (! isset($productArray['title'])) {
                            $productArray['title'] = $productArray['name'] ?? 'Untitled Product';
                        }

                        return $productArray;
                    })->toArray();
                    $result = Sync::shopify()->push($productsArray);  // Pass array for regular
                }

                $successCount = collect($result)->where('success', true)->count();
                $failCount = collect($result)->where('success', false)->count();

                $this->lastPushResults = [
                    'success' => $failCount === 0,
                    'message' => "Bulk pushed {$successCount} products successfully".($failCount > 0 ? ", {$failCount} failed" : '')
                        .($skippedCount > 0 ? " ({$skippedCount} skipped)" : ''),
                    'total' => count($result),
                    'successful' => $successCount,
                    'failed' => $failCount,
                    'results' => $result,
                ];
            }

            $this->refreshStats();

            $this->dispatch('toast', [
                'type' => $this->lastPushResults['success'] ? 'success' : 'warning',
                'message' => $this->lastPushResults['message'],
            ]);

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Bulk push failed: '.$e->getMessage(),
            ]);
        }

        $this->stopLoading();
    }

    /**
     * 🚀 PUSH SINGLE PRODUCT - Push individual product to Shopify
     */
    public function pushSingleProduct($productId)
    {
        $this->startLoading('Pushing product to Shopify...');

        try {
            // Get the product with variants
            $product = Product::with('variants')->find($productId);

            if (! $product) {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => 'Product not found! 🔍',
                ]);
                $this->stopLoading();

                return;
            }

            if ($product->variants->isEmpty()) {
                $this->dispatch('toast', [
                    'type' => 'warning',
                    'message' => 'Product has no variants to push! 📦',
                ]);
                $this->stopLoading();

                return;
            }

            // Check if product has too many variants for REST API
            if ($product->variants->count() > 100) {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => "❌ Product \"{$product->name}\" has {$product->variants->count()} variants. Shopify REST API only supports up to 100 variants per product. Please use GraphQL API or split the product.",
                ]);
                $this->stopLoading();

                return;
            }

            // Use the appropriate push method - pass Eloquent model, not array for colors method
            if ($this->pushMethod === 'colors') {
                $result = Sync::shopify()->pushWithColors([$product]);  // Pass model for colors
            } else {
                // For regular method, ensure we have title field
                $productArray = $product->toArray();
                if (! isset($productArray['title'])) {
                    $productArray['title'] = $productArray['name'] ?? 'Untitled Product';
                }
                $result = Sync::shopify()->push([$productArray]);  // Pass array for regular
            }

            $success = collect($result)->where('success', true)->count() > 0;

            if ($success) {
                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => "✅ Successfully pushed \"{$product->name}\" to Shopify!",
                ]);
            } else {
                $errorMessage = collect($result)->pluck('error')->filter()->first() ?? 'Unknown error';
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => "❌ Failed to push \"{$product->name}\": {$errorMessage}",
                ]);
            }

            $this->refreshStats();

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Push failed: '.$e->getMessage(),
            ]);
        }

        $this->stopLoading();
    }

    /**
     * 🔍 UPDATING PUSH SEARCH - Reset pagination when searching
     */
    public function updatingPushSearchQuery()
    {
        $this->resetPage();
    }

    /**
     * 📊 UPDATING PUSH FILTER - Reset pagination when filtering
     */
    public function updatingPushProductsFilter()
    {
        $this->resetPage();
        $this->selectedPushProducts = []; // Clear selection when filter changes
    }
}
