<!-- Marketplace Sync Tab Content -->
@php
    $shopifyStatus = $product->getShopifySyncStatus();
    $shopifySuggestions = app(\App\Services\ShopifyDataSuggestionsService::class)->generateSuggestions($product);
@endphp
<div class="space-y-6">
    <!-- Shopify Optimization Score -->
    <div class="bg-gradient-to-r from-emerald-50 to-blue-50 dark:from-emerald-900/20 dark:to-blue-900/20 rounded-xl p-6 border border-emerald-200 dark:border-emerald-800">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">Shopify Optimization</flux:heading>
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <div class="text-3xl font-bold text-{{ $shopifySuggestions['optimization_score']['overall_score'] >= 80 ? 'emerald' : ($shopifySuggestions['optimization_score']['overall_score'] >= 60 ? 'yellow' : 'red') }}-600">
                        {{ $shopifySuggestions['optimization_score']['grade'] }}
                    </div>
                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $shopifySuggestions['optimization_score']['overall_score'] }}/100
                    </div>
                </div>
                <div class="w-16 h-16 relative">
                    <svg class="w-16 h-16 transform -rotate-90" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="16" fill="none" stroke="currentColor" stroke-width="2" 
                                class="text-zinc-200 dark:text-zinc-700"></circle>
                        <circle cx="18" cy="18" r="16" fill="none" stroke="currentColor" stroke-width="2" 
                                class="text-{{ $shopifySuggestions['optimization_score']['overall_score'] >= 80 ? 'emerald' : ($shopifySuggestions['optimization_score']['overall_score'] >= 60 ? 'yellow' : 'red') }}-500"
                                stroke-dasharray="{{ $shopifySuggestions['optimization_score']['overall_score'] }}, 100"></circle>
                    </svg>
                </div>
            </div>
        </div>
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
            {{ $shopifySuggestions['optimization_score']['summary'] }}
        </p>
        
        <!-- Quick Stats -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            @foreach($shopifySuggestions['optimization_score']['category_scores'] as $category => $score)
                <div class="text-center p-3 bg-white/50 dark:bg-zinc-800/50 rounded-lg">
                    <div class="text-lg font-semibold text-{{ $score >= 80 ? 'emerald' : ($score >= 60 ? 'yellow' : 'red') }}-600">
                        {{ $score }}
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 capitalize">
                        {{ str_replace('_', ' ', $category) }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Detailed Suggestions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Category Suggestions -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center gap-3 mb-4">
                <flux:icon name="tag" class="w-5 h-5 text-purple-600" />
                <flux:heading size="base" class="text-zinc-900 dark:text-zinc-100">Category & Classification</flux:heading>
                <flux:badge variant="outline" class="bg-{{ $shopifySuggestions['category']['status'] === 'good' ? 'emerald' : 'yellow' }}-50 text-{{ $shopifySuggestions['category']['status'] === 'good' ? 'emerald' : 'yellow' }}-700">
                    {{ ucfirst($shopifySuggestions['category']['status']) }}
                </flux:badge>
            </div>
            
            @if(isset($shopifySuggestions['category']['suggestions']['current_category']))
                <div class="mb-4 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Current Category</div>
                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $shopifySuggestions['category']['suggestions']['current_category']['full_name'] }}
                    </div>
                    <div class="text-xs text-emerald-600 mt-1">
                        {{ $shopifySuggestions['category']['suggestions']['current_category']['confidence'] }}% confidence
                    </div>
                </div>
            @endif

            @if(!empty($shopifySuggestions['category']['warnings']))
                <div class="space-y-2 mb-4">
                    @foreach($shopifySuggestions['category']['warnings'] as $warning)
                        <div class="flex items-start gap-2 text-sm text-yellow-700 dark:text-yellow-300">
                            <flux:icon name="triangle-alert" class="w-4 h-4 mt-0.5 flex-shrink-0" />
                            <span>{{ $warning }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(isset($shopifySuggestions['category']['suggestions']['alternative_categories']))
                <div class="space-y-2">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Alternative Categories</div>
                    @foreach($shopifySuggestions['category']['suggestions']['alternative_categories'] as $alt)
                        <div class="text-sm text-zinc-600 dark:text-zinc-400 p-2 bg-zinc-50 dark:bg-zinc-900 rounded">
                            <div>{{ $alt['full_name'] }}</div>
                            <div class="text-xs text-zinc-500 mt-1">{{ $alt['confidence'] }}% confidence • {{ $alt['reason'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- SEO Suggestions -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center gap-3 mb-4">
                <flux:icon name="search" class="w-5 h-5 text-blue-600" />
                <flux:heading size="base" class="text-zinc-900 dark:text-zinc-100">SEO Optimization</flux:heading>
                <flux:badge variant="outline" class="bg-{{ $shopifySuggestions['seo']['status'] === 'good' ? 'emerald' : 'yellow' }}-50 text-{{ $shopifySuggestions['seo']['status'] === 'good' ? 'emerald' : 'yellow' }}-700">
                    {{ ucfirst($shopifySuggestions['seo']['status']) }}
                </flux:badge>
            </div>

            <div class="space-y-4">
                <!-- Title Analysis -->
                <div>
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-1">Title Length</div>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-zinc-200 dark:bg-zinc-700 rounded-full h-2">
                            <div class="h-2 bg-{{ $shopifySuggestions['seo']['title_analysis']['status'] === 'good' ? 'emerald' : 'yellow' }}-500 rounded-full" 
                                 style="width: {{ min(100, ($shopifySuggestions['seo']['title_analysis']['length'] / 70) * 100) }}%"></div>
                        </div>
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $shopifySuggestions['seo']['title_analysis']['length'] }}/70</span>
                    </div>
                </div>

                <!-- Description Analysis -->
                <div>
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-1">Description</div>
                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                        @if($shopifySuggestions['seo']['description_analysis']['exists'])
                            {{ $shopifySuggestions['seo']['description_analysis']['length'] }} characters
                            <span class="text-{{ $shopifySuggestions['seo']['description_analysis']['status'] === 'good' ? 'emerald' : 'yellow' }}-600">
                                ({{ $shopifySuggestions['seo']['description_analysis']['status'] === 'good' ? 'Good' : 'Could be longer' }})
                            </span>
                        @else
                            <span class="text-red-600">Missing description</span>
                        @endif
                    </div>
                </div>

                <!-- Keyword Suggestions -->
                @if(!empty($shopifySuggestions['seo']['suggested_keywords']))
                    <div>
                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-2">Suggested Keywords</div>
                        <div class="flex flex-wrap gap-1">
                            @foreach(array_slice($shopifySuggestions['seo']['suggested_keywords'], 0, 6) as $keyword)
                                <flux:badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200 text-xs">
                                    {{ $keyword }}
                                </flux:badge>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Data Quality -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center gap-3 mb-4">
                <flux:icon name="database" class="w-5 h-5 text-indigo-600" />
                <flux:heading size="base" class="text-zinc-900 dark:text-zinc-100">Data Completeness</flux:heading>
                <flux:badge variant="outline" class="bg-{{ $shopifySuggestions['data_quality']['status'] === 'excellent' ? 'emerald' : ($shopifySuggestions['data_quality']['status'] === 'good' ? 'blue' : 'yellow') }}-50 text-{{ $shopifySuggestions['data_quality']['status'] === 'excellent' ? 'emerald' : ($shopifySuggestions['data_quality']['status'] === 'good' ? 'blue' : 'yellow') }}-700">
                    {{ $shopifySuggestions['data_quality']['completion_percentage'] }}%
                </flux:badge>
            </div>

            <div class="mb-4">
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-zinc-600 dark:text-zinc-400">Completion Score</span>
                    <span class="font-medium">{{ $shopifySuggestions['data_quality']['completion_score'] }}/{{ $shopifySuggestions['data_quality']['max_score'] }}</span>
                </div>
                <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2">
                    <div class="h-2 bg-gradient-to-r from-red-500 via-yellow-500 to-emerald-500 rounded-full" 
                         style="width: {{ $shopifySuggestions['data_quality']['completion_percentage'] }}%"></div>
                </div>
            </div>

            @if(!empty($shopifySuggestions['data_quality']['recommendations']))
                <div class="space-y-2">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Recommendations</div>
                    @foreach(array_slice($shopifySuggestions['data_quality']['recommendations'], 0, 4) as $recommendation)
                        <div class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                            <flux:icon name="lightbulb" class="w-4 h-4 mt-0.5 flex-shrink-0 text-yellow-500" />
                            <span>{{ $recommendation }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Variant & Pricing -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center gap-3 mb-4">
                <flux:icon name="layers" class="w-5 h-5 text-green-600" />
                <flux:heading size="base" class="text-zinc-900 dark:text-zinc-100">Variants & Pricing</flux:heading>
                <flux:badge variant="outline" class="bg-{{ $shopifySuggestions['variants']['status'] === 'good' ? 'emerald' : 'yellow' }}-50 text-{{ $shopifySuggestions['variants']['status'] === 'good' ? 'emerald' : 'yellow' }}-700">
                    {{ $shopifySuggestions['variants']['variant_count'] }} variants
                </flux:badge>
            </div>

            <div class="space-y-4">
                <!-- Variant Stats -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Variants</div>
                        <div class="text-lg font-semibold text-emerald-600">{{ $shopifySuggestions['variants']['variant_count'] }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Images</div>
                        <div class="text-lg font-semibold text-blue-600">{{ $shopifySuggestions['images']['image_count'] }}</div>
                    </div>
                </div>

                <!-- Attributes -->
                @if(!empty($shopifySuggestions['variants']['attribute_usage']))
                    <div>
                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-2">Attributes</div>
                        <div class="flex flex-wrap gap-1">
                            @foreach($shopifySuggestions['variants']['attribute_usage'] as $attribute => $count)
                                <flux:badge variant="outline" class="bg-green-50 text-green-700 border-green-200 text-xs">
                                    {{ ucfirst($attribute) }} ({{ $count }})
                                </flux:badge>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Warnings -->
                @if(!empty($shopifySuggestions['variants']['warnings']) || !empty($shopifySuggestions['pricing']['warnings']))
                    <div class="space-y-1">
                        @foreach(array_merge($shopifySuggestions['variants']['warnings'] ?? [], $shopifySuggestions['pricing']['warnings'] ?? []) as $warning)
                            <div class="flex items-start gap-2 text-sm text-red-600 dark:text-red-400">
                                <flux:icon name="circle-alert" class="w-4 h-4 mt-0.5 flex-shrink-0" />
                                <span>{{ $warning }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <flux:heading size="lg">Marketplace Sync Status</flux:heading>
    <div class="space-y-6">
        <!-- Shopify Status -->
        <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-emerald-100 dark:bg-emerald-900 rounded-lg flex items-center justify-center">
                        <flux:icon name="shopping-bag" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <span class="text-lg font-medium text-zinc-900 dark:text-zinc-100">Shopify</span>
                </div>
                <flux:button variant="outline" size="sm">
                    <flux:icon name="refresh-cw" class="w-4 h-4 mr-2" />
                    Sync Now
                </flux:button>
            </div>
            <x-sync-status-badge 
                :status="$shopifyStatus['status']"
                :last-synced-at="$shopifyStatus['last_synced_at'] ? \Carbon\Carbon::parse($shopifyStatus['last_synced_at']) : null"
                marketplace="Shopify"
                size="md"
            />
            @if($shopifyStatus['total_colors'] > 0)
                <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $shopifyStatus['colors_synced'] }}/{{ $shopifyStatus['total_colors'] }} color variants synced
                    @if($shopifyStatus['has_failures'])
                        <span class="text-red-500 ml-2">• Has failures</span>
                    @endif
                </div>
            @endif
        </div>

        <!-- eBay Status Placeholder -->
        <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6 opacity-50">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                        <flux:icon name="globe" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                    </div>
                    <span class="text-lg font-medium text-zinc-900 dark:text-zinc-100">eBay</span>
                </div>
                <flux:badge variant="outline" class="bg-zinc-50 text-zinc-500 border-zinc-200">
                    Coming Soon
                </flux:badge>
            </div>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                eBay marketplace integration is in development
            </div>
        </div>

        <!-- Amazon Status Placeholder -->
        <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6 opacity-50">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-orange-100 dark:bg-orange-900 rounded-lg flex items-center justify-center">
                        <flux:icon name="shopping-cart" class="w-4 h-4 text-orange-600 dark:text-orange-400" />
                    </div>
                    <span class="text-lg font-medium text-zinc-900 dark:text-zinc-100">Amazon</span>
                </div>
                <flux:badge variant="outline" class="bg-zinc-50 text-zinc-500 border-zinc-200">
                    Coming Soon
                </flux:badge>
            </div>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                Amazon marketplace integration is planned for future release
            </div>
        </div>
    </div>
</div>