<div>
    <x-breadcrumb :items="[
        ['name' => 'Operations'],
        ['name' => 'Bulk Operations'],
        ['name' => 'AI Assistant']
    ]" />

    <!-- Header -->
    <div class="mb-8">
        <flux:heading size="xl">Bulk Operations - AI Assistant</flux:heading>
        <flux:subheading>Get intelligent assistance for optimizing your product data and operations</flux:subheading>
    </div>

    <!-- Tab Navigation -->
    <x-route-tabs :tabs="$tabs" class="mb-6">
        <div class="p-6">
            <!-- AI Chat Interface -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <!-- Chat Area -->
                <div class="lg:col-span-3">
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 h-96 flex flex-col">
                        <!-- Chat Header -->
                        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center">
                                    <flux:icon name="zap" class="w-4 h-4 text-white" />
                                </div>
                                <div>
                                    <flux:heading size="md">AI Assistant</flux:heading>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        @if($selectedVariantsCount > 0)
                                            Analyzing {{ $selectedVariantsCount }} selected variants
                                        @else
                                            Ready to help with your product operations
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @if(!empty($conversationHistory))
                                <flux:button wire:click="clearConversation" variant="ghost" size="sm">
                                    <flux:icon name="trash" class="w-4 h-4 mr-2" />
                                    Clear
                                </flux:button>
                            @endif
                        </div>

                        <!-- Chat Messages -->
                        <div class="flex-1 overflow-y-auto p-4 space-y-4">
                            @if(empty($conversationHistory))
                                <!-- Welcome Message -->
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                                        <flux:icon name="zap" class="w-4 h-4 text-white" />
                                    </div>
                                    <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-4 max-w-md">
                                        <div class="text-sm text-zinc-700 dark:text-zinc-300">
                                            👋 Hi! I'm your AI assistant for product operations. I can help you with:
                                            <br><br>
                                            • Generating optimized product titles<br>
                                            • Analyzing data quality issues<br>
                                            • Suggesting product attributes<br>
                                            • Marketplace optimization tips<br>
                                            • Pricing strategy recommendations<br>
                                            <br>
                                            What would you like help with today?
                                        </div>
                                    </div>
                                </div>
                            @else
                                @foreach($conversationHistory as $message)
                                    @if($message['type'] === 'user')
                                        <!-- User Message -->
                                        <div class="flex items-start gap-3 justify-end">
                                            <div class="bg-blue-500 text-white rounded-lg p-4 max-w-md">
                                                <div class="text-sm">{{ $message['message'] }}</div>
                                            </div>
                                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                                                <flux:icon name="user" class="w-4 h-4 text-white" />
                                            </div>
                                        </div>
                                    @else
                                        <!-- AI Message -->
                                        <div class="flex items-start gap-3">
                                            <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                                                <flux:icon name="zap" class="w-4 h-4 text-white" />
                                            </div>
                                            <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-4 max-w-2xl">
                                                <div class="text-sm text-zinc-700 dark:text-zinc-300 prose prose-sm dark:prose-invert">
                                                    {!! nl2br(e($message['message'])) !!}
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            @endif

                            <!-- Processing indicator -->
                            @if($aiProcessing)
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                                        <flux:icon name="zap" class="w-4 h-4 text-white" />
                                    </div>
                                    <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-4">
                                        <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                                            <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                                            AI is thinking...
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Chat Input -->
                        <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                            <form wire:submit.prevent="processAIRequest" class="flex gap-2">
                                <flux:input 
                                    wire:model="aiPrompt"
                                    placeholder="Ask me anything about your products..."
                                    class="flex-1"
                                    :disabled="$aiProcessing"
                                />
                                <flux:button 
                                    type="submit" 
                                    variant="primary"
                                    :disabled="$aiProcessing || !$aiPrompt"
                                >
                                    @if($aiProcessing)
                                        <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                                    @else
                                        <flux:icon name="paper-airplane" class="w-4 h-4" />
                                    @endif
                                </flux:button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Sidebar -->
                <div class="space-y-6">
                    <!-- Context Info -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800 p-4">
                        <flux:heading size="md" class="mb-3 text-blue-900 dark:text-blue-100">Context</flux:heading>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-blue-700 dark:text-blue-300">Selected Variants:</span>
                                <span class="font-medium text-blue-900 dark:text-blue-100">{{ $selectedVariantsCount }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-blue-700 dark:text-blue-300">Marketplaces:</span>
                                <span class="font-medium text-blue-900 dark:text-blue-100">{{ count($selectedMarketplaces) }}</span>
                            </div>
                        </div>
                        @if($selectedVariantsCount === 0)
                            <div class="mt-3 pt-3 border-t border-blue-200 dark:border-blue-700">
                                <flux:button 
                                    wire:navigate 
                                    href="{{ route('operations.bulk.overview') }}" 
                                    variant="outline" 
                                    size="sm"
                                    class="w-full border-blue-300 text-blue-700 hover:bg-blue-100"
                                >
                                    Select Variants
                                </flux:button>
                            </div>
                        @endif
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                        <flux:heading size="md" class="mb-4">Quick Actions</flux:heading>
                        <div class="space-y-3">
                            <flux:button 
                                wire:click="generateAITitles"
                                variant="outline"
                                size="sm"
                                class="w-full justify-start"
                                :disabled="$aiProcessing"
                            >
                                <flux:icon name="sparkles" class="w-4 h-4 mr-2" />
                                Generate AI Titles
                            </flux:button>
                            
                            <flux:button 
                                wire:click="analyzeDataQuality"
                                variant="outline"
                                size="sm"
                                class="w-full justify-start"
                                :disabled="$aiProcessing"
                            >
                                <flux:icon name="magnifying-glass" class="w-4 h-4 mr-2" />
                                Analyze Data Quality
                            </flux:button>
                        </div>
                    </div>

                    <!-- Marketplace Settings -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                        <flux:heading size="md" class="mb-4">Marketplace Focus</flux:heading>
                        <div class="space-y-2">
                            @foreach($marketplaces as $marketplace)
                                <label class="flex items-center gap-2">
                                    <flux:checkbox 
                                        wire:model="selectedMarketplaces" 
                                        value="{{ $marketplace->id }}"
                                    />
                                    <span class="text-sm">{{ $marketplace->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Sample Prompts -->
                    <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg border border-zinc-200 dark:border-zinc-600 p-4">
                        <flux:heading size="md" class="mb-4">Try These Prompts</flux:heading>
                        <div class="space-y-2">
                            @php
                                $samplePrompts = [
                                    "Generate SEO-optimized titles for my curtains",
                                    "What attributes am I missing?",
                                    "How can I improve my Amazon listings?",
                                    "Analyze my pricing strategy",
                                    "What are the best keywords for my products?"
                                ];
                            @endphp
                            @foreach($samplePrompts as $prompt)
                                <button 
                                    wire:click="$set('aiPrompt', '{{ $prompt }}')"
                                    class="text-left text-xs text-zinc-600 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200 block w-full p-2 rounded hover:bg-zinc-100 dark:hover:bg-zinc-600 transition-colors"
                                >
                                    "{{ $prompt }}"
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-route-tabs>
</div>