<!-- Attributes Tab Content -->

<!-- Product Attributes -->
@if($product->attributes->isNotEmpty())
    <div class="space-y-6">
        <flux:heading size="lg">Product Attributes</flux:heading>
        <div class="space-y-4">
            @foreach($product->attributes as $attribute)
                <div class="border-b border-zinc-100 dark:border-zinc-800 pb-4">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $attribute->attributeDefinition->label }}
                        </div>
                        @if($attribute->attributeDefinition->description)
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $attribute->attributeDefinition->description }}
                            </span>
                        @endif
                    </div>
                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $attribute->value }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@else
    <div class="text-center py-12">
        <flux:icon name="tag" class="w-16 h-16 text-zinc-400 mx-auto mb-4" />
        <flux:heading size="lg" class="text-zinc-600 dark:text-zinc-400 mb-2">No Attributes</flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-500 mb-4">
            Add product attributes to provide more detailed information about your products
        </flux:subheading>
        <flux:button variant="primary" :href="route('products.product.edit', $product)" wire:navigate>
            <flux:icon name="plus" class="w-4 h-4 mr-2" />
            Add Attributes
        </flux:button>
    </div>
@endif