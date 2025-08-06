<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <flux:heading size="xl">Barcode Pool Management (Lite)</flux:heading>
        <flux:subheading>Lightweight view for large datasets</flux:subheading>
    </div>

    <!-- Basic Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="text-2xl font-bold text-blue-600">{{ number_format($stats['total']) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Total Barcodes</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="text-2xl font-bold text-green-600">{{ number_format($stats['available']) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Available</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="text-2xl font-bold text-orange-600">{{ number_format($stats['assigned']) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Assigned</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 mb-8">
        <flux:button 
            variant="primary"
            href="{{ route('barcodes.pool.import') }}"
            wire:navigate
        >
            Import Barcode Pool
        </flux:button>
    </div>

    <!-- Simple Filters -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:field>
                <flux:label>Search Barcode</flux:label>
                <flux:input 
                    wire:model.live.debounce.500ms="search"
                    placeholder="Search by barcode only..."
                />
            </flux:field>

            <flux:field>
                <flux:label>Filter by Status</flux:label>
                <flux:select wire:model.live="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="available">Available</option>
                    <option value="assigned">Assigned</option>
                    <option value="reserved">Reserved</option>
                    <option value="legacy_archive">Legacy Archive</option>
                </flux:select>
            </flux:field>
        </div>
    </div>

    <!-- Simple Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-zinc-50 dark:bg-zinc-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">
                            Barcode
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">
                            Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">
                            Assigned Date
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($barcodes as $barcode)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="text-sm font-mono font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $barcode->barcode }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <flux:badge size="sm">{{ $barcode->barcode_type }}</flux:badge>
                            </td>
                            <td class="px-6 py-4">
                                @if($barcode->status === 'available')
                                    <flux:badge variant="success" size="sm">Available</flux:badge>
                                @elseif($barcode->status === 'assigned')
                                    <flux:badge variant="warning" size="sm">Assigned</flux:badge>
                                @else
                                    <flux:badge variant="zinc" size="sm">{{ ucfirst($barcode->status) }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($barcode->assigned_at)
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ \Carbon\Carbon::parse($barcode->assigned_at)->format('M j, Y') }}
                                    </div>
                                @else
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="text-zinc-500 dark:text-zinc-400">
                                    <div class="mb-2">No barcodes found</div>
                                    <div class="text-sm">Import your GS1 barcodes to get started</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($barcodes->hasPages())
            <div class="px-6 py-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $barcodes->links() }}
            </div>
        @endif
    </div>

    <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
        <div class="text-sm text-blue-700 dark:text-blue-300">
            <strong>Lite Mode:</strong> This is a memory-optimized version that doesn't show product assignments to handle large datasets. Use the import feature to manage your barcode pool.
        </div>
    </div>
</div>