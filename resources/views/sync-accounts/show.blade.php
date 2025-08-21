@php($syncAccount = $syncAccount ?? null)

<x-layouts.app>
    <div class="max-w-4xl mx-auto p-6">
        @if($syncAccount)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900">{{ $syncAccount->display_name }}</h1>
                            <p class="mt-1 text-sm text-gray-600">{{ ucfirst($syncAccount->channel) }} sync account details</p>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            @if($syncAccount->is_active)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <div class="w-1.5 h-1.5 bg-green-400 rounded-full mr-1.5"></div>
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    <div class="w-1.5 h-1.5 bg-gray-400 rounded-full mr-1.5"></div>
                                    Inactive
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-6 space-y-6">
                    <!-- Basic Information -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Basic Information</h3>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Channel</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($syncAccount->channel) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Marketplace Type</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $syncAccount->marketplace_subtype }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Created</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $syncAccount->created_at->format('M j, Y \a\t g:i A') }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Last Sync</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if($syncAccount->last_sync_at)
                                        {{ $syncAccount->last_sync_at->diffForHumans() }}
                                    @else
                                        <span class="text-gray-400">Never</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Configuration -->
                    @if($syncAccount->credentials)
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Configuration</h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <dl class="space-y-3">
                                    @foreach($syncAccount->credentials as $key => $value)
                                        @if(!in_array($key, ['access_token', 'client_secret']))
                                            <div class="flex justify-between">
                                                <dt class="text-sm font-medium text-gray-500">{{ ucwords(str_replace('_', ' ', $key)) }}</dt>
                                                <dd class="text-sm text-gray-900">{{ $value }}</dd>
                                            </div>
                                        @else
                                            <div class="flex justify-between">
                                                <dt class="text-sm font-medium text-gray-500">{{ ucwords(str_replace('_', ' ', $key)) }}</dt>
                                                <dd class="text-sm text-gray-900">••••••••</dd>
                                            </div>
                                        @endif
                                    @endforeach
                                </dl>
                            </div>
                        </div>
                    @endif

                    <!-- Connection Test Results -->
                    @if($syncAccount->settings && isset($syncAccount->settings['connection_test']))
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Last Connection Test</h3>
                            <div class="bg-green-50 rounded-lg p-4">
                                <div class="flex items-start space-x-3">
                                    <flux:icon name="check-circle" class="w-5 h-5 text-green-500 mt-0.5" />
                                    <div>
                                        <p class="text-sm font-medium text-green-900">Connection Successful</p>
                                        <p class="text-sm text-green-700">
                                            Tested {{ \Carbon\Carbon::parse($syncAccount->settings['connection_test']['tested_at'])->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Actions -->
                <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                    <a href="{{ route('sync-accounts.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                        ← Back to Sync Accounts
                    </a>
                    
                    <div class="flex items-center space-x-3">
                        <button 
                            class="inline-flex items-center px-3 py-1.5 text-sm text-blue-600 hover:text-blue-800"
                            onclick="alert('Edit functionality coming soon!')"
                        >
                            <flux:icon name="edit" class="w-4 h-4 mr-1" />
                            Edit
                        </button>
                        
                        <button 
                            class="inline-flex items-center px-3 py-1.5 text-sm text-red-600 hover:text-red-800"
                            onclick="if(confirm('Are you sure you want to delete this sync account?')) { alert('Delete functionality coming soon!'); }"
                        >
                            <flux:icon name="trash-2" class="w-4 h-4 mr-1" />
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        @else
            <div class="text-center py-12">
                <flux:icon name="x-circle" class="w-12 h-12 mx-auto text-red-400 mb-4" />
                <h3 class="text-lg font-medium text-gray-900 mb-2">Sync Account Not Found</h3>
                <p class="text-gray-600 mb-6">The requested sync account could not be found.</p>
                <a 
                    href="{{ route('sync-accounts.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    ← Back to Sync Accounts
                </a>
            </div>
        @endif
    </div>
</x-layouts.app>