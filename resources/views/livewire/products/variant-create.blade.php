<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $title }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Create a new product variant using the enhanced VariantBuilder pattern
            </p>
            @if($product)
                <div class="mt-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                        Parent: {{ $product->name }} ({{ $product->parent_sku }})
                    </span>
                </div>
            @endif
        </div>
        <div class="mt-4 sm:ml-4 sm:mt-0">
            <button wire:click="cancel"
                    type="button"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                Cancel
            </button>
        </div>
    </div>

    {{-- Form --}}
    <form wire:submit="save" class="space-y-6">
        {{-- Basic Variant Information --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Basic Information</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- SKU --}}
                <div class="md:col-span-2">
                    <label for="sku" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Variant SKU <span class="text-red-500">*</span>
                    </label>
                    <input wire:model="sku"
                           type="text"
                           id="sku"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('sku') border-red-500 @enderror"
                           placeholder="e.g., 001-001">
                    @error('sku')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Status --}}
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <select wire:model="status"
                            id="status"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('status') border-red-500 @enderror">
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Stock Level --}}
                <div>
                    <label for="stockLevel" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Stock Level <span class="text-red-500">*</span>
                    </label>
                    <input wire:model="stockLevel"
                           type="number"
                           id="stockLevel"
                           min="0"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('stockLevel') border-red-500 @enderror"
                           placeholder="0">
                    @error('stockLevel')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Variant Attributes --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Variant Attributes</h3>
            
            <div class="space-y-6">
                {{-- Color Selection --}}
                <div>
                    <label for="color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Color
                    </label>
                    <input wire:model="color"
                           type="text"
                           id="color"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('color') border-red-500 @enderror"
                           placeholder="Enter color or select from options below">
                    
                    {{-- Quick Color Selection --}}
                    <div class="mt-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Quick Select:</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($commonColors as $colorOption)
                                <button wire:click="selectColor('{{ $colorOption }}')"
                                        type="button"
                                        class="px-3 py-1 text-xs rounded-full border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $color === $colorOption ? 'bg-blue-100 border-blue-300 text-blue-800 dark:bg-blue-900 dark:text-blue-300' : 'text-gray-700 dark:text-gray-300' }}">
                                    {{ $colorOption }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    
                    @error('color')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Window Shade Dimensions --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Width --}}
                    <div>
                        <label for="width" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Width
                        </label>
                        <input wire:model="width"
                               type="text"
                               id="width"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('width') border-red-500 @enderror"
                               placeholder="e.g., 120cm">
                        
                        {{-- Quick Width Selection --}}
                        <div class="mt-2">
                            <div class="flex flex-wrap gap-1">
                                @foreach($commonWidths as $widthOption)
                                    <button wire:click="selectWidth('{{ $widthOption }}')"
                                            type="button"
                                            class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $width === $widthOption ? 'bg-blue-100 border-blue-300 text-blue-800 dark:bg-blue-900 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400' }}">
                                        {{ $widthOption }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        
                        @error('width')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Drop --}}
                    <div>
                        <label for="drop" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Drop
                        </label>
                        <input wire:model="drop"
                               type="text"
                               id="drop"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('drop') border-red-500 @enderror"
                               placeholder="e.g., 160cm">
                        
                        {{-- Quick Drop Selection --}}
                        <div class="mt-2">
                            <div class="flex flex-wrap gap-1">
                                @foreach($commonDrops as $dropOption)
                                    <button wire:click="selectDrop('{{ $dropOption }}')"
                                            type="button"
                                            class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $drop === $dropOption ? 'bg-blue-100 border-blue-300 text-blue-800 dark:bg-blue-900 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400' }}">
                                        {{ $dropOption }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        
                        @error('drop')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Pricing --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Pricing</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Retail Price --}}
                <div>
                    <label for="retailPrice" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Retail Price (£)
                    </label>
                    <input wire:model="retailPrice"
                           type="number"
                           id="retailPrice"
                           step="0.01"
                           min="0"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('retailPrice') border-red-500 @enderror"
                           placeholder="0.00">
                    @error('retailPrice')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Cost Price --}}
                <div>
                    <label for="costPrice" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Cost Price (£)
                    </label>
                    <input wire:model="costPrice"
                           type="number"
                           id="costPrice"
                           step="0.01"
                           min="0"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('costPrice') border-red-500 @enderror"
                           placeholder="0.00">
                    @error('costPrice')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Barcode Assignment --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                Barcode Assignment
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                    ({{ $availableBarcodesCount }} available {{ $barcode['type'] }} codes)
                </span>
            </h3>
            
            <div class="space-y-4">
                {{-- Enable Barcode Assignment --}}
                <div class="flex items-center">
                    <input wire:model="barcode.assign"
                           type="checkbox"
                           id="assignBarcode"
                           class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:focus:ring-offset-gray-800">
                    <label for="assignBarcode" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                        Assign barcode to this variant
                    </label>
                </div>

                @if($barcode['assign'])
                    <div class="space-y-4 pl-6 border-l-2 border-blue-200 dark:border-blue-700">
                        {{-- Barcode Type --}}
                        <div>
                            <label for="barcodeType" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Barcode Type
                            </label>
                            <select wire:model="barcode.type"
                                    wire:change="updatedBarcodeType($event.target.value)"
                                    id="barcodeType"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                @foreach($barcodeTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Custom Barcode --}}
                        <div>
                            <label for="customBarcode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Custom Barcode (optional)
                            </label>
                            <input wire:model="barcode.custom"
                                   type="text"
                                   id="customBarcode"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Leave empty to auto-assign from pool">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                If empty, a barcode will be automatically assigned from the {{ $barcode['type'] }} pool
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Package Dimensions --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Package Dimensions</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                {{-- Length --}}
                <div>
                    <label for="packageLength" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Length (cm)
                    </label>
                    <input wire:model="package.length"
                           type="number"
                           id="packageLength"
                           step="0.01"
                           min="0"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('package.length') border-red-500 @enderror"
                           placeholder="0.00">
                    @error('package.length')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Width --}}
                <div>
                    <label for="packageWidth" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Width (cm)
                    </label>
                    <input wire:model="package.width"
                           type="number"
                           id="packageWidth"
                           step="0.01"
                           min="0"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('package.width') border-red-500 @enderror"
                           placeholder="0.00">
                    @error('package.width')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Height --}}
                <div>
                    <label for="packageHeight" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Height (cm)
                    </label>
                    <input wire:model="package.height"
                           type="number"
                           id="packageHeight"
                           step="0.01"
                           min="0"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('package.height') border-red-500 @enderror"
                           placeholder="0.00">
                    @error('package.height')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Weight --}}
                <div>
                    <label for="packageWeight" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Weight (kg)
                    </label>
                    <input wire:model="package.weight"
                           type="number"
                           id="packageWeight"
                           step="0.01"
                           min="0"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('package.weight') border-red-500 @enderror"
                           placeholder="0.00">
                    @error('package.weight')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Form Actions --}}
        <div class="flex items-center justify-end space-x-3 pt-6">
            <button wire:click="cancel"
                    type="button"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                Cancel
            </button>
            
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 disabled:opacity-50">
                <span wire:loading.remove wire:target="save">Create Variant</span>
                <span wire:loading wire:target="save" class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Creating...
                </span>
            </button>
        </div>
    </form>

    {{-- Loading Overlay --}}
    <div wire:loading.flex wire:target="save" 
         class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-sm mx-auto">
            <div class="flex items-center space-x-3">
                <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-gray-900 dark:text-white">Creating variant with Builder pattern...</span>
            </div>
        </div>
    </div>
</div>