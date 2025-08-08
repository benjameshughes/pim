<x-layouts.app.sidebar>
    <div class="p-6 space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                New Import System Test
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
                Testing the new Builder Pattern + Background Jobs architecture
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg border border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                Upload Test File
            </h2>

            <form action="#" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                
                <div>
                    <flux:field>
                        <flux:label>Select Excel or CSV File</flux:label>
                        <flux:input type="file" name="file" accept=".xlsx,.csv" required />
                        <flux:description>Maximum file size: 100MB</flux:description>
                    </flux:field>
                </div>

                <div>
                    <flux:field>
                        <flux:label>Import Mode</flux:label>
                        <flux:select name="import_mode">
                            <flux:option value="create_or_update">Create or Update (Recommended)</flux:option>
                            <flux:option value="create_only">Create Only</flux:option>
                            <flux:option value="update_existing">Update Existing Only</flux:option>
                        </flux:select>
                    </flux:field>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:checkbox name="extract_attributes" checked>
                            Smart Attribute Extraction
                        </flux:checkbox>
                        <flux:description>Extract colors, sizes, dimensions automatically</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:checkbox name="detect_mtm" checked>
                            Detect Made-to-Measure
                        </flux:checkbox>
                        <flux:description>Find MTM/bespoke products in titles</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:checkbox name="group_by_sku" checked>
                            Group by SKU Pattern
                        </flux:checkbox>
                        <flux:description>Use SKU patterns over name matching</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:checkbox name="background_processing" checked>
                            Background Processing
                        </flux:checkbox>
                        <flux:description>Process in background (no timeouts)</flux:description>
                    </flux:field>
                </div>

                <flux:button type="submit" variant="primary" class="w-full">
                    <flux:icon.rocket-launch class="size-4" />
                    Start New Import System
                </flux:button>
            </form>
        </div>

        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
            <h3 class="font-medium text-blue-900 dark:text-blue-200 mb-2">
                🚀 New System Features
            </h3>
            <ul class="text-sm text-blue-800 dark:text-blue-300 space-y-1">
                <li>• Background processing - no more timeouts!</li>
                <li>• Real-time progress updates with WebSockets</li>
                <li>• Smart MTM detection with confidence scoring</li>
                <li>• SKU-based parent-child grouping</li>
                <li>• Digits-only dimension extraction</li>
                <li>• Comprehensive error handling & retry</li>
                <li>• Beautiful progress UI with ETA</li>
            </ul>
        </div>
    </div>
</x-layouts.app.sidebar>