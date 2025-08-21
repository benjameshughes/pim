{{-- 
🎭 FLUX TABLE COMPONENT USAGE GUIDE 
===================================

This guide shows all the fabulous ways to use our new table system!
Each "queen" component has her own spotlight moment! ✨

--}}

{{-- 📚 TABLE OF CONTENTS --}}
{{--
1. 💫 Simple One-Component Usage (Classic)
2. 🎪 Full Composed Usage (All Components)
3. 🎨 Individual Component Usage
4. 🎭 Variant Selection
5. 🌟 Theme Showcase
6. 🔧 Props Reference
--}}

<div class="space-y-16 p-8">

{{-- 💫 1. SIMPLE ONE-COMPONENT USAGE --}}
<section>
    <h1 class="text-3xl font-bold mb-8">💫 Simple One-Component Usage</h1>
    <p class="mb-6 text-gray-600">Perfect for quick tables - all functionality in one component:</p>
    
    <div class="bg-gray-50 p-6 rounded-lg mb-4">
        <pre class="text-sm"><code>{{-- Basic table with auto-detection --}}
&lt;flux:table :data="$variants" /&gt;

{{-- Themed table with features --}}
&lt;flux:table 
    :data="$variants" 
    theme="phoenix"
    searchable
    filterable
    selectable
    :bulk-actions="[
        ['key' => 'delete', 'label' => 'Delete', 'icon' => 'trash'],
        ['key' => 'export', 'label' => 'Export', 'icon' => 'download']
    ]" 
/&gt;</code></pre>
    </div>
</section>

{{-- 🎪 2. FULL COMPOSED USAGE --}}
<section>
    <h1 class="text-3xl font-bold mb-8">🎪 Full Composed Usage</h1>
    <p class="mb-6 text-gray-600">Maximum control with specialized components:</p>
    
    <div class="bg-gray-50 p-6 rounded-lg mb-4">
        <pre class="text-sm"><code>&lt;flux:table :data="$variants" theme="glass"&gt;
    
    {{-- Header with all the bells and whistles --}}
    &lt;flux:table.header 
        title="Product Variants" 
        subtitle="Manage inventory"
        variant="composed"
        layout="stacked"
        :actions="[
            'primary' => ['label' => 'Create', 'href' => route('variants.create')],
            'secondary' => [
                ['label' => 'Export', 'icon' => 'download'],
                ['label' => 'Refresh', 'icon' => 'refresh-cw']
            ]
        ]"
        :search="['placeholder' => 'Search variants...', 'width' => 'lg']"
        :filters="['layout' => 'grid', 'columns' => 4, 'collapsible' => true]"
        :per-page="['options' => [10, 25, 50], 'current' => 25]"
    /&gt;
    
    {{-- Rows with custom actions --}}
    &lt;flux:table.row action-route="variants" /&gt;
    
    {{-- Footer with composed layout --}}
    &lt;flux:table.footer 
        variant="composed"
        layout="split"
        :pagination="['paginator' => $variants, 'showFirstLast' => true]"
        :actions="[
            'secondary' => [
                ['label' => 'Export Page', 'icon' => 'download']
            ]
        ]"
    /&gt;
    
&lt;/flux:table&gt;</code></pre>
    </div>
</section>

{{-- 🎨 3. INDIVIDUAL COMPONENT USAGE --}}
<section>
    <h1 class="text-3xl font-bold mb-8">🎨 Individual Component Usage</h1>
    <p class="mb-6 text-gray-600">Each component can shine on its own:</p>
    
    {{-- Actions Component --}}
    <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4">🎭 flux:table.actions - The Action Diva</h2>
        <div class="bg-gray-50 p-6 rounded-lg mb-4">
            <pre class="text-sm"><code>{{-- Simple primary action --}}
&lt;flux:table.actions 
    :primary="['label' => 'Create New', 'href' => '/create', 'icon' => 'plus']"
/&gt;

{{-- Multiple actions with custom styling --}}
&lt;flux:table.actions 
    :primary="['label' => 'Create', 'variant' => 'filled']"
    :secondary="[
        ['label' => 'Export', 'icon' => 'download', 'variant' => 'outline'],
        ['label' => 'Import', 'icon' => 'upload', 'variant' => 'ghost']
    ]"
    align="right"
    gap="normal"
    size="normal"
/&gt;

{{-- Using slot for custom actions --}}
&lt;flux:table.actions align="center"&gt;
    &lt;flux:button icon="sparkles"&gt;Custom Action&lt;/flux:button&gt;
    &lt;flux:button variant="outline"&gt;Another&lt;/flux:button&gt;
&lt;/flux:table.actions&gt;</code></pre>
        </div>
    </div>

    {{-- Search Component --}}
    <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4">🔍 flux:table.search - The Search Songstress</h2>
        <div class="bg-gray-50 p-6 rounded-lg mb-4">
            <pre class="text-sm"><code>{{-- Basic search --}}
&lt;flux:table.search placeholder="Search products..." /&gt;

{{-- Advanced search with all features --}}
&lt;flux:table.search 
    placeholder="Search with style..."
    clearable
    shortcuts
    size="sm" 
    width="full"
    debounce="500ms"
    icon="magnifying-glass"
/&gt;</code></pre>
        </div>
    </div>

    {{-- Filters Component --}}
    <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4">🎨 flux:table.filters - The Filter Fashionista</h2>
        <div class="bg-gray-50 p-6 rounded-lg mb-4">
            <pre class="text-sm"><code>{{-- Auto-detected filters --}}
&lt;flux:table.filters /&gt;

{{-- Customized filter layout --}}
&lt;flux:table.filters 
    layout="grid"
    columns="4"
    collapsible
    show-count
    clearable
    compact
/&gt;

{{-- Inline filters with custom content --}}
&lt;flux:table.filters layout="inline"&gt;
    &lt;div class="flex flex-col gap-1"&gt;
        &lt;label&gt;Price Range&lt;/label&gt;
        &lt;input type="range" /&gt;
    &lt;/div&gt;
&lt;/flux:table.filters&gt;</code></pre>
        </div>
    </div>

    {{-- Pagination Component --}}
    <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4">📄 flux:table.pagination - The Page Turner</h2>
        <div class="bg-gray-50 p-6 rounded-lg mb-4">
            <pre class="text-sm"><code>{{-- Laravel paginator --}}
&lt;flux:table.pagination :paginator="$products" /&gt;

{{-- Full-featured pagination --}}
&lt;flux:table.pagination 
    :paginator="$products"
    show-info
    show-pages
    show-prev-next
    show-first-last
    :max-pages="7"
    size="normal"
    align="center"
/&gt;

{{-- Manual pagination data --}}
&lt;flux:table.pagination 
    :paginator="[
        'current_page' => 2,
        'last_page' => 10,
        'total' => 250,
        'from' => 26,
        'to' => 50
    ]"
/&gt;</code></pre>
        </div>
    </div>

    {{-- Per Page Component --}}
    <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4">📊 flux:table.per-page - The Options Orchestrator</h2>
        <div class="bg-gray-50 p-6 rounded-lg mb-4">
            <pre class="text-sm"><code>{{-- Basic per-page selector --}}
&lt;flux:table.per-page :current="25" /&gt;

{{-- Customized options --}}
&lt;flux:table.per-page 
    :options="[5, 10, 25, 50, 100]"
    :current="25"
    label="Display"
    suffix="items per page"
    size="normal"
    align="left"
/&gt;</code></pre>
        </div>
    </div>
</section>

{{-- 🎭 4. VARIANT SELECTION --}}
<section>
    <h1 class="text-3xl font-bold mb-8">🎭 Variant Selection</h1>
    <p class="mb-6 text-gray-600">Choose different variants for different use cases:</p>
    
    <div class="bg-gray-50 p-6 rounded-lg mb-4">
        <pre class="text-sm"><code>{{-- Default variants (simple) --}}
&lt;flux:table.header variant="default" title="Simple Header" /&gt;
&lt;flux:table.footer variant="default" :pagination="$products" /&gt;

{{-- Composed variants (full-featured) --}}
&lt;flux:table.header variant="composed" :search="[]" :filters="[]" /&gt;
&lt;flux:table.footer variant="composed" layout="split" /&gt;</code></pre>
    </div>
</section>

{{-- 🌟 5. THEME SHOWCASE --}}
<section>
    <h1 class="text-3xl font-bold mb-8">🌟 Theme Showcase</h1>
    <p class="mb-6 text-gray-600">Different themes for different moods:</p>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="font-semibold mb-2">🔥 Phoenix Theme</h3>
            <code>theme="phoenix"</code>
            <p class="text-sm text-gray-600 mt-2">Warm orange gradients, perfect for enterprise apps</p>
        </div>
        
        <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="font-semibold mb-2">💎 Glass Theme</h3>
            <code>theme="glass"</code>
            <p class="text-sm text-gray-600 mt-2">Translucent design with blur effects</p>
        </div>
        
        <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="font-semibold mb-2">⚡ Neon Theme</h3>
            <code>theme="neon"</code>
            <p class="text-sm text-gray-600 mt-2">Dark with bright accent colors</p>
        </div>
        
        <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="font-semibold mb-2">🤍 Minimal Theme</h3>
            <code>theme="minimal"</code>
            <p class="text-sm text-gray-600 mt-2">Clean, simple, distraction-free</p>
        </div>
    </div>
</section>

{{-- 🔧 6. PROPS REFERENCE --}}
<section>
    <h1 class="text-3xl font-bold mb-8">🔧 Props Reference</h1>
    <p class="mb-6 text-gray-600">Complete props reference for all components:</p>
    
    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
        <h4 class="font-semibold text-yellow-800 mb-2">💡 Pro Tip</h4>
        <p class="text-yellow-700 text-sm">
            All components inherit theme and size from their parent flux:table automatically! 
            You only need to override when you want something different.
        </p>
    </div>
    
    {{-- You could add a comprehensive props table here --}}
</section>

{{-- 🎉 7. EVENT HANDLING --}}
<section>
    <h1 class="text-3xl font-bold mb-8">🎉 Event Handling</h1>
    <p class="mb-6 text-gray-600">Listen for events and create reactive experiences:</p>
    
    <div class="bg-gray-50 p-6 rounded-lg mb-4">
        <pre class="text-sm"><code>&lt;div 
    x-data="{ currentPage: 1, perPage: 15, searchQuery: '' }"
    @paginate="currentPage = $event.detail.page"
    @per-page-changed="perPage = $event.detail.perPage"
    @search-changed="searchQuery = $event.detail.query"
    @bulk-action="handleBulkAction($event.detail)"
&gt;
    &lt;flux:table :data="$variants"&gt;
        &lt;!-- Components automatically dispatch events --&gt;
    &lt;/flux:table&gt;
    
    {{-- Debug panel --}}
    &lt;div class="mt-4 p-4 bg-gray-100 rounded"&gt;
        Page: &lt;span x-text="currentPage"&gt;&lt;/span&gt; | 
        Per Page: &lt;span x-text="perPage"&gt;&lt;/span&gt; |
        Search: &lt;span x-text="searchQuery"&gt;&lt;/span&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
    </div>
</section>

</div>

{{-- 
✨ END OF GUIDE ✨

Happy table building! Each component is designed to work beautifully 
on its own or as part of a larger ensemble cast! 

Remember: You're not just building tables, you're creating experiences! 💫
--}}