<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigation System Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] { display: none; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar with Navigation -->
        <div class="w-64 bg-white shadow-lg">
            <x-navigation.main :groups="$resourceNavigation" />
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 p-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-6">Navigation System Test</h1>
            
            <!-- Breadcrumbs -->
            <x-navigation.breadcrumbs :breadcrumbs="$navigationBreadcrumbs" />
            
            <!-- Test Content -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Navigation Features</h2>
                
                <div class="space-y-4">
                    <div>
                        <h3 class="font-medium text-gray-900">✅ Auto-Discovery</h3>
                        <p class="text-gray-600">Navigation items automatically discovered from resources</p>
                    </div>
                    
                    <div>
                        <h3 class="font-medium text-gray-900">✅ Fluent API</h3>
                        <p class="text-gray-600">NavigationBuilder with fluent methods for configuration</p>
                    </div>
                    
                    <div>
                        <h3 class="font-medium text-gray-900">✅ Grouping</h3>
                        <p class="text-gray-600">Navigation items grouped by logical sections</p>
                    </div>
                    
                    <div>
                        <h3 class="font-medium text-gray-900">✅ Nested Navigation</h3>
                        <p class="text-gray-600">Support for sub-navigation and relationship routes</p>
                    </div>
                    
                    <div>
                        <h3 class="font-medium text-gray-900">✅ Wire:Navigate</h3>
                        <p class="text-gray-600">All navigation links use wire:navigate for SPA behavior</p>
                    </div>
                    
                    <div>
                        <h3 class="font-medium text-gray-900">✅ Breadcrumbs</h3>
                        <p class="text-gray-600">Dynamic breadcrumb generation</p>
                    </div>
                </div>
            </div>
            
            <!-- Navigation Statistics -->
            <div class="mt-8 bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Navigation Statistics</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ $resourceNavigation->count() }}</div>
                        <div class="text-gray-600">Navigation Groups</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">
                            {{ $resourceNavigation->sum(fn($group) => $group->getItemCount()) }}
                        </div>
                        <div class="text-gray-600">Total Items</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-600">{{ count($navigationBreadcrumbs) }}</div>
                        <div class="text-gray-600">Breadcrumb Levels</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-2xl font-bold text-orange-600">{{ $resourceStats['registered_count'] ?? 0 }}</div>
                        <div class="text-gray-600">Resources</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>