@php
    $isActive = \App\Navigation\NavigationManager::isNavigationActive($item);
    $url = $item->getUrl();
    $hasChildren = count($item->getChildren()) > 0;
@endphp

<div class="navigation-item">
    @if($url)
        <a 
            href="{{ $url }}" 
            wire:navigate
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 {{ $isActive ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-500' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}"
        >
            @if($item->getIcon())
                <flux:icon 
                    name="{{ $item->getIcon() }}" 
                    class="mr-3 h-4 w-4 {{ $isActive ? 'text-blue-500' : 'text-gray-400 group-hover:text-gray-500' }}"
                />
            @endif
            
            <span class="flex-1">{{ $item->getLabel() }}</span>
            
            @if($item->getBadge())
                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    {{ $item->getBadge() }}
                </span>
            @endif
            
            @if($hasChildren)
                <flux:icon name="chevron-right" class="ml-2 h-4 w-4" />
            @endif
        </a>
    @else
        <div class="px-3 py-2 text-sm font-medium text-gray-900">
            {{ $item->getLabel() }}
        </div>
    @endif
    
    {{-- Render children if any --}}
    @if($hasChildren)
        <div class="ml-4 mt-1 space-y-1">
            @foreach($item->getChildren() as $child)
                <x-navigation.item :item="$child" />
            @endforeach
        </div>
    @endif
</div>