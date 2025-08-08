{{-- Example usage of the TabSet system --}}
<x-layouts.app.sidebar>
    <x-page-template title="TabSet Demo">
        @php
            use App\UI\Components\TabSet;
            use App\UI\Components\Tab;
            
            // Method 1: Using builder pattern with Tab objects and Collections
            $tabSet = TabSet::make()
                ->tab(
                    Tab::make('overview')
                        ->label('Overview')
                        ->icon('chart-bar')
                        ->route('products.view', collect(['product' => 1]))
                        ->badge(5, 'blue')
                        ->extraAttributes(collect(['data-test' => 'overview-tab']))
                )
                ->tab(
                    Tab::make('details')
                        ->label('Product Details')
                        ->icon('information-circle')
                        ->route('products.product.edit', ['product' => 1])
                )
                ->tab(
                    Tab::make('variants')
                        ->label('Variants')
                        ->icon('squares-2x2')
                        ->route('products.variants.index', ['product' => 1])
                        ->badge(fn() => 12, 'green')
                )
                ->tab(
                    Tab::make('analytics')
                        ->label('Analytics')
                        ->icon('chart-line')
                        ->route('products.analytics', ['product' => 1])
                        ->hidden(true) // This tab will not show
                )
                ->baseRoute('products')
                ->defaultRouteParameters(collect(['product' => 1]))
                ->wireNavigate(true);
            
            // Method 2: Using array format (backwards compatibility)  
            $simpleTabSet = TabSet::make()
                ->baseRoute('products')
                ->defaultRouteParameters(collect(['product' => 1]))
                ->tabs([
                    [
                        'key' => 'overview',
                        'label' => 'Overview',
                        'icon' => 'eye',
                        'badge' => 3
                    ],
                    [
                        'key' => 'edit',
                        'label' => 'Edit',
                        'icon' => 'pencil',
                        'route' => 'products.product.edit',
                        'routeParameters' => ['product' => 1]
                    ]
                ]);

            // Method 3: Collection-based tabs for advanced manipulation
            $collectionTabSet = TabSet::make()
                ->tabs(collect([
                    Tab::make('dashboard')->label('Dashboard')->icon('home'),
                    Tab::make('analytics')->label('Analytics')->icon('chart-line'),
                    Tab::make('settings')->label('Settings')->icon('cog')
                ]));

            // Demonstrate Collection methods
            $navigation = $tabSet->toCollection();
            $activeTab = $navigation->firstWhere('active', true);
            $tabCount = $navigation->count();
        @endphp
        
        <div class="space-y-8">
            <div>
                <h2 class="text-lg font-semibold mb-4">Collection-Powered TabSet</h2>
                <x-ui.tab-set :tabs="$tabSet" />
                <div class="bg-zinc-50 dark:bg-zinc-800 p-4 rounded-lg">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        TabSet using Laravel Collections internally for better data manipulation.
                        Features fluent Builder Pattern with Collection support for route parameters and extra attributes.
                    </p>
                    <div class="mt-3 text-xs text-zinc-500 dark:text-zinc-500">
                        <strong>Collection Features:</strong> Total tabs: {{ $tabCount }}, 
                        Active tab: {{ $activeTab['label'] ?? 'None' }}
                    </div>
                </div>
            </div>
            
            <div>
                <h2 class="text-lg font-semibold mb-4">Collection-Based Tab Creation</h2>
                <x-ui.tab-set :tabs="$collectionTabSet" />
                <div class="bg-zinc-50 dark:bg-zinc-800 p-4 rounded-lg">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        Tabs created directly from a Collection of Tab objects, 
                        demonstrating the flexible input handling.
                    </p>
                </div>
            </div>
            
            <div>
                <h2 class="text-lg font-semibold mb-4">Array-Based TabSet (Legacy Support)</h2>
                <x-ui.tab-set :tabs="$simpleTabSet" />
                <div class="bg-zinc-50 dark:bg-zinc-800 p-4 rounded-lg">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        TabSet built using array configuration for backwards compatibility.
                        Arrays are automatically converted to Collections internally.
                    </p>
                </div>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-3">
                    <flux:icon name="information-circle" class="inline h-5 w-5 mr-1" />
                    Collection Benefits
                </h3>
                <ul class="space-y-2 text-sm text-blue-800 dark:text-blue-200">
                    <li>• <strong>Fluent API:</strong> Use Collection methods like <code>filter()</code>, <code>map()</code>, <code>firstWhere()</code></li>
                    <li>• <strong>Better Performance:</strong> Lazy evaluation and memory efficiency</li>
                    <li>• <strong>Advanced Manipulation:</strong> <code>toCollection()</code> for complex tab operations</li>
                    <li>• <strong>Type Safety:</strong> Full support for both arrays and Collections as parameters</li>
                    <li>• <strong>Laravel Integration:</strong> Native Laravel Collection methods available throughout</li>
                </ul>
            </div>
        </div>
    </x-page-template>
</x-layouts.app.sidebar>