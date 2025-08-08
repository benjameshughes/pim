<x-layouts.app>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Column Mapping') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Map Import Columns</h3>
                    <p class="text-sm text-gray-600 mb-6">
                        Map the columns from your file to the appropriate database fields.
                    </p>

                    <form method="POST" action="{{ route('import.save-mapping', $session->session_id) }}">
                        @csrf

                        <div class="space-y-4">
                            @foreach($availableFields as $field => $description)
                                <div class="flex items-center justify-between py-3 border-b">
                                    <div>
                                        <label class="text-sm font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $field)) }}</label>
                                        <p class="text-xs text-gray-500">{{ $description }}</p>
                                    </div>
                                    <select name="column_mapping[{{ $field }}]" class="mt-1 block w-48 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                        <option value="">-- Not Mapped --</option>
                                        @foreach($session->detected_columns ?? [] as $column)
                                            <option value="{{ $column }}">{{ $column }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex justify-between items-center pt-6">
                            <a href="{{ route('import.show', $session->session_id) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Back
                            </a>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Save Mapping
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>