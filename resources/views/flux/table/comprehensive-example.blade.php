{{-- ✨ COMPREHENSIVE FLUX TABLE SHOWCASE ✨ --}}
{{-- Every queen gets her spotlight! --}}

{{-- 🎭 EXAMPLE 1: Simple One-Component (Classic Style) --}}
<div class="mb-12">
    <h2 class="text-2xl font-bold mb-4">💫 Classic Single Component</h2>
    
    <flux:table 
        :data="$variants" 
        theme="phoenix"
        searchable
        filterable
        selectable
        :bulk-actions="[
            ['key' => 'delete', 'label' => 'Delete Selected', 'icon' => 'trash'],
            ['key' => 'export', 'label' => 'Export', 'icon' => 'download']
        ]" 
    />
</div>

{{-- 🌟 EXAMPLE 2: Full Cast Production (All Components) --}}
<div class="mb-12">
    <h2 class="text-2xl font-bold mb-4">🎪 Full Cast Production</h2>
    
    <flux:table :data="$variants" theme="glass" size="normal">
        
        {{-- Header with Title, Search, Filters, and Actions --}}
        <flux:table.header title="Product Variants" subtitle="Manage your complete inventory">
            
            {{-- Top Actions Bar --}}
            <flux:table.actions 
                :primary="[
                    'href' => route('variants.create'),
                    'label' => 'Create Variant',
                    'icon' => 'plus',
                    'variant' => 'filled'
                ]"
                :secondary="[
                    [
                        'href' => route('variants.export'),
                        'label' => 'Export All',
                        'icon' => 'download',
                        'variant' => 'outline'
                    ],
                    [
                        'action' => '$wire.refreshData()',
                        'label' => 'Refresh', 
                        'icon' => 'refresh-cw',
                        'variant' => 'ghost'
                    ]
                ]"
                align="right"
                gap="normal"
            />
            
            {{-- Search & Filters Row --}}
            <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-end">
                
                {{-- Search with all the glamour --}}
                <flux:table.search 
                    placeholder="Search variants by SKU, color, size..."
                    clearable
                    shortcuts
                    width="lg"
                />
                
                {{-- Filters with accordion style --}}
                <flux:table.filters 
                    layout="grid"
                    columns="4"
                    collapsible
                    show-count
                    clearable
                    compact
                />
                
                {{-- Per Page Options --}}
                <flux:table.per-page 
                    :options="[10, 25, 50, 100]"
                    :current="25"
                    label="Show"
                    suffix="items"
                    size="normal"
                />
                
            </div>
            
        </flux:table.header>
        
        {{-- Rows with custom action route --}}
        <flux:table.row action-route="variants" />
        
        {{-- Footer with everything --}}
        <flux:table.footer>
            
            {{-- Pagination with all options --}}
            <flux:table.pagination 
                :paginator="$variants"
                show-info
                show-pages
                show-prev-next
                show-first-last
                :max-pages="7"
                size="normal"
                align="center"
            />
            
        </flux:table.footer>
        
    </flux:table>
</div>

{{-- ✨ EXAMPLE 3: Custom Component Composition --}}
<div class="mb-12">
    <h2 class="text-2xl font-bold mb-4">🎨 Custom Composition</h2>
    
    <flux:table :data="$variants" theme="neon" size="compact">
        
        {{-- Minimal header with just search --}}
        <flux:table.header>
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold">Quick Search</h3>
                <flux:table.search width="md" size="sm" />
            </div>
        </flux:table.header>
        
        {{-- Custom filters outside the header --}}
        <div class="p-4 bg-gray-50 dark:bg-gray-800">
            <flux:table.filters 
                layout="inline"
                show-count
                clearable
            >
                {{-- Custom filter slots --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">Price Range</label>
                    <input type="range" min="0" max="1000" class="w-32">
                </div>
                
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">In Stock</label>
                    <input type="checkbox" class="rounded">
                </div>
            </flux:table.filters>
        </div>
        
        <flux:table.row action-route="variants" />
        
        {{-- Split footer --}}
        <flux:table.footer>
            <div class="flex justify-between items-center w-full">
                
                <flux:table.per-page 
                    :options="[5, 10, 20]"
                    :current="10"
                    size="sm"
                />
                
                <flux:table.pagination 
                    :paginator="$variants"
                    show-pages
                    show-prev-next
                    :max-pages="5"
                    size="sm"
                />
                
                <flux:table.actions 
                    :secondary="[
                        ['label' => 'Export Page', 'icon' => 'download', 'variant' => 'outline'],
                        ['label' => 'Print', 'icon' => 'printer', 'variant' => 'ghost']
                    ]"
                    size="sm"
                />
                
            </div>
        </flux:table.footer>
        
    </flux:table>
</div>

{{-- 💎 EXAMPLE 4: Themed Variations --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
    
    {{-- Phoenix Theme --}}
    <div>
        <h3 class="text-lg font-bold mb-4">🔥 Phoenix Theme</h3>
        <flux:table :data="$variants->take(3)" theme="phoenix" size="compact">
            <flux:table.header title="Phoenix Rising">
                <flux:table.search size="sm" />
            </flux:table.header>
            <flux:table.row action-route="variants" />
            <flux:table.footer>
                <flux:table.pagination :paginator="$variants" size="sm" />
            </flux:table.footer>
        </flux:table>
    </div>
    
    {{-- Minimal Theme --}}
    <div>
        <h3 class="text-lg font-bold mb-4">🤍 Minimal Theme</h3>
        <flux:table :data="$variants->take(3)" theme="minimal" size="compact">
            <flux:table.header title="Clean & Simple">
                <flux:table.actions 
                    :primary="['label' => 'Add', 'icon' => 'plus']"
                    size="sm"
                />
            </flux:table.header>
            <flux:table.row action-route="variants" />
        </flux:table>
    </div>
    
</div>

{{-- 🎪 EXAMPLE 5: Event-Driven Interactions --}}
<div class="mb-12">
    <h2 class="text-2xl font-bold mb-4">🎭 Interactive Events</h2>
    
    <div x-data="{
        currentPage: 1,
        perPage: 15,
        searchQuery: '',
        selectedItems: [],
        
        handlePagination(event) {
            this.currentPage = event.detail.page;
            console.log('Page changed to:', this.currentPage);
        },
        
        handlePerPageChange(event) {
            this.perPage = event.detail.perPage;
            console.log('Per page changed to:', this.perPage);
        },
        
        handleBulkAction(event) {
            console.log('Bulk action:', event.detail.action, 'on items:', event.detail.selected);
        }
    }" 
    @paginate="handlePagination"
    @per-page-changed="handlePerPageChange" 
    @bulk-action="handleBulkAction">
        
        <flux:table :data="$variants" theme="modern">
            
            <flux:table.header title="Event-Driven Table">
                <flux:table.search 
                    placeholder="Events fire as you type..." 
                    debounce="500ms"
                />
            </flux:table.header>
            
            <flux:table.row action-route="variants" />
            
            <flux:table.footer>
                <div class="flex justify-between items-center w-full">
                    <flux:table.per-page 
                        :options="[5, 15, 30]"
                        :current="15"
                    />
                    <flux:table.pagination :paginator="$variants" />
                </div>
            </flux:table.footer>
            
        </flux:table>
        
        {{-- Debug Panel --}}
        <div class="mt-4 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg text-sm">
            <strong>Live Event Data:</strong><br>
            Current Page: <span x-text="currentPage"></span><br>
            Per Page: <span x-text="perPage"></span><br>
            Search: <span x-text="searchQuery"></span><br>
            Selected Items: <span x-text="selectedItems.length"></span>
        </div>
        
    </div>
</div>