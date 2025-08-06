<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <flux:heading size="xl">Barcode Pool Management</flux:heading>
        <flux:subheading>Manage your GS1 barcode inventory and assignments</flux:subheading>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
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
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="text-2xl font-bold text-purple-600">{{ number_format($stats['reserved']) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Reserved</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">Quick Actions</flux:heading>
        </div>
        
        <div class="flex space-x-4">
            <flux:button 
                variant="primary"
                href="{{ route('barcodes.pool.import') }}"
                wire:navigate
            >
                Import Barcode Pool
            </flux:button>
            
            <flux:button 
                variant="ghost"
                wire:click="downloadSampleFile"
            >
                Download Sample
            </flux:button>
        </div>
        
        <div class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
            Use the Import Barcode Pool feature for bulk imports with Clean Slate Strategy support for legacy archive management.
        </div>
    </div>

    <!-- Legacy Import Section (Simple) -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 mb-8">
        <flux:heading size="lg" class="mb-4">Quick Import</flux:heading>
        <flux:subheading class="mb-6">Simple barcode import (for small files, no legacy archive support)</flux:subheading>
        
        <form wire:submit="importBarcodes" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- File Upload -->
                <flux:field>
                    <flux:label>Select File</flux:label>
                    <input 
                        type="file" 
                        wire:model="file" 
                        accept=".xlsx,.xls,.csv,.txt"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                    >
                    <flux:error name="file" />
                    <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        Supports Excel, CSV, or text files. For text files, one barcode per line.
                    </div>
                </flux:field>

                <!-- Barcode Type -->
                <flux:field>
                    <flux:label>Barcode Type</flux:label>
                    <select wire:model="barcodeType" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        @foreach($barcodeTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <flux:error name="barcodeType" />
                </flux:field>
            </div>

            <div class="flex justify-end">
                <flux:button 
                    type="submit" 
                    variant="primary" 
                    :disabled="!$file || $importing"
                >
                    @if($importing)
                        Importing...
                    @else
                        Import Barcodes
                    @endif
                </flux:button>
            </div>
        </form>

        <!-- Import Progress -->
        <div wire:loading wire:target="file" class="mt-4 text-blue-600">
            Uploading file...
        </div>

        <!-- Import Results -->
        @if($importResults)
            <div class="mt-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                <div class="font-medium text-green-800 dark:text-green-200">Import Results:</div>
                <div class="text-sm text-green-700 dark:text-green-300 mt-1">
                    • Imported: {{ $importResults['imported'] }} barcodes<br>
                    • Skipped: {{ $importResults['skipped'] }} (duplicates or empty)<br>
                    @if($importResults['errors'])
                        • Errors: {{ count($importResults['errors']) }}
                    @endif
                </div>
                @if($importResults['errors'])
                    <details class="mt-2">
                        <summary class="cursor-pointer text-sm font-medium text-red-700 dark:text-red-300">View Errors</summary>
                        <div class="mt-2 text-sm text-red-600 dark:text-red-400">
                            @foreach(array_slice($importResults['errors'], 0, 10) as $error)
                                • {{ $error }}<br>
                            @endforeach
                            @if(count($importResults['errors']) > 10)
                                <em>... and {{ count($importResults['errors']) - 10 }} more errors</em>
                            @endif
                        </div>
                    </details>
                @endif
            </div>
        @endif
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Search -->
            <flux:field>
                <flux:label>Search</flux:label>
                <flux:input 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by barcode, product name, or SKU..."
                />
            </flux:field>

            <!-- Status Filter -->
            <flux:field>
                <flux:label>Filter by Status</flux:label>
                <flux:select wire:model.live="statusFilter">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>
    </div>

    <!-- Barcodes Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-zinc-50 dark:bg-zinc-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Barcode
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Assigned To
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Assigned Date
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Actions
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
                                    <flux:badge variant="zinc" size="sm">Reserved</flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($barcode->assignedVariant)
                                    <div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $barcode->assignedVariant->product->name }}
                                        </div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                            SKU: {{ $barcode->assignedVariant->sku }}
                                        </div>
                                    </div>
                                @else
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($barcode->assigned_at)
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $barcode->assigned_at->format('M j, Y') }}
                                    </div>
                                @else
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    @if($barcode->status === 'assigned')
                                        <flux:button 
                                            size="sm" 
                                            variant="ghost"
                                            wire:click="releaseBarcode({{ $barcode->id }})"
                                            wire:confirm="Release this barcode back to the pool?"
                                        >
                                            Release
                                        </flux:button>
                                    @endif
                                    
                                    <flux:button 
                                        size="sm" 
                                        variant="danger"
                                        wire:click="deleteFromPool({{ $barcode->id }})"
                                        wire:confirm="Are you sure you want to permanently delete this barcode from the pool?"
                                    >
                                        Delete
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
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
</div>
