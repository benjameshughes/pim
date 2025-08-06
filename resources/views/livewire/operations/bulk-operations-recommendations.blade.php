<div>
    <x-breadcrumb :items="[
        ['name' => 'Operations'],
        ['name' => 'Bulk Operations'],
        ['name' => 'Smart Recommendations']
    ]" />

    <!-- Header -->
    <div class="mb-8">
        <flux:heading size="xl">Bulk Operations - Smart Recommendations</flux:heading>
        <flux:subheading>AI-powered suggestions to optimize your product data and operations</flux:subheading>
    </div>

    <!-- Tab Navigation -->
    <x-route-tabs :tabs="$tabs" class="mb-6">
        <div class="p-6">
            @if($loadingRecommendations)
                <!-- Loading State -->
                <div class="text-center py-12">
                    <flux:icon name="arrow-path" class="w-16 h-16 text-blue-500 mx-auto mb-4 animate-spin" />
                    <flux:heading size="lg" class="text-zinc-600 dark:text-zinc-400 mb-2">Analyzing Your Data</flux:heading>
                    <flux:subheading class="text-zinc-500 dark:text-zinc-500">
                        Generating personalized recommendations based on your product data...
                    </flux:subheading>
                </div>
            @elseif(empty($recommendations))
                <!-- No Recommendations State -->
                <div class="text-center py-12">
                    <flux:icon name="check-circle" class="w-16 h-16 text-emerald-500 mx-auto mb-4" />
                    <flux:heading size="lg" class="text-zinc-600 dark:text-zinc-400 mb-2">All Set!</flux:heading>
                    <flux:subheading class="text-zinc-500 dark:text-zinc-500 mb-4">
                        No immediate recommendations at this time. Your data looks good!
                    </flux:subheading>
                    <flux:button wire:click="loadSmartRecommendations" variant="outline">
                        <flux:icon name="arrow-path" class="w-4 h-4 mr-2" />
                        Refresh Analysis
                    </flux:button>
                </div>
            @else
                <!-- Header with Stats -->
                <div class="bg-gradient-to-r from-purple-50 to-blue-50 dark:from-purple-900/20 dark:to-blue-900/20 rounded-lg border border-purple-200 dark:border-purple-800 p-6 mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <flux:icon name="lightbulb" class="w-8 h-8 text-purple-600" />
                                <flux:heading size="lg">{{ count($recommendations) }} Smart Recommendations</flux:heading>
                            </div>
                            <flux:subheading class="text-purple-700 dark:text-purple-300">
                                @if($selectedVariantsCount > 0)
                                    Based on analysis of {{ $selectedVariantsCount }} selected variants and overall data patterns
                                @else
                                    Based on analysis of your overall product catalog and data patterns
                                @endif
                            </flux:subheading>
                        </div>
                        <div class="text-right">
                            @php
                                $highPriority = collect($recommendations)->where('priority', 'high')->count();
                                $mediumPriority = collect($recommendations)->where('priority', 'medium')->count();
                                $lowPriority = collect($recommendations)->where('priority', 'low')->count();
                            @endphp
                            <div class="flex gap-4 text-sm">
                                @if($highPriority > 0)
                                    <div class="text-red-600 font-medium">{{ $highPriority }} High</div>
                                @endif
                                @if($mediumPriority > 0)
                                    <div class="text-amber-600 font-medium">{{ $mediumPriority }} Medium</div>
                                @endif
                                @if($lowPriority > 0)
                                    <div class="text-blue-600 font-medium">{{ $lowPriority }} Low</div>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between mt-4">
                        <flux:button wire:click="loadSmartRecommendations" variant="outline" size="sm">
                            <flux:icon name="arrow-path" class="w-4 h-4 mr-2" />
                            Refresh Analysis
                        </flux:button>
                    </div>
                </div>

                <!-- Recommendations by Category -->
                @foreach($groupedRecommendations as $category => $categoryRecommendations)
                    <div class="mb-8">
                        <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                            @switch($category)
                                @case('SEO & Marketing')
                                    <flux:icon name="megaphone" class="w-5 h-5 text-purple-600" />
                                    @break
                                @case('Data Quality')
                                    <flux:icon name="shield-check" class="w-5 h-5 text-emerald-600" />
                                    @break
                                @case('Pricing')
                                    <flux:icon name="currency-dollar" class="w-5 h-5 text-green-600" />
                                    @break
                                @case('Compliance')
                                    <flux:icon name="check-badge" class="w-5 h-5 text-blue-600" />
                                    @break
                                @case('Marketing')
                                    <flux:icon name="photo" class="w-5 h-5 text-pink-600" />
                                    @break
                                @case('Distribution')
                                    <flux:icon name="globe-alt" class="w-5 h-5 text-indigo-600" />
                                    @break
                                @default
                                    <flux:icon name="lightbulb" class="w-5 h-5 text-amber-600" />
                            @endswitch
                            {{ $category }}
                        </flux:heading>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            @foreach($categoryRecommendations as $recommendation)
                                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6 hover:shadow-lg transition-shadow">
                                    <!-- Header -->
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 rounded-lg bg-{{ $this->getPriorityColor($recommendation['priority']) }}-100 dark:bg-{{ $this->getPriorityColor($recommendation['priority']) }}-900/20">
                                                <flux:icon name="{{ $this->getPriorityIcon($recommendation['priority']) }}" class="w-5 h-5 text-{{ $this->getPriorityColor($recommendation['priority']) }}-600" />
                                            </div>
                                            <div>
                                                <flux:heading size="md" class="text-zinc-900 dark:text-zinc-100">
                                                    {{ $recommendation['title'] }}
                                                </flux:heading>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-{{ $this->getPriorityColor($recommendation['priority']) }}-100 dark:bg-{{ $this->getPriorityColor($recommendation['priority']) }}-900/20 text-{{ $this->getPriorityColor($recommendation['priority']) }}-700 dark:text-{{ $this->getPriorityColor($recommendation['priority']) }}-300">
                                                        {{ ucfirst($recommendation['priority']) }} Priority
                                                    </span>
                                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ $recommendation['estimated_time'] }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <flux:button 
                                            wire:click="dismissRecommendation('{{ $recommendation['id'] }}')"
                                            variant="ghost" 
                                            size="sm"
                                            class="text-zinc-400 hover:text-zinc-600"
                                        >
                                            <flux:icon name="x-mark" class="w-4 h-4" />
                                        </flux:button>
                                    </div>

                                    <!-- Description -->
                                    <div class="mb-4">
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">
                                            {{ $recommendation['description'] }}
                                        </p>
                                        <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-3">
                                            <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">Expected Impact</div>
                                            <div class="text-sm text-zinc-700 dark:text-zinc-300">{{ $recommendation['impact'] }}</div>
                                        </div>
                                    </div>

                                    <!-- Action -->
                                    <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4">
                                        <div class="flex items-center justify-between">
                                            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                                {{ $recommendation['action'] }}
                                            </div>
                                            <flux:button 
                                                wire:click="executeRecommendation('{{ $recommendation['id'] }}')"
                                                variant="primary"
                                                size="sm"
                                            >
                                                @switch($recommendation['type'])
                                                    @case('optimization')
                                                        <flux:icon name="rocket-launch" class="w-4 h-4 mr-2" />
                                                        Optimize Now
                                                        @break
                                                    @case('data-quality')
                                                        <flux:icon name="wrench" class="w-4 h-4 mr-2" />
                                                        Fix Issues
                                                        @break
                                                    @case('pricing')
                                                        <flux:icon name="calculator" class="w-4 h-4 mr-2" />
                                                        Review Pricing
                                                        @break
                                                    @case('compliance')
                                                        <flux:icon name="check" class="w-4 h-4 mr-2" />
                                                        Apply Fix
                                                        @break
                                                    @case('marketing')
                                                        <flux:icon name="sparkles" class="w-4 h-4 mr-2" />
                                                        Improve Now
                                                        @break
                                                    @case('distribution')
                                                        <flux:icon name="arrow-up-right" class="w-4 h-4 mr-2" />
                                                        Expand Reach
                                                        @break
                                                    @default
                                                        <flux:icon name="arrow-right" class="w-4 h-4 mr-2" />
                                                        Take Action
                                                @endswitch
                                            </flux:button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <!-- Quick Actions Summary -->
                <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                    <flux:heading size="md" class="mb-4">Quick Action Summary</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @php
                            $highPriorityRecs = collect($recommendations)->where('priority', 'high');
                            $quickWins = collect($recommendations)->filter(function($rec) {
                                return str_contains($rec['estimated_time'], '5 minutes') || str_contains($rec['estimated_time'], '2 minutes');
                            });
                            $highImpact = collect($recommendations)->filter(function($rec) {
                                return str_contains($rec['impact'], 'High');
                            });
                        @endphp
                        
                        <div class="text-center p-4 bg-white dark:bg-zinc-700 rounded-lg">
                            <div class="text-2xl font-bold text-red-600 mb-1">{{ $highPriorityRecs->count() }}</div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">High Priority Items</div>
                        </div>
                        
                        <div class="text-center p-4 bg-white dark:bg-zinc-700 rounded-lg">
                            <div class="text-2xl font-bold text-emerald-600 mb-1">{{ $quickWins->count() }}</div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">Quick Wins (≤5 min)</div>
                        </div>
                        
                        <div class="text-center p-4 bg-white dark:bg-zinc-700 rounded-lg">
                            <div class="text-2xl font-bold text-purple-600 mb-1">{{ $highImpact->count() }}</div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">High Impact Actions</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-route-tabs>
</div>