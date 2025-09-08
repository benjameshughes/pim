@props(['channel'])

<div class="flex-shrink-0">
    @switch($channel)
        @case('shopify')
            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                <flux:icon name="shopping-bag" class="w-5 h-5 text-green-600" />
            </div>
            @break
        @case('ebay')
            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                <flux:icon name="tag" class="w-5 h-5 text-blue-600" />
            </div>
            @break
        @case('amazon')
            <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                <flux:icon name="truck" class="w-5 h-5 text-orange-600" />
            </div>
            @break
        @default
            <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
                <flux:icon name="link" class="w-5 h-5 text-gray-600" />
            </div>
    @endswitch
</div>
