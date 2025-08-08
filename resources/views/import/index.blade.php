<x-layouts.app>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Import Dashboard') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8" 
         x-data="{
            statistics: @js($statistics),
            recentImports: @js($recentImports->toArray()),
            connected: false,
            channel: null,
            currentTime: new Date(),
            
            init() {
                this.setupWebSocketConnection();
                setInterval(() => { this.currentTime = new Date() }, 1000);
            },

            setupWebSocketConnection() {
                if (!window.Echo) {
                    console.warn('Laravel Echo not available for dashboard updates');
                    return;
                }

                try {
                    this.channel = window.Echo.channel('dashboard-updates');
                    
                    this.channel.listen('ImportSessionUpdated', (data) => {
                        console.log('Dashboard received import update:', data);
                        this.updateImportInList(data);
                        this.refreshStatistics();
                    });

                    this.channel.listen('ImportSessionCreated', (data) => {
                        console.log('Dashboard received new import:', data);
                        this.addImportToList(data);
                        this.refreshStatistics();
                    });

                    this.channel.subscribed(() => {
                        this.connected = true;
                        console.log('Connected to dashboard updates channel');
                    });

                    this.channel.error((error) => {
                        this.connected = false;
                        console.error('Dashboard WebSocket connection error:', error);
                    });

                } catch (error) {
                    console.error('Failed to setup dashboard WebSocket connection:', error);
                    this.connected = false;
                }
            },

            updateImportInList(updatedImport) {
                const index = this.recentImports.findIndex(imp => imp.session_id === updatedImport.session_id);
                if (index !== -1) {
                    this.recentImports[index] = { ...this.recentImports[index], ...updatedImport };
                }
            },

            addImportToList(newImport) {
                this.recentImports.unshift(newImport);
                if (this.recentImports.length > 10) {
                    this.recentImports = this.recentImports.slice(0, 10);
                }
            },

            refreshStatistics() {
                this.statistics.total_imports = this.recentImports.length;
                this.statistics.successful_imports = this.recentImports.filter(imp => imp.status === 'completed').length;
                this.statistics.failed_imports = this.recentImports.filter(imp => imp.status === 'failed').length;
                this.statistics.processing_imports = this.recentImports.filter(imp => 
                    ['processing', 'dry_run', 'analyzing_file'].includes(imp.status)
                ).length;
            },

            formatTimeAgo(dateString) {
                if (!dateString) return '-';
                
                const date = new Date(dateString);
                const now = this.currentTime;
                const diffMs = now - date;
                const diffSeconds = Math.floor(diffMs / 1000);
                const diffMinutes = Math.floor(diffSeconds / 60);
                const diffHours = Math.floor(diffMinutes / 60);
                const diffDays = Math.floor(diffHours / 24);

                if (diffSeconds < 60) {
                    return diffSeconds <= 1 ? 'just now' : `${diffSeconds}s ago`;
                } else if (diffMinutes < 60) {
                    return `${diffMinutes}m ago`;
                } else if (diffHours < 24) {
                    return `${diffHours}h ago`;
                } else if (diffDays < 30) {
                    return `${diffDays}d ago`;
                } else {
                    return date.toLocaleDateString();
                }
            }
         }">
        <!-- Real-time Connection Status -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center text-xs text-gray-500">
                <div class="flex items-center">
                    <div 
                        class="w-2 h-2 rounded-full mr-2"
                        :class="connected ? 'bg-green-500 animate-pulse' : 'bg-red-500'"
                    ></div>
                    <span x-text="connected ? 'Live updates active' : 'Live updates disconnected'"></span>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="text-blue-600 font-bold text-sm">📊</span>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Imports</p>
                        <p class="text-2xl font-semibold text-gray-900" x-text="statistics.total_imports">{{ $statistics['total_imports'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="h-8 w-8 bg-green-100 rounded-full flex items-center justify-center">
                            <span class="text-green-600 font-bold text-sm">✓</span>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Successful</p>
                        <p class="text-2xl font-semibold text-gray-900" x-text="statistics.successful_imports">{{ $statistics['successful_imports'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="h-8 w-8 bg-red-100 rounded-full flex items-center justify-center">
                            <span class="text-red-600 font-bold text-sm">✗</span>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Failed</p>
                        <p class="text-2xl font-semibold text-gray-900" x-text="statistics.failed_imports">{{ $statistics['failed_imports'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="h-8 w-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <span class="text-yellow-600 font-bold text-sm">🕐</span>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Processing</p>
                        <p class="text-2xl font-semibold text-gray-900" x-text="statistics.processing_imports">{{ $statistics['processing_imports'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-lg font-semibold text-gray-900">Recent Imports</h2>
            <a href="{{ route('import.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                + New Import
            </a>
        </div>

        <!-- Recent Imports Table -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <template x-if="recentImports.length > 0">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    File
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Progress
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Rows
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Created
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="importItem in recentImports" :key="importItem.id">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900" x-text="importItem.original_filename"></div>
                                        <div class="text-sm text-gray-500" x-text="Math.round(importItem.file_size / 1024 * 10) / 10 + ' KB'"></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                              :class="{
                                                  'bg-green-100 text-green-800': importItem.status === 'completed',
                                                  'bg-red-100 text-red-800': importItem.status === 'failed',
                                                  'bg-blue-100 text-blue-800': ['processing', 'dry_run', 'analyzing_file'].includes(importItem.status),
                                                  'bg-gray-100 text-gray-800': !['completed', 'failed', 'processing', 'dry_run', 'analyzing_file'].includes(importItem.status)
                                              }">
                                            <span x-text="importItem.status.charAt(0).toUpperCase() + importItem.status.slice(1).replace('_', ' ')"></span>
                                            <span x-show="importItem.status === 'processing'" class="ml-1 animate-pulse">•</span>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <template x-if="['processing', 'dry_run', 'analyzing_file'].includes(importItem.status)">
                                            <div class="flex items-center">
                                                <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                                                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                                         :style="'width: ' + (importItem.progress_percentage || 0) + '%'"></div>
                                                </div>
                                                <span class="text-sm text-gray-500" x-text="(importItem.progress_percentage || 0) + '%'"></span>
                                            </div>
                                        </template>
                                        <template x-if="!['processing', 'dry_run', 'analyzing_file'].includes(importItem.status)">
                                            <span class="text-sm text-gray-500">-</span>
                                        </template>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <template x-if="importItem.total_rows">
                                            <span x-text="(importItem.processed_rows || 0).toLocaleString() + ' / ' + importItem.total_rows.toLocaleString()"></span>
                                        </template>
                                        <template x-if="!importItem.total_rows">
                                            <span>-</span>
                                        </template>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatTimeAgo(importItem.created_at)"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a :href="'/import/' + importItem.session_id"
                                               class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                                View
                                            </a>
                                            
                                            <template x-if="importItem.status === 'completed'">
                                                <a :href="'/import/' + importItem.session_id + '/download'"
                                                   class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                                    Download
                                                </a>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
            
            <template x-if="recentImports.length === 0">
                <div class="text-center py-12">
                    <div class="mx-auto h-12 w-12 bg-gray-100 rounded-full flex items-center justify-center text-gray-400">
                        <span class="text-2xl">📄</span>
                    </div>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No imports yet</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating your first import.</p>
                    <div class="mt-6">
                        <a href="{{ route('import.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            + New Import
                        </a>
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-layouts.app>