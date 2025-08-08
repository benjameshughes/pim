{{-- Phase 6.1 Component Demo --}}
<div class="space-y-8">
    {{-- 1. Page Template Example --}}
    <x-page-template 
        title="Phase 6.1 Component Demo"
        :breadcrumbs="[
            ['name' => 'Components', 'url' => '#'],
            ['name' => 'Phase 6.1 Demo']
        ]"
        :actions="[
            [
                'type' => 'button',
                'label' => 'Refresh Data',
                'variant' => 'outline',
                'icon' => 'refresh-cw',
                'wire:click' => 'refreshData',
                'loading' => 'refreshData'
            ],
            [
                'type' => 'link',
                'label' => 'View Documentation',
                'variant' => 'primary',
                'icon' => 'book-open',
                'href' => '/docs/components',
                'target' => '_blank'
            ]
        ]"
    >
        <x-slot name="description">
            Demonstrating all Phase 6.1 core view infrastructure components: Master Page Template, Data Table, Form Layout, and Stats Cards.
        </x-slot>
        
        <x-slot name="icon">
            <flux:icon name="sparkles" class="h-6 w-6 text-white" />
        </x-slot>
        
        <x-slot name="stats">
            {{-- 2. Stats Grid Example --}}
            <x-stats-grid :stats="[
                [
                    'title' => 'Total Components',
                    'value' => 4,
                    'icon' => 'cube',
                    'iconColor' => 'indigo',
                    'trend' => '+100',
                    'trendDirection' => 'up',
                    'trendText' => 'from last phase'
                ],
                [
                    'title' => 'Code Coverage',
                    'value' => 95.5,
                    'suffix' => '%',
                    'icon' => 'chart-bar',
                    'iconColor' => 'green',
                    'trend' => '+15.2',
                    'trendDirection' => 'up',
                    'trendText' => 'vs baseline'
                ],
                [
                    'title' => 'Performance Score',
                    'value' => 98,
                    'suffix' => '/100',
                    'icon' => 'zap',
                    'iconColor' => 'yellow',
                    'trend' => '+8',
                    'trendDirection' => 'up',
                    'trendText' => 'Lighthouse'
                ],
                [
                    'title' => 'Accessibility',
                    'value' => 'WCAG AA',
                    'icon' => 'shield-check',
                    'iconColor' => 'purple',
                    'trendText' => 'Compliant'
                ]
            ]" />
        </x-slot>
        
        {{-- 3. Data Table Example --}}
        <div class="space-y-6">
            <flux:heading size="lg">Data Table Component</flux:heading>
            
            <x-data-table
                :data="collect([
                    (object)['id' => 1, 'name' => 'Page Template', 'type' => 'Layout', 'status' => 'completed', 'complexity' => 'Medium', 'created_at' => now()->subDays(3)],
                    (object)['id' => 2, 'name' => 'Data Table', 'type' => 'Data Display', 'status' => 'completed', 'complexity' => 'High', 'created_at' => now()->subDays(2)],
                    (object)['id' => 3, 'name' => 'Form Layout', 'type' => 'Form', 'status' => 'completed', 'complexity' => 'Medium', 'created_at' => now()->subDays(1)],
                    (object)['id' => 4, 'name' => 'Stats Card', 'type' => 'Data Display', 'status' => 'completed', 'complexity' => 'Low', 'created_at' => now()],
                ])"
                :columns="[
                    [
                        'key' => 'name',
                        'label' => 'Component Name',
                        'sortable' => true,
                        'type' => 'text'
                    ],
                    [
                        'key' => 'type',
                        'label' => 'Type',
                        'type' => 'badge',
                        'badges' => [
                            'Layout' => ['variant' => 'indigo', 'label' => 'Layout'],
                            'Data Display' => ['variant' => 'green', 'label' => 'Data Display'],
                            'Form' => ['variant' => 'blue', 'label' => 'Form']
                        ]
                    ],
                    [
                        'key' => 'status',
                        'label' => 'Status',
                        'type' => 'badge',
                        'badges' => [
                            'completed' => ['variant' => 'green', 'label' => 'Completed'],
                            'pending' => ['variant' => 'yellow', 'label' => 'Pending'],
                            'in_progress' => ['variant' => 'blue', 'label' => 'In Progress']
                        ]
                    ],
                    [
                        'key' => 'complexity',
                        'label' => 'Complexity',
                        'type' => 'badge',
                        'badges' => [
                            'Low' => ['variant' => 'green', 'label' => 'Low'],
                            'Medium' => ['variant' => 'yellow', 'label' => 'Medium'],
                            'High' => ['variant' => 'red', 'label' => 'High']
                        ]
                    ],
                    [
                        'key' => 'created_at',
                        'label' => 'Created',
                        'type' => 'date',
                        'dateFormat' => 'M j, Y'
                    ]
                ]"
                :actions="[
                    [
                        'type' => 'link',
                        'icon' => 'eye',
                        'label' => 'View',
                        'href' => fn($item) => '#view-' . $item->id,
                        'visible' => true
                    ],
                    [
                        'type' => 'button',
                        'icon' => 'pencil',
                        'label' => 'Edit',
                        'action' => 'editComponent',
                        'visible' => true
                    ]
                ]"
                :bulkActions="[
                    [
                        'key' => 'export',
                        'label' => 'Export Selected',
                        'icon' => 'download',
                        'variant' => 'outline'
                    ],
                    [
                        'key' => 'delete',
                        'label' => 'Delete Selected',
                        'icon' => 'trash-2',
                        'variant' => 'outline',
                        'danger' => true
                    ]
                ]"
                :headerActions="[
                    [
                        'type' => 'button',
                        'label' => 'Add Component',
                        'variant' => 'primary',
                        'icon' => 'plus',
                        'action' => 'addComponent'
                    ]
                ]"
                :filters="[
                    [
                        'key' => 'type',
                        'label' => 'Type',
                        'type' => 'select',
                        'options' => [
                            'Layout' => 'Layout',
                            'Data Display' => 'Data Display',
                            'Form' => 'Form'
                        ]
                    ],
                    [
                        'key' => 'status',
                        'label' => 'Status',
                        'type' => 'select',
                        'options' => [
                            'completed' => 'Completed',
                            'pending' => 'Pending',
                            'in_progress' => 'In Progress'
                        ]
                    ]
                ]"
                searchable="true"
                selectable="true"
                striped="true"
            />
        </div>
        
        {{-- 4. Form Layout Example --}}
        <div class="space-y-6">
            <flux:heading size="lg">Form Layout Component</flux:heading>
            
            <x-form-layout
                title="Component Configuration"
                description="Configure your component settings using our responsive form layout."
                columns="2"
                :steps="[
                    ['label' => 'Basic'],
                    ['label' => 'Styling'],
                    ['label' => 'Advanced'],
                    ['label' => 'Review']
                ]"
                :currentStep="2"
                :progress="50"
                wire:submit="saveConfiguration"
                submitText="Save Configuration"
                cancelButton="true"
                cancelHref="#"
                validationSummary="true"
            >
                {{-- Form Fields --}}
                <flux:field>
                    <flux:label>Component Name</flux:label>
                    <flux:input wire:model="form.name" placeholder="Enter component name" />
                    <flux:error for="form.name" />
                </flux:field>
                
                <flux:field>
                    <flux:label>Component Type</flux:label>
                    <flux:select wire:model="form.type">
                        <flux:select.option value="">Choose type...</flux:select.option>
                        <flux:select.option value="layout">Layout Component</flux:select.option>
                        <flux:select.option value="data">Data Display</flux:select.option>
                        <flux:select.option value="form">Form Component</flux:select.option>
                    </flux:select>
                    <flux:error for="form.type" />
                </flux:field>
                
                <flux:field class="lg:col-span-2">
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="form.description" rows="3" placeholder="Describe the component..." />
                    <flux:description>Provide a detailed description of the component's purpose and functionality.</flux:description>
                    <flux:error for="form.description" />
                </flux:field>
                
                <flux:field>
                    <flux:label>Complexity Level</flux:label>
                    <flux:select wire:model="form.complexity">
                        <flux:select.option value="">Select complexity...</flux:select.option>
                        <flux:select.option value="low">Low</flux:select.option>
                        <flux:select.option value="medium">Medium</flux:select.option>
                        <flux:select.option value="high">High</flux:select.option>
                    </flux:select>
                    <flux:error for="form.complexity" />
                </flux:field>
                
                <flux:field>
                    <div class="flex items-center gap-3">
                        <flux:checkbox wire:model="form.responsive" />
                        <flux:label>Responsive Design</flux:label>
                    </div>
                    <flux:description>Enable responsive breakpoints for mobile devices.</flux:description>
                </flux:field>
                
                <x-slot name="actions">
                    <flux:button type="button" variant="outline">Save as Draft</flux:button>
                    <flux:button type="button" variant="outline">Preview</flux:button>
                </x-slot>
            </x-form-layout>
        </div>
        
        {{-- Individual Stats Cards Examples --}}
        <div class="space-y-6">
            <flux:heading size="lg">Stats Card Variants</flux:heading>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {{-- Default Card --}}
                <x-stats-card
                    title="Default Style"
                    value="1,234"
                    icon="cube"
                    iconColor="indigo"
                    trend="+12.3"
                    trendDirection="up"
                    trendText="vs last month"
                />
                
                {{-- Colored Card --}}
                <x-stats-card
                    title="Colored Variant"
                    value="98.7"
                    suffix="%"
                    icon="chart-bar"
                    iconColor="green"
                    variant="colored"
                    color="green"
                    trend="+5.4"
                    trendDirection="up"
                    trendText="improvement"
                />
                
                {{-- Minimal Card --}}
                <x-stats-card
                    title="Minimal Style"
                    value="42"
                    prefix="£"
                    suffix="K"
                    icon="pound-sterling"
                    iconColor="blue"
                    variant="minimal"
                    trend="-2.1"
                    trendDirection="down"
                    trendText="this week"
                />
                
                {{-- Large Card --}}
                <x-stats-card
                    title="Large Size"
                    value="999"
                    suffix="+"
                    icon="users"
                    iconColor="purple"
                    size="lg"
                    trend="0"
                    trendDirection="neutral"
                    trendText="no change"
                />
                
                {{-- Small Card --}}
                <x-stats-card
                    title="Small Size"
                    value="24"
                    icon="clock"
                    iconColor="yellow"
                    size="sm"
                    trendText="hours saved"
                />
                
                {{-- Clickable Card --}}
                <x-stats-card
                    title="Clickable Card"
                    value="156"
                    icon="external-link"
                    iconColor="pink"
                    href="#external-link"
                    trend="+23"
                    trendDirection="up"
                    trendText="click to view details"
                />
            </div>
        </div>
        
        <x-slot name="contentFooter">
            <div class="flex justify-between items-center">
                <span class="text-sm text-zinc-600 dark:text-zinc-400">
                    Phase 6.1 Implementation completed successfully
                </span>
                <div class="flex gap-2">
                    <flux:badge variant="green">All components ready</flux:badge>
                    <flux:badge variant="blue">Performance optimized</flux:badge>
                    <flux:badge variant="purple">Accessibility compliant</flux:badge>
                </div>
            </div>
        </x-slot>
    </x-page-template>
</div>