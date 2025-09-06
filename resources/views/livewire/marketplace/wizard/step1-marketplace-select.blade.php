<flux:card class="p-6">
    <flux:heading size="lg" class="mb-4">Choose Your Marketplace</flux:heading>
    <p class="text-gray-600 mb-6">Select the marketplace you want to integrate with your product catalog.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($availableMarketplaces as $marketplace)
            <button wire:click="choose('{{ $marketplace['type'] }}')"
                    class="p-4 border-2 border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 text-left group">
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0">
                        @if(!empty($marketplace['logo_url']))
                            <img src="{{ $marketplace['logo_url'] }}" alt="{{ $marketplace['name'] }}" class="w-12 h-12 object-contain">
                        @else
                            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                                <flux:icon.store class="w-6 h-6 text-gray-500" />
                            </div>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-semibold text-gray-900 group-hover:text-blue-600">
                            {{ $marketplace['name'] }}
                        </h3>
                        <p class="text-gray-600 text-sm mt-1">{{ $marketplace['description'] }}</p>

                        @if(!empty($marketplace['has_operators']))
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mt-2">
                                Multiple Operators Available
                            </span>
                        @endif
                    </div>

                    <div class="flex-shrink-0">
                        <flux:icon.chevron-right class="w-5 h-5 text-gray-400 group-hover:text-blue-500" />
                    </div>
                </div>
            </button>
        @endforeach
    </div>
</flux:card>

