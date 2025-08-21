@props(['showEditLinksModal', 'editingAccountId', 'availableAccounts', 'existingLinks', 'newExternalId'])

@if($showEditLinksModal)
    <flux:modal wire:model="showEditLinksModal" class="max-w-3xl">
        <div class="p-6">
            <h3>Test Modal</h3>

            @if($editingAccountId)
                @php
                    $account = $availableAccounts->firstWhere('account.id', $editingAccountId)?->account;
                @endphp
                
                @if($account)
                    <div class="mb-6">
                        <span>Account: {{ $account->channel }}</span>
                    </div>

                    <div class="space-y-6">
                        @if(!empty($existingLinks))
                            <div>
                                <h4>Existing Links</h4>
                                <p>Links found</p>
                            </div>
                        @else
                            <div>
                                <p>No links found</p>
                            </div>
                        @endif

                        <div>
                            <h4>Add New Link</h4>
                            <p>Form here</p>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </flux:modal>
@endif