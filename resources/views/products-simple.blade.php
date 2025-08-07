@extends('components.layouts.app')

@section('content')
<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Products</h1>
            <p class="text-gray-600 dark:text-gray-400">Simple blade route using @stackedList directive</p>
        </div>

        {{-- This is the FilamentPHP approach - just a directive that uses ProductIndex under the hood --}}
        @stackedList('products')
    </div>
</div>
@endsection