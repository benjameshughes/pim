{{-- 
Example: Using {{ $this->table }} in Your Own Livewire Component View
This shows how to add Atom framework table functionality to your existing Livewire components.
--}}

<div>
    {{-- Your custom Livewire component content --}}
    <div class="mb-6">
        <h2 class="text-2xl font-bold mb-2">{{ $customTitle }}</h2>
        <p class="text-gray-600">{{ $customDescription }}</p>
        
        {{-- Your custom component logic --}}
        <div class="flex gap-4 mt-4">
            <button wire:click="refreshData" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                Refresh Data
            </button>
            <button wire:click="exportData" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                Export Data
            </button>
        </div>
    </div>

    {{-- Drop in the Atom framework table --}}
    <div class="bg-white rounded-lg shadow border">
        {{-- This magically works! Your component needs to use the ResourceTableMixin trait --}}
        {{ $this->table }}
    </div>

    {{-- More of your custom content --}}
    <div class="mt-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-blue-100 p-4 rounded">
                <h3 class="font-semibold">Total Records</h3>
                <p class="text-2xl font-bold text-blue-600">{{ $totalRecords }}</p>
            </div>
            <div class="bg-green-100 p-4 rounded">
                <h3 class="font-semibold">Active Records</h3>
                <p class="text-2xl font-bold text-green-600">{{ $activeRecords }}</p>
            </div>
            <div class="bg-purple-100 p-4 rounded">
                <h3 class="font-semibold">Recent Additions</h3>
                <p class="text-2xl font-bold text-purple-600">{{ $recentRecords }}</p>
            </div>
        </div>
    </div>
</div>