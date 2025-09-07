<div class="max-w-7xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">{{ $account->account_label }} – {{ $account->marketplace }} Dashboard</h1>
            <p class="text-sm text-gray-600">Channel code: {{ strtolower($account->channel).'_'.strtolower($account->name) }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('sync-accounts.edit', ['accountId' => $account->id]) }}" class="inline-flex items-center px-3 py-2 bg-blue-600 text-white rounded-md">
                <flux:icon name="pencil" class="w-4 h-4 mr-2" /> Edit
            </a>
            <a href="{{ route('sync-accounts.index') }}" class="inline-flex items-center px-3 py-2 bg-gray-100 text-gray-700 rounded-md">
                <flux:icon name="arrow-left" class="w-4 h-4 mr-2" /> Back
            </a>
        </div>
    </div>

    <!-- Top stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg border p-4">
            <div class="text-sm text-gray-500">Products Linked</div>
            <div class="text-2xl font-bold">{{ $productsCount }}</div>
        </div>
        <div class="bg-white rounded-lg border p-4">
            <div class="text-sm text-gray-500">Synced</div>
            <div class="text-2xl font-bold text-green-600">{{ $statusSummary['synced'] }}</div>
        </div>
        <div class="bg-white rounded-lg border p-4">
            <div class="text-sm text-gray-500">Pending</div>
            <div class="text-2xl font-bold text-yellow-600">{{ $statusSummary['pending'] }}</div>
        </div>
        <div class="bg-white rounded-lg border p-4">
            <div class="text-sm text-gray-500">Failed</div>
            <div class="text-2xl font-bold text-red-600">{{ $statusSummary['failed'] }}</div>
        </div>
    </div>

    <!-- Attribute coverage (hooked to attribute system) -->
    <div class="bg-white rounded-lg border">
        <div class="p-4 border-b">
            <h2 class="text-lg font-semibold">Attribute Coverage for {{ $account->marketplace }}</h2>
            <p class="text-sm text-gray-600">Based on Attribute Definitions marked to sync to this marketplace</p>
        </div>
        @if(!empty($coverage))
            <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($coverage as $key => $row)
                    <div class="border rounded-md p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <flux:icon name="{{ $row['definition']->icon ?? 'swatch' }}" class="w-4 h-4 text-gray-500" />
                                <div>
                                    <div class="text-sm font-medium">{{ $row['definition']->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $key }} • {{ $row['definition']->group }}</div>
                                </div>
                            </div>
                            <div class="text-sm font-semibold {{ $row['coverage_pct'] >= 90 ? 'text-green-600' : ($row['coverage_pct'] >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ $row['coverage_pct'] }}%
                            </div>
                        </div>
                        <div class="mt-2 h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-2 bg-blue-500" style="width: {{ $row['coverage_pct'] }}%"></div>
                        </div>
                        <div class="mt-2 text-xs text-gray-600">
                            <span class="font-medium">{{ $row['valid'] }}</span> valid • <span class="font-medium">{{ $row['missing'] }}</span> missing
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-6 text-sm text-gray-600">No marketplace-mapped attributes found for this channel, or no products linked to this account.</div>
        @endif
    </div>

    <!-- Sales Channel awareness -->
    <div class="bg-white rounded-lg border p-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold">Sales Channel</h3>
                <p class="text-sm text-gray-600">
                    {{ $salesChannel ? $salesChannel->name.' ('.$salesChannel->code.')' : 'No sales channel record found for this account' }}
                </p>
            </div>
            @if($salesChannel)
                <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">Priority: {{ $salesChannel->config['priority'] ?? 'n/a' }}</span>
            @endif
        </div>
    </div>
</div>

