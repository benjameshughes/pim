<x-layouts.app>
    <x-slot:title>Import Products</x-slot:title>
    
    <div class="container max-w-4xl mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            
            {{-- Page Header --}}
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                            📤 Import Products
                        </h1>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">
                            Upload and import product data from CSV files
                        </p>
                    </div>
                    
                    {{-- Breadcrumb Navigation --}}
                    <nav class="flex" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-3">
                            <li class="inline-flex items-center">
                                <a href="{{ route('dashboard') }}" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">
                                    <flux:icon name="squares-2x2" class="w-3 h-3 mr-2.5" />
                                    Dashboard
                                </a>
                            </li>
                            <li aria-current="page">
                                <div class="flex items-center">
                                    <flux:icon name="chevron-right" class="w-3 h-3 text-gray-400 mx-1" />
                                    <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2 dark:text-gray-400">Import Products</span>
                                </div>
                            </li>
                        </ol>
                    </nav>
                </div>
            </div>

            {{-- Import Component --}}
            <livewire:import.simple-product-import />
            
        </div>
    </div>
</x-layouts.app>