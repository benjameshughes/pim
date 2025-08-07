<x-layouts.app>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- 
                🎉 NEW FilamentPHP-Style Approach!
                No dedicated Livewire component needed - just use <x-stacked-list> component
                This uses ProductIndex under the hood but renders directly in blade
            --}}
            <x-stacked-list type="products" />
            
            <div class="mt-8 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                <h3 class="text-sm font-medium text-green-800 dark:text-green-300 mb-2">
                    🚀 FilamentPHP-Style Implementation
                </h3>
                <ul class="text-sm text-green-700 dark:text-green-400 space-y-1">
                    <li>• No dedicated ProductIndex component route</li>
                    <li>• Just <code>&lt;x-stacked-list type="products" /&gt;</code> component in blade</li>
                    <li>• Uses ProductIndex under the hood via Livewire::mount()</li>
                    <li>• All functionality preserved (search, sort, filters, bulk actions)</li>
                    <li>• Pure PHP configuration + HTML/CSS/AlpineJS rendering</li>
                </ul>
                <p class="text-xs text-green-600 dark:text-green-500 mt-2">
                    Compare: <a href="{{ route('products.component') }}" class="underline">Old component approach</a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>