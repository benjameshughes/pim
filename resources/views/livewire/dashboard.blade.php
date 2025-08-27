<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                🚧 Dashboard Under Maintenance
            </h1>
            <p class="text-xl text-gray-600 dark:text-gray-400 mb-8">
                The dashboard is temporarily disabled while we debug production issues.
            </p>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 max-w-2xl mx-auto">
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">
                    Welcome back, {{ auth()->user()->name }}!
                </h2>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    {{ now()->format('l, F j, Y \a\t g:i A') }}
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-8">
                    <a href="{{ route('products.index') }}" 
                       class="block p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors">
                        <h3 class="font-medium text-blue-900 dark:text-blue-200">📦 Products</h3>
                        <p class="text-sm text-blue-700 dark:text-blue-300">Manage your product catalog</p>
                    </a>
                    
                    <a href="{{ route('images.index') }}" 
                       class="block p-4 bg-green-50 dark:bg-green-900/20 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/40 transition-colors">
                        <h3 class="font-medium text-green-900 dark:text-green-200">🖼️ Images</h3>
                        <p class="text-sm text-green-700 dark:text-green-300">Manage your image library</p>
                    </a>
                    
                    
                    <a href="{{ route('settings.profile') }}" 
                       class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                        <h3 class="font-medium text-gray-900 dark:text-gray-200">⚙️ Settings</h3>
                        <p class="text-sm text-gray-700 dark:text-gray-300">Account preferences</p>
                    </a>
                    
                    <div class="block p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                        <h3 class="font-medium text-purple-900 dark:text-purple-200 mb-2">🧪 Test Pusher</h3>
                        <p class="text-sm text-purple-700 dark:text-purple-300 mb-3">Debug Echo + Pusher connection</p>
                        <flux:button wire:click="testPusher" variant="outline" size="sm">
                            Test Broadcasting
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>