<div class="max-w-3xl mx-auto p-6 space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">
            {{ $mode === 'edit' ? 'Edit Sync Account' : 'Create Sync Account' }}
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:field>
                <flux:label>Channel</flux:label>
                <flux:select wire:model.live="channel">
                    <flux:select.option value="" disabled>Select a marketplace</flux:select.option>
                    @foreach($marketplaces as $template)
                        <flux:select.option value="{{ $template->type }}">{{ $template->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if($errors->has('channel'))
                    <p class="text-sm text-red-600 mt-1">{{ $errors->first('channel') }}</p>
                @endif
            </flux:field>

            <flux:field>
                <flux:label>Account Key (name)</flux:label>
                <flux:input wire:model.live="name" placeholder="main, uk, etc." />
            </flux:field>

            <flux:field>
                <flux:label>Display Name</flux:label>
                <flux:input wire:model.live="display_name" placeholder="My Store" />
            </flux:field>

            @if($channel === 'mirakl')
                <flux:field>
                    <flux:label>Operator (optional)</flux:label>
                    <flux:input wire:model.live="operator" placeholder="freemans, debenhams, bq" />
                </flux:field>
            @endif
        </div>

        <div class="mt-6">
            <h3 class="text-md font-medium text-gray-900 mb-3">Credentials</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($requiredFields as $field)
                    @php
                        $meta = $credentialFieldMeta[$field] ?? ['label' => ucwords(str_replace('_',' ',$field)), 'type' => 'text'];
                        $type = $meta['type'] ?? 'text';
                        $label = $meta['label'] ?? ucwords(str_replace('_',' ',$field));
                    @endphp
                    <flux:field>
                        <flux:label>{{ $label }}</flux:label>
                        <flux:input wire:model.live="credentials.{{ $field }}" type="{{ $type }}" />
                        @if(isset($errorsBag[$field]))
                            <p class="text-sm text-red-600">{{ $errorsBag[$field][0] }}</p>
                        @endif
                    </flux:field>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-gray-500">Sensitive fields (API keys/tokens) are stored securely and masked.</p>
        </div>

        <div class="mt-6">
            <h3 class="text-md font-medium text-gray-900 mb-3">Settings (optional)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Currency</flux:label>
                    <flux:input wire:model.live="settings.currency" placeholder="GBP" />
                </flux:field>
                <flux:field>
                    <flux:label>Category Code</flux:label>
                    <flux:input wire:model.live="settings.category_code" placeholder="H02" />
                </flux:field>
                <flux:field>
                    <flux:label>Default State</flux:label>
                    <flux:input wire:model.live="settings.default_state" placeholder="11" />
                </flux:field>
                <flux:field>
                    <flux:label>Logistic Class</flux:label>
                    <flux:input wire:model.live="settings.logistic_class" placeholder="STD" />
                </flux:field>
                <flux:field>
                    <flux:label>Leadtime To Ship</flux:label>
                    <flux:input wire:model.live="settings.leadtime_to_ship" placeholder="3" />
                </flux:field>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-between">
            <div class="space-x-2">
                <flux:button wire:click="save" variant="filled">
                    {{ $mode === 'edit' ? 'Save Changes' : 'Create Account' }}
                </flux:button>
                @if($mode === 'edit')
                    <flux:button wire:click="testConnection" variant="outline">
                        Test Connection
                    </flux:button>
                @endif
            </div>
            <div>
                <a href="{{ route('sync-accounts.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Back to list</a>
            </div>
        </div>

        @if($lastTest)
            <div class="mt-4 bg-gray-50 rounded-lg p-4 border">
                <div class="flex items-center gap-2">
                    @if(($lastTest['success'] ?? false))
                        <flux:icon name="check-circle" class="w-4 h-4 text-green-600" />
                        <span class="text-sm text-green-700">Last connection test succeeded</span>
                    @else
                        <flux:icon name="x-circle" class="w-4 h-4 text-red-600" />
                        <span class="text-sm text-red-700">Last connection test failed</span>
                    @endif
                    @if($lastTestAt)
                        <span class="text-xs text-gray-500">({{ $lastTestAt->diffForHumans() }})</span>
                    @endif
                </div>
                @if(!($lastTest['success'] ?? false) && !empty($lastTest['error'] ?? null))
                    <p class="mt-2 text-xs text-gray-600">{{ $lastTest['error'] }}</p>
                @endif
            </div>
        @endif
    </div>
</div>
