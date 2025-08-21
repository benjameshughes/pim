<div class="space-y-8">
    {{-- ✨ PHOENIX TABLE DEMO HEADER --}}
    <div class="text-center">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">
            ✨ Phoenix Table Component Demo 🔥
        </h1>
        <p class="text-lg text-gray-600 dark:text-gray-400">
            Showcasing all themes, features, and sass-powered intelligence
        </p>
    </div>

    {{-- 🎯 MODERN THEME (DEFAULT) --}}
    <div class="space-y-4">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">🎯 Modern Theme</h2>
        <flux:table 
            :data="$data" 
            :columns="$columns"
            theme="modern"
            searchable
            selectable
            sortable
            hoverable
            :glitter-intensity="'medium'"
        />
    </div>

    {{-- 🌟 PHOENIX THEME --}}
    <div class="space-y-4">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">🔥 Phoenix Theme</h2>
        <flux:table 
            :data="$data" 
            :columns="$columns"
            theme="phoenix"
            searchable
            selectable
            sortable
            hoverable
            animation="glitter"
            :glitter-intensity="'high'"
        />
    </div>

    {{-- 💎 GLASS THEME --}}
    <div class="space-y-4">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">💎 Glass Theme</h2>
        <flux:table 
            :data="$data" 
            :columns="$columns"
            theme="glass"
            searchable
            sortable
            hoverable
            animation="fade"
            :glitter-intensity="'low'"
        />
    </div>

    {{-- ⚡ NEON THEME --}}
    <div class="space-y-4">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">⚡ Neon Theme</h2>
        <flux:table 
            :data="$data" 
            :columns="$columns"
            theme="neon"
            searchable
            selectable
            sortable
            hoverable
            striped
            animation="bounce"
            :glitter-intensity="'maximum'"
        />
    </div>

    {{-- 📋 MINIMAL THEME --}}
    <div class="space-y-4">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">📋 Minimal Theme</h2>
        <flux:table 
            :data="$data" 
            :columns="$columns"
            theme="minimal"
            sortable
            hoverable
            bordered
            size="compact"
            :glitter-intensity="'none'"
        />
    </div>

    {{-- 🎪 FEATURES DEMO --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Loading State --}}
        <div class="space-y-4">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">⏳ Loading State</h3>
            <flux:table 
                :data="[]" 
                :columns="$columns"
                theme="phoenix"
                loading
                :glitter-intensity="'medium'"
            />
        </div>

        {{-- Empty State --}}
        <div class="space-y-4">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">📭 Empty State</h3>
            <flux:table 
                :data="[]" 
                :columns="$columns"
                theme="modern"
                empty-message="No sass found!"
                empty-icon="face-frown"
                searchable
                :glitter-intensity="'low'"
            />
        </div>
    </div>

    {{-- 🎨 SIZE VARIANTS --}}
    <div class="space-y-6">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">📏 Size Variants</h2>
        
        <div class="grid grid-cols-1 gap-6">
            {{-- Compact --}}
            <div class="space-y-2">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Compact Size</h3>
                <flux:table 
                    :data="array_slice($data, 0, 3)" 
                    :columns="$columns"
                    theme="minimal"
                    size="compact"
                    sortable
                    :glitter-intensity="'none'"
                />
            </div>
            
            {{-- Spacious --}}
            <div class="space-y-2">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Spacious Size</h3>
                <flux:table 
                    :data="array_slice($data, 0, 3)" 
                    :columns="$columns"
                    theme="phoenix"
                    size="spacious"
                    sortable
                    :glitter-intensity="'high'"
                />
            </div>
        </div>
    </div>

    {{-- 🚀 PERFORMANCE INFO --}}
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-4">
            🚀 Phoenix Table Features
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div class="flex items-center gap-2">
                <span class="text-green-600 dark:text-green-400">✓</span>
                <span class="text-blue-800 dark:text-blue-200">5 Beautiful themes</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-green-600 dark:text-green-400">✓</span>
                <span class="text-blue-800 dark:text-blue-200">Alpine.js powered</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-green-600 dark:text-green-400">✓</span>
                <span class="text-blue-800 dark:text-blue-200">Real-time search & sorting</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-green-600 dark:text-green-400">✓</span>
                <span class="text-blue-800 dark:text-blue-200">Row selection</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-green-600 dark:text-green-400">✓</span>
                <span class="text-blue-800 dark:text-blue-200">Loading & empty states</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-green-600 dark:text-green-400">✓</span>
                <span class="text-blue-800 dark:text-blue-200">Glitter animations ✨</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-green-600 dark:text-green-400">✓</span>
                <span class="text-blue-800 dark:text-blue-200">Responsive design</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-green-600 dark:text-green-400">✓</span>
                <span class="text-blue-800 dark:text-blue-200">Flux UI integration</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-green-600 dark:text-green-400">✓</span>
                <span class="text-blue-800 dark:text-blue-200">Pure sass energy 🔥</span>
            </div>
        </div>
    </div>
</div>