@props(['items' => []])

<nav class="flex mb-6" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-1 md:space-x-3">
        @foreach($items as $index => $item)
            <li class="inline-flex items-center">
                @if($index > 0)
                    <svg class="w-3 h-3 text-zinc-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                    </svg>
                @endif
                
                @if(isset($item['url']) && !$loop->last)
                    <a href="{{ $item['url'] }}" wire:navigate class="inline-flex items-center text-sm font-medium text-zinc-700 hover:text-blue-600 dark:text-zinc-400 dark:hover:text-white">
                        @if($index === 0 && isset($item['icon']))
                            {!! $item['icon'] !!}
                        @endif
                        {{ $item['name'] ?? '' }}
                    </a>
                @else
                    <span class="inline-flex items-center text-sm font-medium text-zinc-500 dark:text-zinc-400">
                        @if($index === 0 && isset($item['icon']))
                            {!! $item['icon'] !!}
                        @endif
                        {{ $item['name'] ?? '' }}
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>