<div class="space-y-6">
    {{-- Header --}}
    <x-marketplace.sync-header :accounts-count="$this->availableAccounts->count()" />

    {{-- Sync Cards Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($this->availableAccounts as $item)
            <x-marketplace.sync-card :item="$item" />
        @empty
            <flux:card class="col-span-full">
                <div class="text-center py-8">
                    <flux:icon name="link" class="w-12 h-12 text-gray-300 mx-auto mb-4" />
                    <h4 class="text-lg font-medium text-gray-900 mb-2">No Marketplace Accounts</h4>
                    <p class="text-gray-500 mb-4">Set up marketplace integrations to sync this product across channels.</p>
                    <flux:button variant="filled" href="{{ route('sync-accounts.index') }}">
                        Setup Integrations
                    </flux:button>
                </div>
            </flux:card>
        @endforelse
    </div>

    {{-- Recent Sync Logs --}}
    <x-marketplace.recent-sync-logs :product="$product" />

    {{-- Modals --}}
    <x-marketplace.linking-modal 
        :show-linking-modal="$showLinkingModal" 
        :linking-account-id="$linkingAccountId" 
        :available-accounts="$this->availableAccounts" 
        :external-product-id="$externalProductId" />


    <x-marketplace.edit-links-modal 
        :show-edit-links-modal="$showEditLinksModal"
        :editing-account-id="$editingAccountId"
        :available-accounts="$this->availableAccounts"
        :existing-links="$existingLinks"
        :new-external-id="$newExternalId" />
</div>