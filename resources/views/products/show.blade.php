<x-layouts.app>
    <x-slot:title>{{ $product->name ?? 'Product Details' }}</x-slot:title>
    
    <div class="mx-auto px-4 py-8">
        <div class="mx-auto">
            
            {{-- Breadcrumb Navigation --}}
            <nav class="flex mb-8" aria-label="Breadcrumb">
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
                            <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2 dark:text-gray-400">{{ $product->name ?? 'Product' }}</span>
                        </div>
                    </li>
                </ol>
            </nav>

            {{-- Product Show Component --}}
            <livewire:products.product-show :product="$product" />
            
        </div>
    </div>
</x-layouts.app>