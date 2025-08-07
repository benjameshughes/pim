@php
    $config = $table->toArray();
@endphp

<div class="space-y-6">
    {{-- Title Section --}}
    @if($config['title'])
        <div class="space-y-1">
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                {{ $config['title'] }}
            </h2>
            @if($config['subtitle'])
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $config['subtitle'] }}
                </p>
            @endif
        </div>
    @endif

    {{-- Data Table --}}
    @if($data->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            {{-- Table Headers --}}
            <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3">
                <div class="flex items-center space-x-4">
                    @foreach($config['columns'] as $column)
                        <div class="flex-1 text-left">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                {{ $column['label'] ?? $column['key'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Table Rows --}}
            <div class="divide-y divide-gray-200 dark:divide-gray-600">
                @foreach($data as $item)
                    <div class="px-6 py-4">
                        <div class="flex items-center space-x-4">
                            @foreach($config['columns'] as $column)
                                <div class="flex-1 {{ $column['class'] ?? '' }}">
                                    @switch($column['type'] ?? 'text')
                                        @case('text')
                                            <div class="text-sm text-gray-900 dark:text-gray-100 {{ $column['font'] ?? '' }}">
                                                {{ data_get($item, $column['key']) }}
                                            </div>
                                            @break

                                        @case('badge')
                                            @php
                                                $value = data_get($item, $column['key']);
                                                $badgeConfig = $column['badges'][$value] ?? ['class' => 'bg-gray-100 text-gray-800', 'label' => $value];
                                                if (is_string($badgeConfig)) {
                                                    $badgeConfig = ['class' => $badgeConfig, 'label' => $value];
                                                }
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeConfig['class'] ?? 'bg-gray-100 text-gray-800' }}">
                                                {{ $badgeConfig['label'] ?? $value }}
                                            </span>
                                            @break

                                        @default
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ data_get($item, $column['key']) }}
                                            </div>
                                    @endswitch
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $data->links() }}
        </div>
    @else
        {{-- Empty State --}}
        <div class="text-center py-12">
            <div class="text-gray-400 text-6xl mb-4">📋</div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No items found</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Get started by adding some data.</p>
        </div>
    @endif
</div>