<div class="space-y-2">
    <label for="{{ $field->getName() }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
        {{ $field->getLabel() }}
        @if($field->isRequired())
            <span class="text-red-500">*</span>
        @endif
    </label>
    
    <select 
        name="{{ $field->getName() }}" 
        id="{{ $field->getName() }}"
        wire:model.live="formData.{{ $field->getName() }}"
        @if($multiple) multiple @endif
        @if($field->isRequired()) required @endif
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
    >
        @if(!$multiple && $field->getPlaceholder())
            <option value="">{{ $field->getPlaceholder() }}</option>
        @endif
        
        @foreach($options as $value => $label)
            <option 
                value="{{ $value }}" 
                @if($field->getValue() == $value) selected @endif
            >
                {{ $label }}
            </option>
        @endforeach
    </select>
    
    @if($field->getDescription())
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $field->getDescription() }}</p>
    @endif
</div>