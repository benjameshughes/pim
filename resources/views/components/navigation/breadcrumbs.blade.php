@if(count($breadcrumbs) > 1)
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            @foreach($breadcrumbs as $breadcrumb)
                <li class="inline-flex items-center">
                    @if(!$loop->first)
                        <flux:icon 
                            name="chevron-right" 
                            class="w-4 h-4 text-gray-400 mx-2" 
                        />
                    @endif
                    
                    @if($breadcrumb['url'] && !$loop->last)
                        <a 
                            href="{{ $breadcrumb['url'] }}" 
                            wire:navigate
                            class="text-gray-500 hover:text-gray-700 transition-colors duration-200"
                        >
                            {{ $breadcrumb['label'] }}
                        </a>
                    @else
                        <span class="text-gray-700 font-medium">
                            {{ $breadcrumb['label'] }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif