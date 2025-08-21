{{-- ✨ EXAMPLE: Simple One-Component Table (like before) ✨ --}}
<flux:table 
    :data="$variants" 
    theme="phoenix"
    searchable
    filterable
    selectable
    :bulk-actions="[
        ['key' => 'delete', 'label' => 'Delete Selected', 'icon' => 'trash'],
        ['key' => 'export', 'label' => 'Export', 'icon' => 'download']
    ]" />

{{-- ✨ EXAMPLE: Composed Multi-Component Table (NEW POWER!) ✨ --}}
<flux:table :data="$variants" theme="phoenix">
    
    {{-- Header with title and actions --}}
    <flux:table.header 
        title="Product Variants" 
        subtitle="Manage your product variants and inventory"
        searchable
        filterable>
        
        <x-slot:actions>
            <flux:button icon="plus" href="{{ route('variants.create') }}">
                New Variant
            </flux:button>
            <flux:button icon="download" variant="outline">
                Export All
            </flux:button>
        </x-slot:actions>
    </flux:table.header>
    
    {{-- Rows with custom action route --}}
    <flux:table.row action-route="variants" />
    
    {{-- Footer with pagination --}}
    <flux:table.footer :pagination="$variants->links()">
        <flux:button icon="refresh-cw" variant="ghost" size="sm">
            Refresh
        </flux:button>
    </flux:table.footer>
    
</flux:table>

{{-- ✨ EXAMPLE: Fully Custom Table ✨ --}}
<flux:table :data="$variants" theme="glass" size="compact">
    
    <flux:table.header title="Inventory Overview">
        {{-- Custom filters or search --}}
        <div class="flex gap-4">
            <select class="border rounded px-3 py-1">
                <option>All Categories</option>
                <option>Electronics</option>
                <option>Clothing</option>
            </select>
            <input type="range" min="0" max="1000" class="w-32">
        </div>
    </flux:table.header>
    
    <flux:table.row>
        {{-- You could add custom row content here if needed --}}
    </flux:table.row>
    
    <flux:table.footer summary>
        <div class="text-sm text-gray-500">
            Last updated: {{ now()->format('M j, Y g:i A') }}
        </div>
    </flux:table.footer>
    
</flux:table>