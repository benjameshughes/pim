<div class="space-y-2">
    <label for="{{ $field->getName() }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
        {{ $field->getLabel() }}
        @if($field->isRequired())
            <span class="text-red-500">*</span>
        @endif
    </label>
    
    <input {!! $attributes !!} />
    
    @if($field->getDescription())
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $field->getDescription() }}</p>
    @endif
</div>