<flux:main>
    {{-- Page Header --}}
    <div class="mb-6">
        <flux:heading size="xl">{{ $title }}</flux:heading>
        
        {{-- Breadcrumbs --}}
        @if(count($breadcrumbs) > 1)
            <nav class="flex mt-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    @foreach($breadcrumbs as $breadcrumb)
                        <li class="inline-flex items-center">
                            @if(!$loop->first)
                                <svg class="w-5 h-5 text-gray-400 mx-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            @endif
                            @if($breadcrumb['url'])
                                <a href="{{ $breadcrumb['url'] }}" class="text-gray-500 hover:text-gray-700">
                                    {{ $breadcrumb['label'] }}
                                </a>
                            @else
                                <span class="text-gray-700 font-medium">{{ $breadcrumb['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
    </div>

    {{-- Resource Table (Magic!) --}}
    {{ $this->table }}

    {{-- Success Messages --}}
    @if(session('success'))
        <div class="fixed bottom-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded shadow-lg">
            {{ session('success') }}
        </div>
    @endif
</flux:main>