<x-layouts.app>
    <x-slot:title>{{ isset($product) ? 'Edit Product (Builder)' : 'Create Product (Builder)' }}</x-slot:title>
    
    <div class="container max-w-7xl mx-auto px-4 py-8">
        <div class="max-w-7xl mx-auto">
            
            {{-- Page Header --}}
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center">
                            🏗️ {{ isset($product) ? 'Edit Product' : 'Create New Product' }}
                            <flux:badge color="blue" size="sm" class="ml-3">Builder Pattern</flux:badge>
                        </h1>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">
                            {{ isset($product) ? 'Update product using the new Builder Pattern wizard - variants preserved exactly' : 'Create products using the new Builder Pattern architecture' }}
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
                            <li>
                                <div class="flex items-center">
                                    <flux:icon name="chevron-right" class="w-3 h-3 text-gray-400 mx-1" />
                                    <a href="{{ route('products.index') }}" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2 dark:text-gray-400 dark:hover:text-white">
                                        Products
                                    </a>
                                </div>
                            </li>
                            <li aria-current="page">
                                <div class="flex items-center">
                                    <flux:icon name="chevron-right" class="w-3 h-3 text-gray-400 mx-1" />
                                    <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2 dark:text-gray-400">{{ isset($product) ? 'Edit (Builder)' : 'Create (Builder)' }}</span>
                                </div>
                            </li>
                        </ol>
                    </nav>
                </div>
                
                {{-- Action Buttons --}}
                <div class="mt-4 flex gap-3">
                    <a href="{{ route('products.create') }}" 
                       class="px-3 py-2 bg-gray-600 text-white text-sm rounded-md hover:bg-gray-700 transition-colors">
                        ← Back to Original Wizard
                    </a>
                    
                    @if(isset($product))
                        <a href="{{ route('products.show', $product) }}" 
                           class="px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors">
                            View Product
                        </a>
                    @endif
                    
                    <a href="{{ route('builder.test.demo') }}" 
                       class="px-3 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition-colors">
                        📊 Builder Demo
                    </a>
                </div>
            </div>

            {{-- Testing Minimal Livewire Component --}}
            <livewire:products.product-wizard-builder-test />
            
        </div>
    </div>
</x-layouts.app>