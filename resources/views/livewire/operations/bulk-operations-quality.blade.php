<div>
    <x-breadcrumb :items="[
        ['name' => 'Operations'],
        ['name' => 'Bulk Operations'],
        ['name' => 'Data Quality']
    ]" />

    <!-- Header -->
    <div class="mb-8">
        <flux:heading size="xl">Bulk Operations - Data Quality</flux:heading>
        <flux:subheading>Monitor and improve the quality of your product data</flux:subheading>
    </div>

    <!-- Tab Navigation -->
    <x-route-tabs :tabs="$tabs" class="mb-6">
        <div class="p-6">
            <!-- Quality Score Card -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg border border-blue-200 dark:border-blue-800 p-6 mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <flux:icon name="shield-check" class="w-8 h-8 text-blue-600" />
                            <flux:heading size="lg">Overall Data Quality Score</flux:heading>
                        </div>
                        <flux:subheading class="text-blue-700 dark:text-blue-300">
                            Based on completeness across {{ $qualityResults['total_products'] ?? 0 }} products and {{ $qualityResults['total_variants'] ?? 0 }} variants
                        </flux:subheading>
                    </div>
                    <div class="text-right">
                        <div class="text-4xl font-bold {{ $qualityScore >= 80 ? 'text-emerald-600' : ($qualityScore >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                            {{ $qualityScore }}%
                        </div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                            @if($qualityScore >= 90)
                                Excellent
                            @elseif($qualityScore >= 80)
                                Good
                            @elseif($qualityScore >= 60)
                                Needs Improvement
                            @else
                                Poor
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- Progress Bar -->
                <div class="mt-4">
                    <div class="bg-white dark:bg-zinc-700 rounded-full h-3 overflow-hidden">
                        <div class="h-full transition-all duration-500 {{ $qualityScore >= 80 ? 'bg-emerald-500' : ($qualityScore >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" 
                             style="width: {{ $qualityScore }}%"></div>
                    </div>
                </div>

                <div class="flex items-center justify-between mt-4">
                    <flux:button wire:click="scanDataQuality" variant="outline" size="sm" :disabled="$qualityScanning">
                        @if($qualityScanning)
                            <flux:icon name="arrow-path" class="w-4 h-4 mr-2 animate-spin" />
                            Scanning...
                        @else
                            <flux:icon name="arrow-path" class="w-4 h-4 mr-2" />
                            Refresh Scan
                        @endif
                    </flux:button>
                    @if($selectedVariantsCount > 0)
                        <div class="text-sm text-blue-700 dark:text-blue-300">
                            {{ $selectedVariantsCount }} variants selected for focused analysis
                        </div>
                    @endif
                </div>
            </div>

            @if(!empty($qualityResults))
                <!-- Quality Metrics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Marketplace Coverage -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <flux:icon name="globe-alt" class="w-5 h-5 text-purple-600" />
                                <flux:heading size="md">Marketplace Coverage</flux:heading>
                            </div>
                            @php
                                $coverage = ($qualityResults['total_variants'] - $qualityResults['missing_marketplace_variants']) / max($qualityResults['total_variants'], 1) * 100;
                            @endphp
                            <span class="text-lg font-semibold {{ $coverage >= 80 ? 'text-emerald-600' : ($coverage >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                {{ round($coverage, 1) }}%
                            </span>
                        </div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $qualityResults['missing_marketplace_variants'] }} variants without marketplace listings
                        </div>
                        <div class="mt-3 bg-zinc-100 dark:bg-zinc-700 rounded-full h-2">
                            <div class="h-full rounded-full {{ $coverage >= 80 ? 'bg-emerald-500' : ($coverage >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" 
                                 style="width: {{ $coverage }}%"></div>
                        </div>
                    </div>

                    <!-- Product Attributes -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <flux:icon name="tag" class="w-5 h-5 text-blue-600" />
                                <flux:heading size="md">Product Attributes</flux:heading>
                            </div>
                            @php
                                $prodAttr = ($qualityResults['total_products'] - $qualityResults['products_without_attributes']) / max($qualityResults['total_products'], 1) * 100;
                            @endphp
                            <span class="text-lg font-semibold {{ $prodAttr >= 80 ? 'text-emerald-600' : ($prodAttr >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                {{ round($prodAttr, 1) }}%
                            </span>
                        </div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $qualityResults['products_without_attributes'] }} products without attributes
                        </div>
                        <div class="mt-3 bg-zinc-100 dark:bg-zinc-700 rounded-full h-2">
                            <div class="h-full rounded-full {{ $prodAttr >= 80 ? 'bg-emerald-500' : ($prodAttr >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" 
                                 style="width: {{ $prodAttr }}%"></div>
                        </div>
                    </div>

                    <!-- Barcode Coverage -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <flux:icon name="qr-code" class="w-5 h-5 text-emerald-600" />
                                <flux:heading size="md">Barcode Coverage</flux:heading>
                            </div>
                            @php
                                $barcodes = ($qualityResults['total_variants'] - $qualityResults['variants_without_barcodes']) / max($qualityResults['total_variants'], 1) * 100;
                            @endphp
                            <span class="text-lg font-semibold {{ $barcodes >= 80 ? 'text-emerald-600' : ($barcodes >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                {{ round($barcodes, 1) }}%
                            </span>
                        </div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $qualityResults['variants_without_barcodes'] }} variants without barcodes
                        </div>
                        <div class="mt-3 bg-zinc-100 dark:bg-zinc-700 rounded-full h-2">
                            <div class="h-full rounded-full {{ $barcodes >= 80 ? 'bg-emerald-500' : ($barcodes >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" 
                                 style="width: {{ $barcodes }}%"></div>
                        </div>
                    </div>

                    <!-- Pricing Coverage -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <flux:icon name="currency-dollar" class="w-5 h-5 text-green-600" />
                                <flux:heading size="md">Pricing Coverage</flux:heading>
                            </div>
                            @php
                                $pricing = ($qualityResults['total_variants'] - $qualityResults['variants_without_pricing']) / max($qualityResults['total_variants'], 1) * 100;
                            @endphp
                            <span class="text-lg font-semibold {{ $pricing >= 80 ? 'text-emerald-600' : ($pricing >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                {{ round($pricing, 1) }}%
                            </span>
                        </div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $qualityResults['variants_without_pricing'] }} variants without pricing
                        </div>
                        <div class="mt-3 bg-zinc-100 dark:bg-zinc-700 rounded-full h-2">
                            <div class="h-full rounded-full {{ $pricing >= 80 ? 'bg-emerald-500' : ($pricing >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" 
                                 style="width: {{ $pricing }}%"></div>
                        </div>
                    </div>

                    <!-- Color/Size Completion -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <flux:icon name="swatch" class="w-5 h-5 text-pink-600" />
                                <flux:heading size="md">Color & Size Data</flux:heading>
                            </div>
                            @php
                                $colorSize = (($qualityResults['total_variants'] - $qualityResults['variants_without_color']) + ($qualityResults['total_variants'] - $qualityResults['variants_without_size'])) / (max($qualityResults['total_variants'], 1) * 2) * 100;
                            @endphp
                            <span class="text-lg font-semibold {{ $colorSize >= 80 ? 'text-emerald-600' : ($colorSize >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                {{ round($colorSize, 1) }}%
                            </span>
                        </div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400 space-y-1">
                            <div>{{ $qualityResults['variants_without_color'] }} missing colors</div>
                            <div>{{ $qualityResults['variants_without_size'] }} missing sizes</div>
                        </div>
                        <div class="mt-3 bg-zinc-100 dark:bg-zinc-700 rounded-full h-2">
                            <div class="h-full rounded-full {{ $colorSize >= 80 ? 'bg-emerald-500' : ($colorSize >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" 
                                 style="width: {{ $colorSize }}%"></div>
                        </div>
                    </div>

                    <!-- ASIN Coverage -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <flux:icon name="identification" class="w-5 h-5 text-orange-600" />
                                <flux:heading size="md">ASIN Coverage</flux:heading>
                            </div>
                            @php
                                $asin = ($qualityResults['total_variants'] - $qualityResults['variants_without_asin']) / max($qualityResults['total_variants'], 1) * 100;
                            @endphp
                            <span class="text-lg font-semibold {{ $asin >= 80 ? 'text-emerald-600' : ($asin >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                {{ round($asin, 1) }}%
                            </span>
                        </div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $qualityResults['variants_without_asin'] }} variants without ASINs
                        </div>
                        <div class="mt-3 bg-zinc-100 dark:bg-zinc-700 rounded-full h-2">
                            <div class="h-full rounded-full {{ $asin >= 80 ? 'bg-emerald-500' : ($asin >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" 
                                 style="width: {{ $asin }}%"></div>
                        </div>
                    </div>
                </div>

                <!-- Issues Summary -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-6">Data Quality Issues</flux:heading>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Critical Issues -->
                        <div>
                            <div class="flex items-center gap-2 mb-4">
                                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600" />
                                <flux:heading size="md" class="text-red-700 dark:text-red-300">Critical Issues</flux:heading>
                            </div>
                            <div class="space-y-3">
                                @if($qualityResults['products_without_names'] > 0)
                                    <div class="flex items-center justify-between p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                        <span class="text-sm text-red-700 dark:text-red-300">Products without names</span>
                                        <span class="font-semibold text-red-600">{{ $qualityResults['products_without_names'] }}</span>
                                    </div>
                                @endif
                                @if($qualityResults['products_without_skus'] > 0)
                                    <div class="flex items-center justify-between p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                        <span class="text-sm text-red-700 dark:text-red-300">Products without SKUs</span>
                                        <span class="font-semibold text-red-600">{{ $qualityResults['products_without_skus'] }}</span>
                                    </div>
                                @endif
                                @if($qualityResults['duplicate_asins'] > 0)
                                    <div class="flex items-center justify-between p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                        <span class="text-sm text-red-700 dark:text-red-300">Duplicate ASINs</span>
                                        <span class="font-semibold text-red-600">{{ $qualityResults['duplicate_asins'] }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Warning Issues -->
                        <div>
                            <div class="flex items-center gap-2 mb-4">
                                <flux:icon name="exclamation-circle" class="w-5 h-5 text-amber-600" />
                                <flux:heading size="md" class="text-amber-700 dark:text-amber-300">Warnings</flux:heading>
                            </div>
                            <div class="space-y-3">
                                @if($qualityResults['incomplete_titles'] > 0)
                                    <div class="flex items-center justify-between p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                                        <span class="text-sm text-amber-700 dark:text-amber-300">Incomplete titles</span>
                                        <span class="font-semibold text-amber-600">{{ $qualityResults['incomplete_titles'] }}</span>
                                    </div>
                                @endif
                                @if($qualityResults['variants_without_attributes'] > 0)
                                    <div class="flex items-center justify-between p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                                        <span class="text-sm text-amber-700 dark:text-amber-300">Variants without attributes</span>
                                        <span class="font-semibold text-amber-600">{{ $qualityResults['variants_without_attributes'] }}</span>
                                    </div>
                                @endif
                                @if($qualityResults['variants_without_color'] > 0)
                                    <div class="flex items-center justify-between p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                                        <span class="text-sm text-amber-700 dark:text-amber-300">Variants without colors</span>
                                        <span class="font-semibold text-amber-600">{{ $qualityResults['variants_without_color'] }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:heading size="md" class="mb-4">Quick Actions</flux:heading>
                        <div class="flex flex-wrap gap-3">
                            <flux:button wire:navigate href="{{ route('operations.bulk.overview') }}" variant="outline" size="sm">
                                <flux:icon name="chart-bar" class="w-4 h-4 mr-2" />
                                Select & Fix Issues
                            </flux:button>
                            <flux:button wire:navigate href="{{ route('operations.bulk.attributes') }}" variant="outline" size="sm">
                                <flux:icon name="tag" class="w-4 h-4 mr-2" />
                                Add Missing Attributes
                            </flux:button>
                            <flux:button wire:navigate href="{{ route('operations.bulk.templates') }}" variant="outline" size="sm">
                                <flux:icon name="layout-grid" class="w-4 h-4 mr-2" />
                                Generate Titles
                            </flux:button>
                            <flux:button wire:navigate href="{{ route('barcodes.pool.index') }}" variant="outline" size="sm">
                                <flux:icon name="qr-code" class="w-4 h-4 mr-2" />
                                Assign Barcodes
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-route-tabs>
</div>