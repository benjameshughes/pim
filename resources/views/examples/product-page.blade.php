{{-- 
Example: Drop-in Atom Framework Usage in Your Own Blade Views
This shows how to use the Atom framework without it taking over your layout.
--}}

@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">My Products</h1>
        <p class="text-gray-600">Manage your product catalog using Atom framework components.</p>
    </div>

    {{-- Your custom content before the table --}}
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <h2 class="text-lg font-semibold text-blue-800">Custom Content</h2>
        <p class="text-blue-700">This is your own content in your own layout. The Atom framework table will appear below.</p>
    </div>

    {{-- Method 1: Use the dedicated resource table component --}}
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="p-6">
            <h3 class="text-xl font-semibold mb-4">Products Table</h3>
            @livewire('resource-table', ['resource' => \App\Atom\Resources\ProductResource::class])
        </div>
    </div>

    {{-- Method 2: Embed in an existing Livewire component --}}
    {{-- If you have your own Livewire component, you can add the table property like this: --}}
    {{-- @livewire('your-custom-component') --}}

    {{-- Your custom content after the table --}}
    <div class="mt-8 bg-green-50 border border-green-200 rounded-lg p-4">
        <h2 class="text-lg font-semibold text-green-800">More Custom Content</h2>
        <p class="text-green-700">Your layout remains completely in your control!</p>
    </div>
</div>
@endsection