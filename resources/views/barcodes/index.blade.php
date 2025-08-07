<x-layouts.app>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- 
                🎉 NEW FilamentPHP-Style Approach for Barcodes!
                Uses BarcodeIndex component under the hood via <x-stacked-list> component
            --}}
            <x-stacked-list type="barcodes" />
            
            <div class="mt-8 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300 mb-2">
                    🎯 Barcodes StackedList
                </h3>
                <ul class="text-sm text-blue-700 dark:text-blue-400 space-y-1">
                    <li>• Same <code>&lt;x-stacked-list&gt;</code> approach for barcodes</li>
                    <li>• Uses BarcodeIndex component configuration</li>
                    <li>• Works with product relationships and search</li>
                    <li>• All barcode functionality preserved</li>
                </ul>
                <p class="text-xs text-blue-600 dark:text-blue-500 mt-2">
                    Compare: <a href="{{ route('barcodes.component') }}" class="underline">Old component approach</a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>