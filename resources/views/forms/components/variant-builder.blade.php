<div class="space-y-6" x-data="{
    colors: [],
    widths: [],
    drops: [],
    newColor: '',
    newWidth: '',
    newDrop: '',
    variants: [],
    
    get variantCount() {
        return this.variants.length;
    },
    
    init() {
        console.log('variantBuilder initialized inline');
        console.log('initial data:', { colors: this.colors, widths: this.widths, drops: this.drops });
        this.generateVariants();
    },
    
    addColor() {
        console.log('addColor called, newColor:', this.newColor);
        const color = this.newColor.trim();
        console.log('trimmed color:', color);
        console.log('current colors:', this.colors);
        if (color && !this.colors.includes(color)) {
            this.colors.push(color);
            this.newColor = '';
            this.generateVariants();
            console.log('color added, new colors:', this.colors);
        } else {
            console.log('color not added - empty or duplicate');
        }
    },
    
    addWidth() {
        const width = parseInt(this.newWidth);
        if (width && !this.widths.includes(width)) {
            this.widths.push(width);
            this.widths.sort((a, b) => a - b);
            this.newWidth = '';
            this.generateVariants();
        }
    },
    
    addDrop() {
        const drop = parseInt(this.newDrop);
        if (drop && !this.drops.includes(drop)) {
            this.drops.push(drop);
            this.drops.sort((a, b) => a - b);
            this.newDrop = '';
            this.generateVariants();
        }
    },
    
    removeColor(color) {
        this.colors = this.colors.filter(c => c !== color);
        this.generateVariants();
    },
    
    removeWidth(width) {
        this.widths = this.widths.filter(w => w !== width);
        this.generateVariants();
    },
    
    removeDrop(drop) {
        this.drops = this.drops.filter(d => d !== drop);
        this.generateVariants();
    },
    
    generateVariants() {
        if (this.colors.length === 0 || this.widths.length === 0 || this.drops.length === 0) {
            this.variants = [];
            return;
        }
        
        let variants = [];
        let variantNumber = 1;
        
        this.colors.forEach(color => {
            this.widths.forEach(width => {
                this.drops.forEach(drop => {
                    const sku = `PROD-${String(variantNumber).padStart(3, '0')}`;
                    variants.push({
                        sku: sku,
                        title: `${color} ${width}cm × ${drop}cm`,
                        color: color,
                        width: width,
                        drop: drop
                    });
                    variantNumber++;
                });
            });
        });
        
        this.variants = variants;
    },
    
    loadPreset(preset) {
        switch(preset) {
            case 'basic':
                this.colors = ['White', 'Cream', 'Grey'];
                this.widths = [120, 140, 160];
                this.drops = [160, 180, 200];
                break;
            case 'premium':
                this.colors = ['White', 'Cream', 'Grey', 'Black', 'Blue'];
                this.widths = [120, 140, 160, 180, 200];
                this.drops = [160, 180, 200, 220];
                break;
            case 'curtains':
                this.colors = ['White', 'Ivory', 'Grey', 'Navy', 'Green'];
                this.widths = [140, 160, 180, 220, 240];
                this.drops = [180, 200, 220, 240, 260];
                break;
        }
        this.generateVariants();
    },
    
    clearAll() {
        this.colors = [];
        this.widths = [];
        this.drops = [];
        this.variants = [];
        this.newColor = '';
        this.newWidth = '';
        this.newDrop = '';
    }
}"
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Generate Variants</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">Create all possible combinations of colors, widths, and drops</p>
        </div>
        <div class="text-sm text-gray-500">
            <span x-text="variantCount"></span> variants will be created
        </div>
    </div>

    {{-- Preset Buttons --}}
    <div class="flex flex-wrap gap-2">
        <button 
            type="button"
            x-on:click="loadPreset('basic')"
            class="px-3 py-2 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 transition-colors text-sm"
        >
            🏠 Basic Blinds
        </button>
        <button 
            type="button"
            x-on:click="loadPreset('premium')"
            class="px-3 py-2 bg-purple-100 text-purple-700 rounded-md hover:bg-purple-200 transition-colors text-sm"
        >
            ⭐ Premium Blinds
        </button>
        <button 
            type="button"
            x-on:click="loadPreset('curtains')"
            class="px-3 py-2 bg-green-100 text-green-700 rounded-md hover:bg-green-200 transition-colors text-sm"
        >
            🌊 Curtains
        </button>
        <button 
            type="button"
            x-on:click="clearAll()"
            class="px-3 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-sm"
        >
            🗑️ Clear All
        </button>
    </div>

    {{-- SKU Settings --}}
    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 space-y-3">
        <h4 class="font-medium text-gray-900 dark:text-white">SKU Generation</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Parent SKU
                </label>
                <input 
                    type="text"
                    wire:model.live="formData.parent_sku"
                    placeholder="PROD"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    SKU Pattern
                </label>
                <select 
                    wire:model.live="formData.enable_sku_grouping"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="1">Sequential (PROD-001, PROD-002)</option>
                    <option value="0">Descriptive (PROD-Red-120x180)</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Colors Section --}}
    <div class="space-y-3">
        <h4 class="font-medium text-gray-900 dark:text-white">Colors</h4>
        <div class="flex items-center space-x-2">
            <input 
                type="text" 
                x-model="newColor"
                placeholder="Add color (e.g. Red)"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                x-on:keydown.enter="addColor()"
            >
            <button 
                type="button"
                x-on:click="addColor()"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
                Add
            </button>
        </div>
        <div class="flex flex-wrap gap-2">
            <template x-for="color in colors" :key="color">
                <span class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                    <span x-text="color"></span>
                    <button x-on:click="removeColor(color)" class="ml-2 text-blue-600 hover:text-blue-800">×</button>
                </span>
            </template>
        </div>
    </div>

    {{-- Widths Section --}}
    <div class="space-y-3">
        <h4 class="font-medium text-gray-900 dark:text-white">Widths (cm)</h4>
        <div class="flex items-center space-x-2">
            <input 
                type="number" 
                x-model="newWidth"
                placeholder="120"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                x-on:keydown.enter="addWidth()"
            >
            <button 
                type="button"
                x-on:click="addWidth()"
                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
            >
                Add
            </button>
        </div>
        <div class="flex flex-wrap gap-2">
            <template x-for="width in widths" :key="width">
                <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">
                    <span x-text="width + 'cm'"></span>
                    <button x-on:click="removeWidth(width)" class="ml-2 text-green-600 hover:text-green-800">×</button>
                </span>
            </template>
        </div>
    </div>

    {{-- Drops Section --}}
    <div class="space-y-3">
        <h4 class="font-medium text-gray-900 dark:text-white">Drops (cm)</h4>
        <div class="flex items-center space-x-2">
            <input 
                type="number" 
                x-model="newDrop"
                placeholder="180"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                x-on:keydown.enter="addDrop()"
            >
            <button 
                type="button"
                x-on:click="addDrop()"
                class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors"
            >
                Add
            </button>
        </div>
        <div class="flex flex-wrap gap-2">
            <template x-for="drop in drops" :key="drop">
                <span class="inline-flex items-center px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm">
                    <span x-text="drop + 'cm'"></span>
                    <button x-on:click="removeDrop(drop)" class="ml-2 text-purple-600 hover:text-purple-800">×</button>
                </span>
            </template>
        </div>
    </div>

    {{-- Generated Variants Preview --}}
    <div class="space-y-3">
        <h4 class="font-medium text-gray-900 dark:text-white">Generated Variants</h4>
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 max-h-64 overflow-y-auto">
            <div x-show="variants.length === 0">
                <p class="text-gray-500 text-center">Add colors, widths, and drops to generate variants</p>
            </div>
            <div x-show="variants.length > 0" class="space-y-2">
                <template x-for="variant in variants.slice(0, 10)" :key="variant.sku">
                    <div class="flex items-center justify-between p-2 bg-white rounded border text-sm">
                        <span class="font-medium" x-text="variant.sku"></span>
                        <span x-text="variant.title"></span>
                    </div>
                </template>
                <div x-show="variants.length > 10" class="text-center text-gray-500 text-sm mt-2">
                    <span>... and </span><span x-text="variants.length - 10"></span><span> more variants</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Hidden inputs for Livewire --}}
    <input type="hidden" wire:model="formData.colors" x-bind:value="colors.join(',')">
    <input type="hidden" wire:model="formData.widths" x-bind:value="widths.join(',')">
    <input type="hidden" wire:model="formData.drops" x-bind:value="drops.join(',')">
</div>

