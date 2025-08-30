<?php

namespace App\Livewire\Marketplace;

use App\Jobs\SyncProductToMarketplaceJob;
use App\Jobs\UpdateMarketplaceListingJob;
use App\Models\MarketplaceLink;
use App\Models\Product;
use App\Models\SyncAccount;
use App\Models\SyncStatus;
use App\Services\Shopify\ShopifyProductDiscoveryService;
use Livewire\Component;

class MarketplaceSyncCards extends Component
{
    public Product $product;

    public function mount(Product $product)
    {
        // Authorize managing marketplace sync operations
        $this->authorize('manage-marketplace-sync');
        
        $this->product = $product->load([
            'syncStatuses.syncAccount',
            'marketplaceLinks.syncAccount',
            'syncLogs' => function ($query) {
                $query->with('syncAccount')->latest()->limit(5);
            },
        ]);

        // Initialize properties to prevent undefined property errors
        $this->existingLinks = [];
        $this->colorMappings = [];
        $this->shopifyProducts = [];
    }

    public $showLinkingModal = false;

    public $showShopifyColorModal = false;

    public $showEditLinksModal = false;

    public $linkingAccountId = null;

    public $externalProductId = '';

    // Edit Links Modal
    public $editingAccountId = null;

    public $existingLinks = [];

    public $newExternalId = '';

    // Shopify Color Linking
    public $shopifyProducts = [];

    public $colorMappings = [];

    public $discoveryLoading = false;

    public function syncToMarketplace(int $syncAccountId)
    {
        // Authorize syncing to marketplace
        $this->authorize('sync-to-marketplace');
        
        $syncAccount = SyncAccount::findOrFail($syncAccountId);

        // Dispatch the job using your existing Actions
        SyncProductToMarketplaceJob::dispatch($this->product, $syncAccount, [
            'method' => 'manual_ui',
            'initiated_by' => 'marketplace_sync_cards',
        ]);

        $this->dispatch('success', "Sync to {$syncAccount->channel} ({$syncAccount->name}) initiated! 🚀 Job queued for processing.");

        // Refresh the component to show updated status
        $this->product = $this->product->fresh([
            'syncStatuses.syncAccount',
            'syncLogs' => function ($query) {
                $query->with('syncAccount')->latest()->limit(5);
            },
        ]);
    }

    public function updateMarketplaceListing(int $syncAccountId)
    {
        // Authorize updating marketplace listings
        $this->authorize('update-marketplace-listings');
        
        $syncAccount = SyncAccount::findOrFail($syncAccountId);

        // Dispatch the update job using your existing Actions
        UpdateMarketplaceListingJob::dispatch($this->product, $syncAccount, [
            'method' => 'manual_update',
            'initiated_by' => 'marketplace_sync_cards',
            'update_type' => 'full', // Can be customized based on UI needs
        ]);

        $this->dispatch('success', "Update to {$syncAccount->channel} ({$syncAccount->name}) initiated! 📤 Job queued for processing.");

        // Refresh the component to show updated status
        $this->product = $this->product->fresh([
            'syncStatuses.syncAccount',
            'syncLogs' => function ($query) {
                $query->with('syncAccount')->latest()->limit(5);
            },
        ]);
    }

    public function showLinkingModal(int $syncAccountId)
    {
        $this->linkingAccountId = $syncAccountId;
        $this->externalProductId = '';
        $this->showLinkingModal = true;
    }

    public function closeLinkingModal()
    {
        $this->showLinkingModal = false;
        $this->linkingAccountId = null;
        $this->externalProductId = '';
    }

    public function linkToMarketplace()
    {
        // Authorize linking to marketplace
        $this->authorize('link-marketplace-products');
        
        $this->validate([
            'externalProductId' => 'required|string|min:1',
        ]);

        $syncAccount = SyncAccount::findOrFail($this->linkingAccountId);

        try {
            // Create or update MarketplaceLink (new system)
            $marketplaceLink = MarketplaceLink::firstOrCreate([
                'linkable_type' => Product::class,
                'linkable_id' => $this->product->id,
                'sync_account_id' => $syncAccount->id,
            ], [
                'internal_sku' => $this->product->parent_sku,
                'external_sku' => $this->product->parent_sku,
                'link_status' => 'pending',
                'link_level' => 'product',
            ]);

            $marketplaceLink->update([
                'external_product_id' => $this->externalProductId,
                'link_status' => 'linked',
                'linked_at' => now(),
                'linked_by' => auth()->user()?->name ?? 'system',
                'marketplace_data' => array_merge($marketplaceLink->marketplace_data ?? [], [
                    'linked_manually' => true,
                    'linked_at' => now()->toISOString(),
                    'linked_via' => 'link_modal',
                ]),
            ]);

            // Also create/update SyncStatus for backward compatibility
            $syncStatus = \App\Models\SyncStatus::findOrCreateFor($this->product, $syncAccount);
            $syncStatus->update([
                'external_product_id' => $this->externalProductId,
                'sync_status' => 'synced', // Mark as synced since it's already linked
                'last_synced_at' => now(),
                'metadata' => array_merge($syncStatus->metadata ?? [], [
                    'linked_manually' => true,
                    'linked_at' => now()->toISOString(),
                    'marketplace_link_id' => $marketplaceLink->id, // Reference to MarketplaceLink
                ]),
            ]);

            // Log the linking action
            \App\Models\SyncLog::createEntry($syncAccount, 'link', $this->product, $syncStatus)
                ->markAsSuccessful("Product linked to external ID: {$this->externalProductId} (MarketplaceLink #{$marketplaceLink->id})");

            $this->dispatch('success', "Product linked to {$syncAccount->channel} listing! 🔗");

        } catch (\Exception $e) {
            \Log::error('Failed to link marketplace product', [
                'product_id' => $this->product->id,
                'sync_account_id' => $syncAccount->id,
                'external_product_id' => $this->externalProductId,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('error', 'Failed to link product: '.$e->getMessage());

            return;
        }

        $this->closeLinkingModal();

        // Refresh the product data to show updated sync status
        $this->mount($this->product->fresh());
    }

    public function unlinkFromMarketplace(int $syncAccountId)
    {
        // Authorize unlinking from marketplace
        $this->authorize('unlink-marketplace-products');
        
        $syncAccount = SyncAccount::findOrFail($syncAccountId);
        $unlinkedCount = 0;
        $externalIds = [];

        // Handle MarketplaceLink records (newer system)
        $marketplaceLinks = $this->product->marketplaceLinks()
            ->where('sync_account_id', $syncAccountId)
            ->get();

        foreach ($marketplaceLinks as $link) {
            if ($link->external_product_id) {
                $externalIds[] = $link->external_product_id;
            }

            // Unlink the MarketplaceLink
            $link->unlink();
            $unlinkedCount++;
        }

        // Handle SyncStatus records (legacy system)
        $syncStatus = $this->product->syncStatuses
            ->where('sync_account_id', $syncAccountId)
            ->first();

        if ($syncStatus && $syncStatus->external_product_id) {
            $externalId = $syncStatus->external_product_id;
            if (! in_array($externalId, $externalIds)) {
                $externalIds[] = $externalId;
            }

            // Update sync status to unlinked
            $syncStatus->update([
                'external_product_id' => null,
                'external_variant_id' => null,
                'external_handle' => null,
                'sync_status' => 'pending',
                'metadata' => array_merge($syncStatus->metadata ?? [], [
                    'unlinked_manually' => true,
                    'unlinked_at' => now()->toISOString(),
                    'previous_external_id' => $externalId,
                ]),
            ]);

            $unlinkedCount++;
        }

        // Log the unlinking action if we found anything to unlink
        if ($unlinkedCount > 0) {
            $externalIdList = ! empty($externalIds) ? implode(', ', $externalIds) : 'Unknown';

            // Log using SyncStatus if available, otherwise create a basic log
            if ($syncStatus) {
                \App\Models\SyncLog::createEntry($syncAccount, 'unlink', $this->product, $syncStatus)
                    ->markAsSuccessful("Product unlinked from external IDs: {$externalIdList}");
            } else {
                \App\Models\SyncLog::createEntry($syncAccount, 'unlink', $this->product)
                    ->markAsSuccessful("MarketplaceLinks unlinked from external IDs: {$externalIdList}");
            }

            $message = $unlinkedCount === 1
                ? "Product unlinked from {$syncAccount->channel} listing! 🔓"
                : "Product unlinked from {$unlinkedCount} {$syncAccount->channel} links! 🔓";

            $this->dispatch('success', $message);

            // Refresh the product data
            $this->mount($this->product->fresh());
        } else {
            $this->dispatch('info', "No active links found for {$syncAccount->channel} to unlink.");
        }
    }

    /**
     * 🎨 Show Shopify color linking modal
     */
    public function showShopifyColorLinking(int $syncAccountId)
    {
        $syncAccount = SyncAccount::findOrFail($syncAccountId);

        if ($syncAccount->channel !== 'shopify') {
            $this->dispatch('error', 'Color linking is only available for Shopify accounts.');

            return;
        }

        $this->linkingAccountId = $syncAccountId;
        $this->colorMappings = [];
        $this->shopifyProducts = [];
        $this->showShopifyColorModal = true;

        // Initialize color mappings with product colors
        $colors = $this->product->variants->pluck('color')->unique()->filter()->values()->toArray();
        foreach ($colors as $color) {
            $this->colorMappings[$color] = '';
        }

        $this->discoverShopifyProducts();
    }

    /**
     * 🔍 Discover Shopify products for linking
     */
    public function discoverShopifyProducts()
    {
        $this->discoveryLoading = true;

        try {
            $syncAccount = SyncAccount::findOrFail($this->linkingAccountId);
            $discoveryService = new ShopifyProductDiscoveryService($syncAccount);

            $suggestions = $discoveryService->getColorLinkingSuggestions($this->product);
            $this->shopifyProducts = $discoveryService->discoverProducts(100)->toArray();

            // Auto-populate suggestions if found
            foreach ($suggestions as $suggestion) {
                $color = $suggestion['color'];
                if (isset($this->colorMappings[$color]) && ! empty($suggestion['suggested_products'])) {
                    $bestMatch = $suggestion['suggested_products'][0];
                    $this->colorMappings[$color] = $bestMatch['id'];
                }
            }

            $this->dispatch('success', 'Shopify products discovered! Smart suggestions applied.');

        } catch (\Exception $e) {
            \Log::error('Failed to discover Shopify products', [
                'error' => $e->getMessage(),
                'product_id' => $this->product->id,
                'sync_account_id' => $this->linkingAccountId,
            ]);

            $this->dispatch('error', 'Failed to discover Shopify products: '.$e->getMessage());
        }

        $this->discoveryLoading = false;
    }

    /**
     * 🔗 Link colors to Shopify products
     */
    public function linkShopifyColors()
    {
        // Build validation rules dynamically for both regular mappings and custom inputs
        $validationRules = [
            'colorMappings' => 'required|array|min:1',
        ];

        // Check that at least one color has a valid selection
        $hasValidSelection = false;
        foreach ($this->colorMappings as $color => $value) {
            if ($value === 'custom') {
                $customProperty = "colorMappings.{$color}_custom";
                $customValue = data_get($this, $customProperty);
                if (! empty($customValue)) {
                    $hasValidSelection = true;
                    $validationRules["colorMappings.{$color}_custom"] = 'required|string|min:1';
                }
            } elseif (! empty($value)) {
                $hasValidSelection = true;
                // Don't add validation rule - we'll check this in the logic below
            }
        }

        // If no colors selected, show a general error
        if (! $hasValidSelection) {
            $this->addError('colorMappings', 'Please select at least one color to link.');

            return;
        }

        $this->validate($validationRules);

        $syncAccount = SyncAccount::findOrFail($this->linkingAccountId);
        $linkedCount = 0;

        try {
            \Log::info('🔗 Starting Shopify color linking', [
                'product_id' => $this->product->id,
                'sync_account_id' => $syncAccount->id,
                'color_mappings' => $this->colorMappings,
            ]);

            foreach ($this->colorMappings as $color => $shopifyProductId) {
                // Handle custom input
                if ($shopifyProductId === 'custom') {
                    $customProperty = "colorMappings.{$color}_custom";
                    $actualProductId = data_get($this, $customProperty);

                    if (empty($actualProductId)) {
                        \Log::info("⏭️ Skipping color '{$color}' - custom input empty");

                        continue;
                    }

                    $shopifyProductId = $actualProductId;
                    \Log::info("🔧 Using custom product ID for '{$color}': {$shopifyProductId}");
                } elseif (empty($shopifyProductId)) {
                    \Log::info("⏭️ Skipping color '{$color}' - dropdown empty");

                    continue;
                } else {
                    \Log::info("📋 Using dropdown selection for '{$color}': {$shopifyProductId}");
                }

                // Check for existing link to prevent duplicates
                $existingLink = $this->product->marketplaceLinks()
                    ->where('sync_account_id', $syncAccount->id)
                    ->where('external_product_id', $shopifyProductId)
                    ->whereJsonContains('marketplace_data->color_filter', $color)
                    ->first();

                if ($existingLink) {
                    \Log::info("⏭️ Skipping color '{$color}' - link already exists", [
                        'existing_link_id' => $existingLink->id,
                    ]);

                    continue;
                }

                // Create MarketplaceLink for this color
                $link = MarketplaceLink::create([
                    'linkable_type' => Product::class,
                    'linkable_id' => $this->product->id,
                    'sync_account_id' => $syncAccount->id,
                    'internal_sku' => $this->product->parent_sku,
                    'external_sku' => $this->product->parent_sku,
                    'external_product_id' => $shopifyProductId,
                    'link_status' => 'linked',
                    'link_level' => 'product',
                    'marketplace_data' => [
                        'color_filter' => $color,
                        'linked_at' => now()->toISOString(),
                        'linked_method' => 'color_linking_modal',
                        'input_method' => $this->colorMappings[$color] === 'custom' ? 'custom' : 'dropdown',
                    ],
                    'linked_at' => now(),
                    'linked_by' => auth()->user()?->name ?? 'system',
                ]);

                $linkedCount++;

                \Log::info('Created color-based MarketplaceLink', [
                    'product_id' => $this->product->id,
                    'color' => $color,
                    'shopify_product_id' => $shopifyProductId,
                    'input_method' => $this->colorMappings[$color] === 'custom' ? 'custom' : 'dropdown',
                    'link_id' => $link->id,
                ]);
            }

            // Also update/create SyncStatus for backward compatibility
            $syncStatus = \App\Models\SyncStatus::findOrCreateFor($this->product, $syncAccount);
            $syncStatus->update([
                'sync_status' => 'synced',
                'last_synced_at' => now(),
                'metadata' => array_merge($syncStatus->metadata ?? [], [
                    'color_links_created' => $linkedCount,
                    'colors_linked' => array_keys($this->colorMappings),
                    'linked_at' => now()->toISOString(),
                ]),
            ]);

            if ($linkedCount > 0) {
                $this->dispatch('success', "Successfully linked {$linkedCount} colors to Shopify products! 🎨");
            } else {
                $this->dispatch('info', 'No new color links were created. All selected colors may already be linked.');
            }

            $this->closeShopifyColorModal();

            // Refresh the product data
            $this->mount($this->product->fresh());

        } catch (\Exception $e) {
            \Log::error('Failed to link Shopify colors', [
                'error' => $e->getMessage(),
                'product_id' => $this->product->id,
                'color_mappings' => $this->colorMappings,
            ]);

            $this->dispatch('error', 'Failed to link colors: '.$e->getMessage());
        }
    }

    /**
     * 🚪 Close Shopify color linking modal
     */
    public function closeShopifyColorModal()
    {
        $this->showShopifyColorModal = false;
        $this->linkingAccountId = null;
        $this->shopifyProducts = [];
        $this->colorMappings = [];
        $this->discoveryLoading = false;
    }

    /**
     * 📝 Show edit links modal
     */
    public function showEditLinksModal(int $syncAccountId)
    {
        $this->editingAccountId = $syncAccountId;
        $this->newExternalId = '';
        $this->loadExistingLinks($syncAccountId);
        $this->showEditLinksModal = true;
    }

    /**
     * 🚪 Close edit links modal
     */
    public function closeEditLinksModal()
    {
        $this->showEditLinksModal = false;
        $this->editingAccountId = null;
        $this->existingLinks = [];
        $this->newExternalId = '';
    }

    /**
     * 🚪 Close edit links modal and refresh data
     */
    public function closeEditLinksModalAndRefresh()
    {
        $this->closeEditLinksModal();
        $this->mount($this->product->fresh());
    }

    /**
     * 📋 Load existing links for a sync account
     */
    private function loadExistingLinks(int $syncAccountId)
    {
        // Get MarketplaceLinks
        $marketplaceLinks = $this->product->marketplaceLinks()
            ->where('sync_account_id', $syncAccountId)
            ->get()
            ->map(function ($link) {
                return [
                    'id' => $link->id,
                    'type' => 'marketplace_link',
                    'external_product_id' => $link->external_product_id,
                    'external_variant_id' => $link->external_variant_id,
                    'link_status' => $link->link_status,
                    'link_level' => $link->link_level,
                    'color_filter' => $link->marketplace_data['color_filter'] ?? null,
                    'linked_at' => $link->linked_at,
                    'editable_external_id' => $link->external_product_id, // For editing
                ];
            });

        // Get SyncStatus (legacy system)
        $syncStatus = $this->product->syncStatuses()
            ->where('sync_account_id', $syncAccountId)
            ->first();

        $legacyLinks = collect();
        if ($syncStatus && $syncStatus->external_product_id) {
            $legacyLinks->push([
                'id' => $syncStatus->id,
                'type' => 'sync_status',
                'external_product_id' => $syncStatus->external_product_id,
                'external_variant_id' => $syncStatus->external_variant_id,
                'link_status' => $syncStatus->sync_status,
                'link_level' => 'product',
                'color_filter' => null,
                'linked_at' => $syncStatus->last_synced_at,
                'editable_external_id' => $syncStatus->external_product_id, // For editing
            ]);
        }

        $this->existingLinks = $marketplaceLinks->concat($legacyLinks)->toArray();
    }

    /**
     * 💾 Update external product ID for a link
     */
    public function updateLinkExternalId(int $index)
    {
        if (! isset($this->existingLinks[$index])) {
            $this->dispatch('error', 'Link not found');

            return;
        }

        $link = $this->existingLinks[$index];
        $newExternalId = $this->existingLinks[$index]['editable_external_id'] ?? '';

        if (empty($newExternalId)) {
            $this->dispatch('error', 'External ID cannot be empty');

            return;
        }

        try {
            if ($link['type'] === 'marketplace_link') {
                $marketplaceLink = MarketplaceLink::findOrFail($link['id']);
                $marketplaceLink->update([
                    'external_product_id' => $newExternalId,
                    'link_status' => 'linked', // Ensure it's marked as linked
                ]);
            } elseif ($link['type'] === 'sync_status') {
                $syncStatus = SyncStatus::findOrFail($link['id']);
                $syncStatus->update([
                    'external_product_id' => $newExternalId,
                    'sync_status' => 'synced', // Ensure it's marked as synced
                ]);
            }

            // Update the local array for immediate UI feedback
            $this->existingLinks[$index]['external_product_id'] = $newExternalId;
            $this->existingLinks[$index]['editable_external_id'] = $newExternalId;

            $this->dispatch('success', 'External ID updated successfully! 📝');

        } catch (\Exception $e) {
            \Log::error('Failed to update link external ID', [
                'link_id' => $link['id'],
                'link_type' => $link['type'],
                'new_external_id' => $newExternalId,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('error', 'Failed to update external ID: '.$e->getMessage());
        }
    }

    /**
     * 🗑️ Remove a specific link
     */
    public function removeLinkById(int $index)
    {
        if (! isset($this->existingLinks[$index])) {
            $this->dispatch('error', 'Link not found');

            return;
        }

        $link = $this->existingLinks[$index];

        try {
            if ($link['type'] === 'marketplace_link') {
                $marketplaceLink = MarketplaceLink::findOrFail($link['id']);
                $marketplaceLink->delete();
            } elseif ($link['type'] === 'sync_status') {
                $syncStatus = SyncStatus::findOrFail($link['id']);
                $syncStatus->update([
                    'external_product_id' => null,
                    'external_variant_id' => null,
                    'sync_status' => 'pending',
                    'metadata' => array_merge($syncStatus->metadata ?? [], [
                        'unlinked_manually' => true,
                        'unlinked_at' => now()->toISOString(),
                        'previous_external_id' => $link['external_product_id'],
                    ]),
                ]);
            }

            // Remove from local array for immediate UI feedback
            array_splice($this->existingLinks, $index, 1);

            $this->dispatch('success', 'Link removed successfully! 🗑️');

        } catch (\Exception $e) {
            \Log::error('Failed to remove link', [
                'link_id' => $link['id'],
                'link_type' => $link['type'],
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('error', 'Failed to remove link: '.$e->getMessage());
        }
    }

    /**
     * ➕ Add a new link to this account
     */
    public function addNewLink()
    {
        $this->validate([
            'newExternalId' => 'required|string|min:1',
        ]);

        try {
            $syncAccount = SyncAccount::findOrFail($this->editingAccountId);

            // Create a new MarketplaceLink
            $newLink = MarketplaceLink::create([
                'linkable_type' => Product::class,
                'linkable_id' => $this->product->id,
                'sync_account_id' => $syncAccount->id,
                'internal_sku' => $this->product->parent_sku,
                'external_sku' => $this->product->parent_sku,
                'external_product_id' => $this->newExternalId,
                'link_status' => 'linked',
                'link_level' => 'product',
                'marketplace_data' => [
                    'linked_manually' => true,
                    'linked_at' => now()->toISOString(),
                    'linked_via' => 'edit_modal',
                ],
                'linked_at' => now(),
                'linked_by' => auth()->user()?->name ?? 'system',
            ]);

            // Add to local array for immediate UI feedback
            $this->existingLinks[] = [
                'id' => $newLink->id,
                'type' => 'marketplace_link',
                'external_product_id' => $newLink->external_product_id,
                'external_variant_id' => $newLink->external_variant_id,
                'link_status' => $newLink->link_status,
                'link_level' => $newLink->link_level,
                'color_filter' => $newLink->marketplace_data['color_filter'] ?? null,
                'linked_at' => $newLink->linked_at,
                'editable_external_id' => $newLink->external_product_id,
            ];

            $this->newExternalId = '';

            $this->dispatch('success', 'New link added successfully! ➕');

        } catch (\Exception $e) {
            \Log::error('Failed to add new link', [
                'sync_account_id' => $this->editingAccountId,
                'external_id' => $this->newExternalId,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('error', 'Failed to add link: '.$e->getMessage());
        }
    }

    /**
     * 🔓 Unlink specific color from Shopify
     */
    public function unlinkShopifyColor(int $syncAccountId, string $color)
    {
        $syncAccount = SyncAccount::findOrFail($syncAccountId);

        $link = $this->product->marketplaceLinks()
            ->where('sync_account_id', $syncAccountId)
            ->whereJsonContains('marketplace_data->color_filter', $color)
            ->first();

        if ($link) {
            $externalProductId = $link->external_product_id;
            $link->delete();

            $this->dispatch('success', "Unlinked {$color} from Shopify product {$externalProductId}! 🔓");

            // Refresh the product data
            $this->mount($this->product->fresh());
        } else {
            $this->dispatch('info', "No link found for {$color} color.");
        }
    }

    /**
     * ✏️ Update Shopify color link external product ID
     */
    public function updateShopifyColorLink(int $syncAccountId, string $color, string $newExternalProductId)
    {
        $syncAccount = SyncAccount::findOrFail($syncAccountId);

        $link = $this->product->marketplaceLinks()
            ->where('sync_account_id', $syncAccountId)
            ->whereJsonContains('marketplace_data->color_filter', $color)
            ->first();

        if ($link) {
            $oldProductId = $link->external_product_id;

            $link->update([
                'external_product_id' => $newExternalProductId,
                'link_status' => 'linked',
                'linked_at' => now(),
                'marketplace_data' => array_merge($link->marketplace_data ?? [], [
                    'updated_at' => now()->toISOString(),
                    'updated_manually' => true,
                    'previous_external_id' => $oldProductId,
                ]),
            ]);

            $this->dispatch('success', "Updated {$color} link from product {$oldProductId} to {$newExternalProductId}! ✏️");

            // Refresh the product data
            $this->mount($this->product->fresh());
        } else {
            $this->dispatch('error', "No link found for {$color} color to update.");
        }
    }

    /**
     * 🎨 Get individual color link details for management
     */
    public function getColorLinkDetails(int $syncAccountId, string $color)
    {
        $link = $this->product->marketplaceLinks()
            ->where('sync_account_id', $syncAccountId)
            ->whereJsonContains('marketplace_data->color_filter', $color)
            ->first();

        if ($link) {
            return [
                'id' => $link->id,
                'color' => $color,
                'external_product_id' => $link->external_product_id,
                'external_variant_id' => $link->external_variant_id,
                'link_status' => $link->link_status,
                'linked_at' => $link->linked_at,
                'marketplace_data' => $link->marketplace_data,
            ];
        }

        return null;
    }

    /**
     * 🔄 Refresh color links for a specific account
     */
    public function refreshColorLinks(int $syncAccountId)
    {
        try {
            $syncAccount = SyncAccount::findOrFail($syncAccountId);

            if ($syncAccount->channel !== 'shopify') {
                $this->dispatch('error', 'Color link refresh is only available for Shopify accounts.');

                return;
            }

            // Get updated color links
            $colorLinks = $this->getShopifyColorLinks($syncAccountId);
            $linkCount = $colorLinks->count();

            // Log the refresh action
            \App\Models\SyncLog::createEntry($syncAccount, 'color_refresh', $this->product)
                ->markAsSuccessful("Refreshed {$linkCount} color links");

            $this->dispatch('success', "Refreshed {$linkCount} Shopify color links! 🔄");

            // Refresh the product data
            $this->mount($this->product->fresh());

        } catch (\Exception $e) {
            \Log::error('Failed to refresh color links', [
                'error' => $e->getMessage(),
                'product_id' => $this->product->id,
                'sync_account_id' => $syncAccountId,
            ]);

            $this->dispatch('error', 'Failed to refresh color links: '.$e->getMessage());
        }
    }

    /**
     * 🎨 Get Shopify color links for an account
     */
    public function getShopifyColorLinks(int $syncAccountId)
    {
        return $this->product->marketplaceLinks()
            ->where('sync_account_id', $syncAccountId)
            ->whereNotNull('marketplace_data->color_filter') // Any color filter exists
            ->get()
            ->map(function ($link) {
                return [
                    'color' => $link->marketplace_data['color_filter'] ?? 'Unknown',
                    'shopify_product_id' => $link->external_product_id,
                    'link_id' => $link->id,
                    'linked_at' => $link->linked_at,
                ];
            });
    }

    /**
     * 💰 Update Shopify pricing for linked colors
     */
    public function updateShopifyPricing(int $syncAccountId, array $options = [])
    {
        // Get Shopify sync account
        $syncAccount = SyncAccount::find($syncAccountId);

        if (! $syncAccount || $syncAccount->channel !== 'shopify') {
            $this->dispatch('error', 'Invalid Shopify account selected');

            return;
        }

        // Check if product has MarketplaceLinks for this account
        $linkedColorsCount = $this->product->marketplaceLinks()
            ->where('sync_account_id', $syncAccountId)
            ->where('link_level', 'product')
            ->whereNotNull('marketplace_data->color_filter')
            ->count();

        if ($linkedColorsCount === 0) {
            $this->dispatch('error', 'No color links found for this Shopify account. Link colors first.');

            return;
        }

        // Dispatch the pricing update job
        \App\Jobs\UpdateShopifyPricingJob::dispatch($this->product, $syncAccount, $options);

        $this->dispatch('success', "Shopify pricing update initiated! 💰 Updating pricing for {$linkedColorsCount} linked colors.");

        // Refresh the product data to show updated sync logs
        $this->mount($this->product->fresh());
    }

    public function getAvailableAccountsProperty()
    {
        return SyncAccount::with(['syncStatuses' => function ($query) {
            $query->where('product_id', $this->product->id);
        }])
            ->where('is_active', true)
            ->get()
            ->map(function ($account) {
                // Get SyncStatus (legacy system)
                $syncStatus = $this->product->syncStatuses
                    ->where('sync_account_id', $account->id)
                    ->first();

                // Get MarketplaceLinks (new system)
                $marketplaceLinks = $this->product->marketplaceLinks()
                    ->where('sync_account_id', $account->id)
                    ->get();

                // Get Shopify color links if it's a Shopify account
                $colorLinks = ($account->channel === 'shopify')
                    ? $this->getShopifyColorLinks($account->id)
                    : collect();

                // Determine consolidated linking status
                $hasMarketplaceLinks = $marketplaceLinks->isNotEmpty();
                $hasSyncStatusLink = $syncStatus && $syncStatus->external_product_id;

                // Priority: MarketplaceLinks > SyncStatus > Color Links (for Shopify)
                $isLinked = $hasMarketplaceLinks || $hasSyncStatusLink || ($account->channel === 'shopify' && $colorLinks->isNotEmpty());

                // Determine consolidated status
                $consolidatedStatus = 'not_synced';
                if ($hasMarketplaceLinks) {
                    $latestLink = $marketplaceLinks->sortByDesc('linked_at')->first();
                    $consolidatedStatus = match ($latestLink->link_status) {
                        'linked' => 'synced',
                        'pending' => 'pending',
                        'failed' => 'failed',
                        'unlinked' => 'not_synced',
                        default => 'pending'
                    };
                } elseif ($syncStatus) {
                    $consolidatedStatus = $syncStatus->sync_status;
                }

                // Determine last sync time from multiple sources
                $lastSyncTimes = collect();
                if ($syncStatus && $syncStatus->last_synced_at) {
                    $lastSyncTimes->push($syncStatus->last_synced_at);
                }
                $marketplaceLinks->each(function ($link) use ($lastSyncTimes) {
                    if ($link->linked_at) {
                        $lastSyncTimes->push($link->linked_at);
                    }
                });

                $lastSync = $lastSyncTimes->isNotEmpty()
                    ? $lastSyncTimes->max()->diffForHumans()
                    : 'Never';

                // Check if manually linked
                $isManuallyLinked = false;
                if ($hasMarketplaceLinks) {
                    $isManuallyLinked = $marketplaceLinks->some(function ($link) {
                        return ($link->marketplace_data['linked_manually'] ?? false) ||
                               ($link->marketplace_data['linked_via'] ?? '') === 'link_modal';
                    });
                } elseif ($syncStatus) {
                    $isManuallyLinked = $syncStatus->metadata['linked_manually'] ?? false;
                }

                return (object) [
                    'account' => $account,
                    'syncStatus' => $syncStatus,
                    'marketplaceLinks' => $marketplaceLinks,
                    'needsSync' => $syncStatus ? $syncStatus->needsSync() : true,
                    'lastSync' => $lastSync,
                    'status' => $consolidatedStatus,
                    'isLinked' => $isLinked,
                    'linkingType' => $isManuallyLinked ? 'manual' : 'auto',
                    'colorLinks' => $colorLinks,
                    'hasColorLinks' => $colorLinks->isNotEmpty(),
                    'linkSources' => [
                        'marketplace_links' => $hasMarketplaceLinks,
                        'sync_status' => $hasSyncStatusLink,
                        'color_links' => $colorLinks->isNotEmpty(),
                    ],
                ];
            });
    }

    /**
     * 🔄 Synchronize status between MarketplaceLink and SyncStatus systems
     */
    public function synchronizeSystemStatus(int $syncAccountId)
    {
        try {
            $syncAccount = SyncAccount::findOrFail($syncAccountId);
            $updated = 0;

            // Get all MarketplaceLinks for this account
            $marketplaceLinks = $this->product->marketplaceLinks()
                ->where('sync_account_id', $syncAccountId)
                ->get();

            // Get or create SyncStatus
            $syncStatus = \App\Models\SyncStatus::findOrCreateFor($this->product, $syncAccount);

            if ($marketplaceLinks->isNotEmpty()) {
                // Update SyncStatus based on MarketplaceLinks
                $latestLink = $marketplaceLinks->sortByDesc('linked_at')->first();

                $syncStatusValue = match ($latestLink->link_status) {
                    'linked' => 'synced',
                    'pending' => 'pending',
                    'failed' => 'failed',
                    'unlinked' => 'pending',
                    default => 'pending'
                };

                $syncStatus->update([
                    'external_product_id' => $latestLink->external_product_id,
                    'external_variant_id' => $latestLink->external_variant_id,
                    'sync_status' => $syncStatusValue,
                    'last_synced_at' => $latestLink->linked_at,
                    'metadata' => array_merge($syncStatus->metadata ?? [], [
                        'synchronized_at' => now()->toISOString(),
                        'synchronized_from' => 'marketplace_link',
                        'marketplace_link_id' => $latestLink->id,
                    ]),
                ]);
                $updated++;

            } elseif ($syncStatus->external_product_id) {
                // Create MarketplaceLink based on SyncStatus if none exist
                $marketplaceLink = MarketplaceLink::create([
                    'linkable_type' => Product::class,
                    'linkable_id' => $this->product->id,
                    'sync_account_id' => $syncAccount->id,
                    'internal_sku' => $this->product->parent_sku,
                    'external_sku' => $this->product->parent_sku,
                    'external_product_id' => $syncStatus->external_product_id,
                    'external_variant_id' => $syncStatus->external_variant_id,
                    'link_status' => match ($syncStatus->sync_status) {
                        'synced' => 'linked',
                        'pending' => 'pending',
                        'failed' => 'failed',
                        default => 'pending'
                    },
                    'link_level' => 'product',
                    'linked_at' => $syncStatus->last_synced_at,
                    'linked_by' => 'system_sync',
                    'marketplace_data' => [
                        'synchronized_at' => now()->toISOString(),
                        'synchronized_from' => 'sync_status',
                        'sync_status_id' => $syncStatus->id,
                    ],
                ]);

                // Update SyncStatus metadata to reference the new MarketplaceLink
                $syncStatus->update([
                    'metadata' => array_merge($syncStatus->metadata ?? [], [
                        'synchronized_at' => now()->toISOString(),
                        'marketplace_link_id' => $marketplaceLink->id,
                    ]),
                ]);
                $updated++;
            }

            if ($updated > 0) {
                $this->dispatch('success', "System status synchronized! 🔄 Updated {$updated} records.");

                // Log the synchronization
                \App\Models\SyncLog::createEntry($syncAccount, 'sync_systems', $this->product, $syncStatus)
                    ->markAsSuccessful('Synchronized MarketplaceLink and SyncStatus systems');
            } else {
                $this->dispatch('info', 'No synchronization needed - systems are already consistent.');
            }

            // Refresh the product data
            $this->mount($this->product->fresh());

        } catch (\Exception $e) {
            \Log::error('Failed to synchronize system status', [
                'error' => $e->getMessage(),
                'product_id' => $this->product->id,
                'sync_account_id' => $syncAccountId,
            ]);

            $this->dispatch('error', 'Failed to synchronize systems: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.marketplace.marketplace-sync-cards');
    }
}
