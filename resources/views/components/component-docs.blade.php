@props([
    'component' => null,
    'title' => null,
    'description' => null,
    'props' => [],
    'examples' => [],
    'slots' => [],
])

<x-page-template :title="$title ?? ($component . ' Component')" maxWidth="6xl">
    <x-slot name="description">
        {{ $description ?? "Documentation and examples for the {$component} component." }}
    </x-slot>
    
    <x-slot name="actions">
        [
            [
                'type' => 'button',
                'label' => 'View Source',
                'variant' => 'outline',
                'icon' => 'code',
            ],
            [
                'type' => 'link',
                'label' => 'Edit Documentation',
                'variant' => 'primary',
                'icon' => 'pencil',
                'href' => '#',
            ]
        ]
    </x-slot>
    
    <div class="space-y-8">
        {{-- Component Properties --}}
        @if(!empty($props))
            <div>
                <flux:heading size="lg" class="mb-4">Properties</flux:heading>
                <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-sm divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-900">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Property</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Default</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Description</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-zinc-800 divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($props as $prop)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <code class="text-sm bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded">
                                            {{ $prop['name'] }}
                                        </code>
                                        @if($prop['required'] ?? false)
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400 ml-2">
                                                required
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <code class="text-sm text-blue-600 dark:text-blue-400">
                                            {{ $prop['type'] }}
                                        </code>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if(isset($prop['default']))
                                            <code class="text-sm text-zinc-600 dark:text-zinc-400">
                                                {{ is_bool($prop['default']) ? ($prop['default'] ? 'true' : 'false') : $prop['default'] }}
                                            </code>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $prop['description'] }}</p>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
        
        {{-- Available Slots --}}
        @if(!empty($slots))
            <div>
                <flux:heading size="lg" class="mb-4">Slots</flux:heading>
                <div class="space-y-4">
                    @foreach($slots as $slot)
                        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                            <div class="flex justify-between items-start mb-2">
                                <code class="text-sm bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded font-medium">
                                    {{ $slot['name'] }}
                                </code>
                                @if($slot['required'] ?? false)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400">
                                        required
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $slot['description'] }}
                            </p>
                            @if(isset($slot['example']))
                                <div class="mt-3">
                                    <pre class="bg-zinc-50 dark:bg-zinc-900 p-3 rounded text-sm overflow-x-auto"><code>{{ $slot['example'] }}</code></pre>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        
        {{-- Examples --}}
        @if(!empty($examples))
            <div>
                <flux:heading size="lg" class="mb-4">Examples</flux:heading>
                <div class="space-y-8">
                    @foreach($examples as $example)
                        <div>
                            <flux:subheading class="mb-4">{{ $example['title'] }}</flux:subheading>
                            @if(isset($example['description']))
                                <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
                                    {{ $example['description'] }}
                                </p>
                            @endif
                            
                            {{-- Example Output --}}
                            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-6 bg-white dark:bg-zinc-800 mb-4">
                                {!! $example['output'] !!}
                            </div>
                            
                            {{-- Example Code --}}
                            @if(isset($example['code']))
                                <details class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                                    <summary class="bg-zinc-50 dark:bg-zinc-900 px-4 py-2 cursor-pointer text-sm font-medium">
                                        View Code
                                    </summary>
                                    <div class="p-4">
                                        <pre class="bg-zinc-900 text-zinc-100 p-4 rounded text-sm overflow-x-auto"><code>{{ $example['code'] }}</code></pre>
                                    </div>
                                </details>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        
        {{ $slot }}
    </div>
</x-page-template>