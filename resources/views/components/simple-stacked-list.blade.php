@php
    // Simple alternative view for StackedList
    $config = $table->toArray();
@endphp

<div class="bg-white rounded-lg shadow">
    <!-- Simple Header -->
    <div class="p-4 border-b">
        <h2 class="text-lg font-semibold">{{ $config['title'] ?? 'Items' }}</h2>
        @if($subtitle = $config['subtitle'] ?? null)
            <p class="text-sm text-gray-600">{{ $subtitle }}</p>
        @endif
    </div>

    <!-- Simple Data Display -->
    @if($data && $data->count() > 0)
        <div class="p-4">
            <p class="mb-4 text-green-600 font-bold">✅ Found {{ $data->count() }} items ({{ $data->total() }} total)</p>
            
            <!-- Simple Table -->
            <table class="w-full border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-50">
                        @foreach($config['columns'] ?? [] as $column)
                            <th class="border border-gray-300 px-4 py-2 text-left">
                                {{ $column['label'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($data as $item)
                        <tr>
                            @foreach($config['columns'] ?? [] as $column)
                                <td class="border border-gray-300 px-4 py-2">
                                    {{ data_get($item, $column['key']) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="mt-4">
                {{ $data->links() }}
            </div>
        </div>
    @else
        <div class="p-8 text-center text-gray-500">
            <p class="text-red-600 font-bold">❌ No data found</p>
            <p class="text-sm">Data variable: {{ gettype($data) }}</p>
            @if(is_object($data) && method_exists($data, 'count'))
                <p class="text-sm">Count: {{ $data->count() }}</p>
            @endif
        </div>
    @endif
</div>