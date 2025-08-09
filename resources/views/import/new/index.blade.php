<x-layouts.app>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Import Dashboard') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8" id="import-dashboard">
        <!-- Real-time Connection Status -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center text-xs text-gray-500">
                <div class="flex items-center">
                    <div id="connection-indicator" class="w-2 h-2 rounded-full mr-2 bg-red-500"></div>
                    <span id="connection-status">Live updates disconnected</span>
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
                        <p class="text-2xl font-semibold text-gray-900" id="total-imports">{{ $statistics['total_imports'] ?? 0 }}</p>
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
                        <p class="text-2xl font-semibold text-gray-900" id="successful-imports">{{ $statistics['successful_imports'] ?? 0 }}</p>
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
                        <p class="text-2xl font-semibold text-gray-900" id="failed-imports">{{ $statistics['failed_imports'] ?? 0 }}</p>
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
                        <p class="text-2xl font-semibold text-gray-900" id="processing-imports">{{ $statistics['processing_imports'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center space-x-4">
                <h2 class="text-lg font-semibold text-gray-900">Recent Imports</h2>
                <button id="bulk-delete-btn" style="display: none;" 
                        class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded text-sm">
                    Delete Selected (<span id="selected-count">0</span>)
                </button>
            </div>
            <a href="{{ route('import.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                + New Import
            </a>
        </div>

        <!-- Recent Imports Table -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div id="imports-table" style="display: none;">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" id="select-all" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                </th>
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
                        <tbody id="imports-tbody" class="bg-white divide-y divide-gray-200">
                            <!-- Table rows will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div id="no-imports" class="text-center py-12">
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
        </div>
    </div>

    <script>
        class ImportDashboard {
            constructor() {
                this.recentImports = @json($recentImports);
                this.statistics = @json($statistics);
                this.selectedImports = [];
                this.selectAll = false;
                this.connected = false;
                this.currentTime = new Date();
                this.channels = new Map();

                // Initialize the dashboard
                this.init();
            }

            init() {
                console.log('Initializing Import Dashboard');
                this.updateDisplay();
                this.setupEventListeners();
                this.setupWebSocket();
                this.startTimeUpdater();
                this.setupNewImportDetection();
            }

            setupEventListeners() {
                // Select all checkbox
                document.getElementById('select-all')?.addEventListener('change', (e) => {
                    this.toggleSelectAll(e.target.checked);
                });

                // Bulk delete button
                document.getElementById('bulk-delete-btn')?.addEventListener('click', () => {
                    this.bulkDeleteImports();
                });
            }

            setupWebSocket() {
                console.log('Setting up WebSocket connections');
                console.log('Echo object:', window.Echo);
                console.log('Recent imports:', this.recentImports);
                
                // Global connection status
                window.Echo.connector.pusher.connection.bind('connected', () => {
                    console.log('✅ WebSocket connected');
                    this.updateConnectionStatus(true);
                });

                window.Echo.connector.pusher.connection.bind('disconnected', () => {
                    console.log('❌ WebSocket disconnected');
                    this.updateConnectionStatus(false);
                });

                // Subscribe to import progress channels for active imports
                this.recentImports.forEach(importItem => {
                    console.log('Checking import:', importItem.session_id, 'status:', importItem.status);
                    if (['processing', 'dry_run', 'analyzing_file'].includes(importItem.status)) {
                        console.log('Subscribing to active import:', importItem.session_id);
                        this.subscribeToImport(importItem.session_id);
                    }
                });

                // Also test subscribing to ALL imports to see if we get any events
                console.log('Testing: subscribing to all imports for debugging...');
                this.recentImports.forEach(importItem => {
                    console.log('Force subscribing to:', importItem.session_id);
                    this.subscribeToImport(importItem.session_id);
                });

                console.log('WebSocket setup complete');
            }

            setupNewImportDetection() {
                // Poll for new imports every 3 seconds and auto-subscribe
                setInterval(() => {
                    // Reload the current page data to check for new imports
                    fetch(window.location.href, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.recentImports) {
                            data.recentImports.forEach(importItem => {
                                // Check if this is a new import we haven't subscribed to
                                if (!this.channels.has(importItem.session_id) && 
                                    ['processing', 'dry_run', 'analyzing_file'].includes(importItem.status)) {
                                    console.log('🆕 New active import detected:', importItem.session_id);
                                    this.subscribeToImport(importItem.session_id);
                                    
                                    // Add to our local list if not already there
                                    const exists = this.recentImports.find(imp => imp.session_id === importItem.session_id);
                                    if (!exists) {
                                        this.recentImports.unshift(importItem);
                                        if (this.recentImports.length > 10) {
                                            this.recentImports = this.recentImports.slice(0, 10);
                                        }
                                        this.renderTable();
                                    }
                                }
                            });
                        }
                    })
                    .catch(error => console.error('Failed to check for new imports:', error));
                }, 3000); // Check every 3 seconds

                console.log('New import detection started (polling every 3s)');
            }

            subscribeToImport(sessionId) {
                if (this.channels.has(sessionId)) {
                    console.log(`Already subscribed to import.${sessionId}`);
                    return;
                }

                console.log(`📡 Subscribing to import.${sessionId}`);
                
                const channel = window.Echo.channel(`import.${sessionId}`);
                this.channels.set(sessionId, channel);

                // Listen for ImportSessionUpdated events - Laravel Echo format
                channel.listen('ImportSessionUpdated', (e) => {
                    console.log('📨 Received ImportSessionUpdated event:', e);
                    console.log('📨 Event properties:', Object.keys(e));
                    
                    // Laravel Echo provides event data directly in the callback parameter
                    console.log('📨 Session ID from event:', e.session_id);
                    this.handleImportUpdate(e);
                });

                // Listen for connection events
                channel.subscribed(() => {
                    console.log(`✅ Successfully subscribed to import.${sessionId}`);
                });

                channel.error((error) => {
                    console.log(`❌ Subscription error for import.${sessionId}:`, error);
                });
            }

            unsubscribeFromImport(sessionId) {
                if (this.channels.has(sessionId)) {
                    console.log(`📡 Unsubscribing from import.${sessionId}`);
                    this.channels.get(sessionId).stopListening();
                    window.Echo.leaveChannel(`import.${sessionId}`);
                    this.channels.delete(sessionId);
                }
            }

            handleImportUpdate(data) {
                console.log('🔄 Handling import update:', data);
                console.log('Current imports:', this.recentImports.length);
                
                // Find and update the import in our local array
                const importIndex = this.recentImports.findIndex(imp => imp.session_id === data.session_id);
                console.log('Found import at index:', importIndex);
                
                if (importIndex !== -1) {
                    // Update existing import
                    const existingImport = this.recentImports[importIndex];
                    console.log('Before update:', existingImport.progress_percentage, existingImport.processed_rows);
                    
                    this.recentImports[importIndex] = {
                        ...existingImport,
                        status: data.status || existingImport.status,
                        progress_percentage: data.progress_percentage || existingImport.progress_percentage,
                        processed_rows: data.processed_rows || existingImport.processed_rows,
                        current_operation: data.current_operation || existingImport.current_operation
                    };
                    
                    console.log('After update:', this.recentImports[importIndex].progress_percentage, this.recentImports[importIndex].processed_rows);

                    // If import completed or failed, unsubscribe from its channel
                    if (['completed', 'failed', 'cancelled'].includes(data.status)) {
                        this.unsubscribeFromImport(data.session_id);
                    }
                } else {
                    console.log('Import not found in local list, might be new');
                    // Could fetch fresh data or add new import here
                }

                console.log('Calling refreshStatistics and renderTable...');
                // Update the display
                this.refreshStatistics();
                this.renderTable();
                console.log('✅ UI update complete');
            }

            updateConnectionStatus(connected) {
                this.connected = connected;
                const indicator = document.getElementById('connection-indicator');
                const status = document.getElementById('connection-status');

                if (connected) {
                    indicator.className = 'w-2 h-2 rounded-full mr-2 bg-green-500 animate-pulse';
                    status.textContent = 'Live updates active';
                } else {
                    indicator.className = 'w-2 h-2 rounded-full mr-2 bg-red-500';
                    status.textContent = 'Live updates disconnected';
                }
            }

            updateDisplay() {
                this.refreshStatistics();
                this.renderTable();
            }

            refreshStatistics() {
                this.statistics.total_imports = this.recentImports.length;
                this.statistics.successful_imports = this.recentImports.filter(imp => imp.status === 'completed').length;
                this.statistics.failed_imports = this.recentImports.filter(imp => imp.status === 'failed').length;
                this.statistics.processing_imports = this.recentImports.filter(imp => 
                    ['processing', 'dry_run', 'analyzing_file'].includes(imp.status)
                ).length;

                // Update DOM
                document.getElementById('total-imports').textContent = this.statistics.total_imports;
                document.getElementById('successful-imports').textContent = this.statistics.successful_imports;
                document.getElementById('failed-imports').textContent = this.statistics.failed_imports;
                document.getElementById('processing-imports').textContent = this.statistics.processing_imports;
            }

            renderTable() {
                const tableContainer = document.getElementById('imports-table');
                const noImports = document.getElementById('no-imports');
                const tbody = document.getElementById('imports-tbody');

                if (this.recentImports.length === 0) {
                    tableContainer.style.display = 'none';
                    noImports.style.display = 'block';
                    return;
                }

                tableContainer.style.display = 'block';
                noImports.style.display = 'none';

                // Render table rows
                tbody.innerHTML = this.recentImports.map(importItem => this.renderTableRow(importItem)).join('');

                // Update bulk actions visibility
                this.updateBulkActionsVisibility();
            }

            renderTableRow(importItem) {
                const isSelected = this.selectedImports.includes(importItem.session_id);
                const statusClass = this.getStatusClass(importItem.status);
                const statusText = importItem.status.charAt(0).toUpperCase() + importItem.status.slice(1).replace('_', ' ');
                const progressBar = this.renderProgressBar(importItem);
                const rowsText = this.renderRowsText(importItem);
                const timeAgo = this.formatTimeAgo(importItem.created_at);
                const actions = this.renderActions(importItem);

                return `
                    <tr class="hover:bg-gray-50" data-session-id="${importItem.session_id}">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="checkbox" 
                                   value="${importItem.session_id}"
                                   ${isSelected ? 'checked' : ''}
                                   onchange="dashboard.toggleImportSelection('${importItem.session_id}')"
                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">${importItem.original_filename}</div>
                            <div class="text-sm text-gray-500">${Math.round(importItem.file_size / 1024 * 10) / 10} KB</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                                ${statusText}
                                ${importItem.status === 'processing' ? '<span class="ml-1 animate-pulse">•</span>' : ''}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            ${progressBar}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${rowsText}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${timeAgo}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            ${actions}
                        </td>
                    </tr>
                `;
            }

            getStatusClass(status) {
                const classes = {
                    'completed': 'bg-green-100 text-green-800',
                    'failed': 'bg-red-100 text-red-800',
                    'processing': 'bg-blue-100 text-blue-800',
                    'dry_run': 'bg-blue-100 text-blue-800',
                    'analyzing_file': 'bg-blue-100 text-blue-800'
                };
                return classes[status] || 'bg-gray-100 text-gray-800';
            }

            renderProgressBar(importItem) {
                if (!['processing', 'dry_run', 'analyzing_file'].includes(importItem.status)) {
                    return '<span class="text-sm text-gray-500">-</span>';
                }

                const percentage = importItem.progress_percentage || 0;
                return `
                    <div class="flex items-center">
                        <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: ${percentage}%"></div>
                        </div>
                        <span class="text-sm text-gray-500">${percentage}%</span>
                    </div>
                `;
            }

            renderRowsText(importItem) {
                return importItem.total_rows 
                    ? `${(importItem.processed_rows || 0).toLocaleString()} / ${importItem.total_rows.toLocaleString()}`
                    : '-';
            }

            renderActions(importItem) {
                let actions = `<div class="flex space-x-2">`;
                
                // View link
                actions += `<a href="/import/${importItem.session_id}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">View</a>`;
                
                // Download link (only for completed)
                if (importItem.status === 'completed') {
                    actions += `<a href="/import/${importItem.session_id}/download" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">Download</a>`;
                }
                
                // Delete button
                actions += `<button onclick="dashboard.deleteImport('${importItem.session_id}')" class="text-red-600 hover:text-red-900 text-sm font-medium">Delete</button>`;
                
                actions += `</div>`;
                return actions;
            }

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

            startTimeUpdater() {
                setInterval(() => {
                    this.currentTime = new Date();
                    // Re-render time ago columns
                    const rows = document.querySelectorAll('#imports-tbody tr');
                    rows.forEach((row, index) => {
                        if (this.recentImports[index]) {
                            const timeCell = row.children[5];
                            timeCell.textContent = this.formatTimeAgo(this.recentImports[index].created_at);
                        }
                    });
                }, 30000); // Update every 30 seconds
            }

            toggleSelectAll(checked) {
                this.selectAll = checked;
                if (checked) {
                    this.selectedImports = this.recentImports.map(imp => imp.session_id);
                } else {
                    this.selectedImports = [];
                }
                
                // Update individual checkboxes
                document.querySelectorAll('#imports-tbody input[type="checkbox"]').forEach(cb => {
                    cb.checked = checked;
                });
                
                this.updateBulkActionsVisibility();
            }

            toggleImportSelection(sessionId) {
                const index = this.selectedImports.indexOf(sessionId);
                if (index > -1) {
                    this.selectedImports.splice(index, 1);
                } else {
                    this.selectedImports.push(sessionId);
                }
                
                // Update select all checkbox state
                const selectAllCheckbox = document.getElementById('select-all');
                selectAllCheckbox.checked = this.selectedImports.length === this.recentImports.length;
                selectAllCheckbox.indeterminate = this.selectedImports.length > 0 && this.selectedImports.length < this.recentImports.length;
                
                this.updateBulkActionsVisibility();
            }

            updateBulkActionsVisibility() {
                const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
                const selectedCount = document.getElementById('selected-count');
                
                if (this.selectedImports.length > 0) {
                    bulkDeleteBtn.style.display = 'inline-block';
                    selectedCount.textContent = this.selectedImports.length;
                } else {
                    bulkDeleteBtn.style.display = 'none';
                }
            }

            async deleteImport(sessionId) {
                if (!confirm('Are you sure you want to delete this import? This action cannot be undone.')) {
                    return;
                }

                try {
                    const response = await fetch(`/import/${sessionId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        }
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Remove from local list for instant UI update
                        this.recentImports = this.recentImports.filter(imp => imp.session_id !== sessionId);
                        
                        // Remove from selected if it was selected
                        const selectedIndex = this.selectedImports.indexOf(sessionId);
                        if (selectedIndex > -1) {
                            this.selectedImports.splice(selectedIndex, 1);
                        }
                        
                        // Unsubscribe from WebSocket channel
                        this.unsubscribeFromImport(sessionId);
                        
                        // Update display
                        this.updateDisplay();
                        
                        console.log('Import deleted successfully');
                    } else {
                        alert('Failed to delete import: ' + result.error);
                    }
                } catch (error) {
                    console.error('Delete error:', error);
                    alert('Failed to delete import. Please try again.');
                }
            }

            async bulkDeleteImports() {
                if (this.selectedImports.length === 0) {
                    alert('Please select imports to delete.');
                    return;
                }

                const confirmMessage = `Are you sure you want to delete ${this.selectedImports.length} import(s)? This action cannot be undone.`;
                if (!confirm(confirmMessage)) {
                    return;
                }

                try {
                    // Delete each import in parallel
                    const deletePromises = this.selectedImports.map(sessionId => 
                        fetch(`/import/${sessionId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                            }
                        })
                    );

                    const responses = await Promise.all(deletePromises);
                    
                    let successCount = 0;
                    let failureCount = 0;
                    const successfulDeletes = [];

                    // Process responses
                    for (let i = 0; i < responses.length; i++) {
                        const response = responses[i];
                        const sessionId = this.selectedImports[i];
                        
                        try {
                            const result = await response.json();
                            if (result.success) {
                                successfulDeletes.push(sessionId);
                                successCount++;
                            } else {
                                failureCount++;
                                console.error(`Failed to delete import ${sessionId}:`, result.error);
                            }
                        } catch (error) {
                            failureCount++;
                            console.error(`Error processing response for ${sessionId}:`, error);
                        }
                    }

                    // Remove successfully deleted imports from local list
                    this.recentImports = this.recentImports.filter(imp => !successfulDeletes.includes(imp.session_id));
                    
                    // Unsubscribe from WebSocket channels for deleted imports
                    successfulDeletes.forEach(sessionId => {
                        this.unsubscribeFromImport(sessionId);
                    });
                    
                    // Clear selection
                    this.selectedImports = [];
                    document.getElementById('select-all').checked = false;
                    
                    // Update display
                    this.updateDisplay();

                    // Show result message
                    if (failureCount === 0) {
                        console.log(`Successfully deleted ${successCount} import(s)`);
                    } else {
                        alert(`Deleted ${successCount} import(s), failed to delete ${failureCount} import(s). Check console for details.`);
                    }
                    
                } catch (error) {
                    console.error('Bulk delete error:', error);
                    alert('Failed to delete imports. Please try again.');
                }
            }
        }

        // Initialize the dashboard when DOM is loaded
        let dashboard;
        document.addEventListener('DOMContentLoaded', function() {
            dashboard = new ImportDashboard();
            
            // Make dashboard globally accessible for debugging
            window.dashboard = dashboard;
            
            // Global function to subscribe to any import session
            window.subscribeToImport = function(sessionId) {
                console.log('🌍 Global subscribe to:', sessionId);
                dashboard.subscribeToImport(sessionId);
            };
        });
    </script>
</x-layouts.app>