@if(count($items) > 0)
    <div class="border-b border-gray-200 mb-6">
        <nav class="flex space-x-8" aria-label="Sub Navigation">
            @foreach($items as $item)
                @php
                    $isActive = \App\Navigation\NavigationManager::isNavigationActive($item);
                    $url = $item->getUrl();
                @endphp
                
                @if($url)
                    <a 
                        href="{{ $url }}" 
                        wire:navigate
                        class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200 {{ $isActive ? 'border-blue-500 text-blue-600' : '' }}"
                    >
                        @if($item->getIcon())
                            <flux:icon 
                                name="{{ $item->getIcon() }}" 
                                class="inline h-4 w-4 mr-2" 
                            />
                        @endif
                        {{ $item->getLabel() }}
                        
                        @if($item->getBadge())
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                {{ $item->getBadge() }}
                            </span>
                        @endif
                    </a>
                @else
                    <span class="text-gray-900 whitespace-nowrap py-4 px-1 border-b-2 border-transparent font-medium text-sm">
                        {{ $item->getLabel() }}
                    </span>
                @endif
            @endforeach
        </nav>
    </div>
@endif