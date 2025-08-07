<flux:main>
    <flux:heading size="xl">Toast Notification System Demo</flux:heading>
    <flux:subheading>
        A comprehensive demonstration of the FilamentPHP-style toast notification system
    </flux:subheading>

    <div class="space-y-8">
        <!-- Basic Toast Types Section -->
        <div class="space-y-4">
            <flux:heading size="lg">Basic Toast Types</flux:heading>
            <flux:subheading class="text-gray-600 dark:text-gray-400">
                Test the different toast notification types with their default styling.
            </flux:subheading>

            <div class="flex flex-wrap gap-3">
                <flux:button variant="primary" wire:click="showSuccessToast">
                    <flux:icon name="circle-check" class="mr-2 h-4 w-4" />
                    Success Toast
                </flux:button>

                <flux:button variant="danger" wire:click="showErrorToast">
                    <flux:icon name="circle-x" class="mr-2 h-4 w-4" />
                    Error Toast
                </flux:button>

                <flux:button variant="ghost" wire:click="showWarningToast">
                    <flux:icon name="triangle-alert" class="mr-2 h-4 w-4" />
                    Warning Toast
                </flux:button>

                <flux:button variant="ghost" wire:click="showInfoToast">
                    <flux:icon name="info" class="mr-2 h-4 w-4" />
                    Info Toast
                </flux:button>
            </div>
        </div>

        <!-- Advanced Features Section -->
        <div class="space-y-4">
            <flux:heading size="lg">Advanced Features</flux:heading>
            <flux:subheading class="text-gray-600 dark:text-gray-400">
                Demonstrate advanced toast features like actions and positioning.
            </flux:subheading>

            <div class="flex flex-wrap gap-3">
                <flux:button variant="outline" wire:click="showActionToast">
                    <flux:icon name="settings" class="mr-2 h-4 w-4" />
                    Toast with Actions
                </flux:button>

                <flux:button variant="outline" wire:click="showPositionDemo">
                    <flux:icon name="layout-grid" class="mr-2 h-4 w-4" />
                    Position Demo
                </flux:button>

                <flux:button variant="outline" wire:click="showHelperFunctionDemo">
                    <flux:icon name="file-text" class="mr-2 h-4 w-4" />
                    Helper Functions
                </flux:button>

                <flux:button variant="outline" wire:click="showManagementDemo">
                    <flux:icon name="layers" class="mr-2 h-4 w-4" />
                    Batch Toasts
                </flux:button>
            </div>
        </div>

        <!-- Custom Toast Builder Section -->
        <div class="space-y-4">
            <flux:heading size="lg">Custom Toast Builder</flux:heading>
            <flux:subheading class="text-gray-600 dark:text-gray-400">
                Build and test custom toast notifications with your own parameters.
            </flux:subheading>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <flux:field>
                        <flux:label>Title</flux:label>
                        <flux:input wire:model="customTitle" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Body Message</flux:label>
                        <flux:textarea wire:model="customBody" rows="3" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Toast Type</flux:label>
                        <flux:select wire:model="selectedType">
                            <flux:select.option value="success">Success</flux:select.option>
                            <flux:select.option value="error">Error</flux:select.option>
                            <flux:select.option value="warning">Warning</flux:select.option>
                            <flux:select.option value="info">Info</flux:select.option>
                        </flux:select>
                    </flux:field>
                </div>

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>Position</flux:label>
                        <flux:select wire:model="selectedPosition">
                            <flux:select.option value="top-left">Top Left</flux:select.option>
                            <flux:select.option value="top-center">Top Center</flux:select.option>
                            <flux:select.option value="top-right">Top Right</flux:select.option>
                            <flux:select.option value="bottom-left">Bottom Left</flux:select.option>
                            <flux:select.option value="bottom-center">Bottom Center</flux:select.option>
                            <flux:select.option value="bottom-right">Bottom Right</flux:select.option>
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>Duration (milliseconds)</flux:label>
                        <flux:input type="number" wire:model="customDuration" min="1000" max="30000" step="500" />
                    </flux:field>

                    <div class="flex items-center space-x-4">
                        <flux:checkbox wire:model="closable" id="closable">
                            Closable
                        </flux:checkbox>

                        <flux:checkbox wire:model="persistent" id="persistent">
                            Persistent
                        </flux:checkbox>
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <flux:button variant="primary" wire:click="showCustomToast">
                    <flux:icon name="zap" class="mr-2 h-4 w-4" />
                    Show Custom Toast
                </flux:button>

                <flux:button variant="danger" wire:click="clearAllToasts">
                    <flux:icon name="trash-2" class="mr-2 h-4 w-4" />
                    Clear All Toasts
                </flux:button>
            </div>
        </div>

        <!-- Code Examples Section -->
        <div class="space-y-4">
            <flux:heading size="lg">Code Examples</flux:heading>
            <flux:subheading class="text-gray-600 dark:text-gray-400">
                Example code snippets for implementing toast notifications in your application.
            </flux:subheading>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Basic Usage -->
                <x-card>
                    <x-card.header>
                        <flux:heading size="md">Basic Usage</flux:heading>
                    </x-card.header>
                    
                    <div class="bg-zinc-50 dark:bg-zinc-800 p-4 rounded-lg font-mono text-sm">
                        <pre><code>// Using Facade
Toast::success('Success!', 'Operation completed.')
    ->duration(5000)
    ->send();

// Using Helper Functions
toast_success('Success!', 'Operation completed.')
    ->send();

// In Controllers/Actions
toast('Info', 'Welcome to our app!')->send();</code></pre>
                    </div>
                </x-card>

                <!-- Advanced Usage -->
                <x-card>
                    <x-card.header>
                        <flux:heading size="md">Advanced Features</flux:heading>
                    </x-card.header>
                    
                    <div class="bg-zinc-50 dark:bg-zinc-800 p-4 rounded-lg font-mono text-sm">
                        <pre><code>// With Actions
Toast::info('Confirm Action', 'Do you want to proceed?')
    ->persistent()
    ->action(
        ToastAction::make('Yes')
            ->url('/confirm')
            ->icon('check')
    )
    ->action(
        ToastAction::make('Cancel')
            ->shouldCloseToast()
    )
    ->send();</code></pre>
                    </div>
                </x-card>

                <!-- Livewire Integration -->
                <x-card>
                    <x-card.header>
                        <flux:heading size="md">Livewire Integration</flux:heading>
                    </x-card.header>
                    
                    <div class="bg-zinc-50 dark:bg-zinc-800 p-4 rounded-lg font-mono text-sm">
                        <pre><code>// In Livewire Components
public function save()
{
    $this->validate();
    
    // Save logic...
    
    Toast::success('Saved!', 'Your changes have been saved.')
        ->position('top-right')
        ->send();
}</code></pre>
                    </div>
                </x-card>

                <!-- Position & Styling -->
                <x-card>
                    <x-card.header>
                        <flux:heading size="md">Positioning & Styling</flux:heading>
                    </x-card.header>
                    
                    <div class="bg-zinc-50 dark:bg-zinc-800 p-4 rounded-lg font-mono text-sm">
                        <pre><code>// Custom positioning and styling
Toast::warning('Warning', 'Check your input.')
    ->position('bottom-center')
    ->icon('exclamation-triangle')
    ->duration(8000)
    ->closable(false)
    ->class(['custom-warning-toast'])
    ->send();</code></pre>
                    </div>
                </x-card>
            </div>
        </div>

        <!-- Configuration Info -->
        <div class="space-y-4">
            <flux:heading size="lg">Configuration</flux:heading>
            <flux:subheading class="text-gray-600 dark:text-gray-400">
                The toast system is fully configurable through the <code>config/toasts.php</code> file.
            </flux:subheading>

            <x-card>
                <div class="prose dark:prose-invert max-w-none">
                    <p>Key configuration options include:</p>
                    <ul>
                        <li><strong>Default Settings:</strong> Duration, position, type, and closable defaults</li>
                        <li><strong>Positions:</strong> Six available positions with custom CSS classes</li>
                        <li><strong>Toast Types:</strong> Success, error, warning, and info with Flux UI styling</li>
                        <li><strong>Animations:</strong> Configurable enter/exit animations using Alpine.js</li>
                        <li><strong>Limits:</strong> Maximum number of simultaneous toasts</li>
                    </ul>
                    <p>Publish the configuration file using:</p>
                    <code>php artisan vendor:publish --tag=toasts-config</code>
                </div>
            </x-card>
        </div>
    </div>
</flux:main>