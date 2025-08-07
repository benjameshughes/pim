{{-- Main Navigation Component --}}
<nav class="bg-white shadow-sm border-r border-gray-200 h-full" {{ $attributes }}>
    <div class="p-4">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Navigation</h2>
        
        @foreach($groups as $groupName => $group)
            @if($groupName === '_ungrouped')
                {{-- Ungrouped navigation items --}}
                @if($group->hasItems())
                    <div class="space-y-1 mb-6">
                        @foreach($group->getItems() as $item)
                            <x-navigation.item :item="$item" />
                        @endforeach
                    </div>
                @endif
            @else
                {{-- Grouped navigation items --}}
                <div class="mb-6">
                    @if($group->getLabel())
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-2">
                            {{ $group->getLabel() }}
                        </h3>
                    @endif
                    
                    <div class="space-y-1">
                        @foreach($group->getItems() as $item)
                            <x-navigation.item :item="$item" />
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</nav>