<x-layouts.app>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Import Session: {{ $session->original_filename }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <!-- Session Status with Real-time Updates -->
                    <div class="mb-6" x-data="importProgress('{{ $session->session_id }}', @js($session->toArray()))" x-init="init()">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900">Import Status</h3>
                            <span 
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                :class="{
                                    'bg-green-100 text-green-800': session.status === 'completed',
                                    'bg-red-100 text-red-800': session.status === 'failed',
                                    'bg-blue-100 text-blue-800': ['processing', 'dry_run', 'analyzing_file'].includes(session.status),
                                    'bg-gray-100 text-gray-800': !['completed', 'failed', 'processing', 'dry_run', 'analyzing_file'].includes(session.status)
                                }"
                            >
                                <span x-text="session.status.charAt(0).toUpperCase() + session.status.slice(1)"></span>
                                <span x-show="session.status === 'processing'" class="ml-1 animate-pulse">•</span>
                            </span>
                        </div>
                        
                        <div x-show="['processing', 'dry_run', 'analyzing_file'].includes(session.status)" class="mt-4">
                            <div class="flex items-center">
                                <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                                    <div 
                                        class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                        :style="'width: ' + session.progress_percentage + '%'"
                                    ></div>
                                </div>
                                <span class="text-sm text-gray-500" x-text="session.progress_percentage + '%'"></span>
                            </div>
                            <div class="flex items-center justify-between mt-2">
                                <p class="text-sm text-gray-600" x-text="session.current_operation || 'Processing...'"></p>
                                <p class="text-xs text-gray-500" x-text="session.rows_per_second ? Math.round(session.rows_per_second) + ' rows/sec' : ''"></p>
                            </div>
                            
                            <!-- Live Statistics -->
                            <div class="grid grid-cols-3 gap-4 mt-4 p-3 bg-gray-50 rounded-lg">
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-gray-900" x-text="session.processed_rows || 0"></div>
                                    <div class="text-xs text-gray-500">Processed</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-green-600" x-text="session.successful_rows || 0"></div>
                                    <div class="text-xs text-gray-500">Successful</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-red-600" x-text="session.failed_rows || 0"></div>
                                    <div class="text-xs text-gray-500">Failed</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Connection Status -->
                        <div class="flex items-center mt-2 text-xs text-gray-500">
                            <div class="flex items-center">
                                <div 
                                    class="w-2 h-2 rounded-full mr-2"
                                    :class="connected ? 'bg-green-500 animate-pulse' : 'bg-red-500'"
                                ></div>
                                <span x-text="connected ? 'Live updates active' : 'Live updates disconnected'"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Session Details -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">File Information</h4>
                            <dl class="text-sm">
                                <dt class="text-gray-500">Filename:</dt>
                                <dd class="text-gray-900 mb-1">{{ $session->original_filename }}</dd>
                                <dt class="text-gray-500">Size:</dt>
                                <dd class="text-gray-900 mb-1">{{ number_format($session->file_size / 1024, 1) }} KB</dd>
                                <dt class="text-gray-500">Created:</dt>
                                <dd class="text-gray-900" x-text="formatTimeAgo('{{ $session->created_at->toISOString() }}')"></dd>
                            </dl>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Progress</h4>
                            <dl class="text-sm">
                                <dt class="text-gray-500">Total Rows:</dt>
                                <dd class="text-gray-900 mb-1">{{ number_format($session->total_rows ?? 0) }}</dd>
                                <dt class="text-gray-500">Processed:</dt>
                                <dd class="text-gray-900 mb-1">{{ number_format($session->processed_rows ?? 0) }}</dd>
                                <dt class="text-gray-500">Import Mode:</dt>
                                <dd class="text-gray-900">{{ ucwords(str_replace('_', ' ', $session->import_mode ?? 'create_or_update')) }}</dd>
                            </dl>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-between items-center pt-4 border-t">
                        <a href="{{ route('import.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            Back to Dashboard
                        </a>
                        
                        <div class="space-x-2">
                            @if($session->status === 'analyzed' && (!$session->column_mapping || empty($session->column_mapping)))
                                <a href="{{ route('import.mapping', $session->session_id) }}" 
                                   class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                    Configure Mapping
                                </a>
                            @endif
                            
                            @if($session->status === 'mapped')
                                <form method="POST" action="{{ route('import.start-processing', $session->session_id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                        Start Processing
                                    </button>
                                </form>
                            @endif
                            
                            @if(in_array($session->status, ['processing', 'dry_run']))
                                <form method="POST" action="{{ route('import.cancel', $session->session_id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                        Cancel
                                    </button>
                                </form>
                            @endif
                            
                            @if($session->status === 'completed')
                                <a href="{{ route('import.download', $session->session_id) }}" 
                                   class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                    Download Report
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function importProgress(sessionId, initialData) {
        return {
            sessionId: sessionId,
            session: initialData,
            connected: false,
            channel: null,
            currentTime: new Date(),
            timeUpdateInterval: null,

            init() {
                this.setupWebSocketConnection();
                this.startTimeUpdates();
            },

            setupWebSocketConnection() {
                if (!window.Echo) {
                    console.warn('Laravel Echo not available');
                    return;
                }

                try {
                    this.channel = window.Echo.channel(`import-progress.${this.sessionId}`);
                    
                    this.channel.listen('ImportProgressUpdated', (data) => {
                        this.session = { ...this.session, ...data };
                        console.log('Import progress updated:', data);
                    });

                    // Connection status events
                    this.channel.subscribed(() => {
                        this.connected = true;
                        console.log('Connected to import progress channel');
                    });

                    this.channel.error((error) => {
                        this.connected = false;
                        console.error('WebSocket connection error:', error);
                    });

                } catch (error) {
                    console.error('Failed to setup WebSocket connection:', error);
                    this.connected = false;
                }
            },

            startTimeUpdates() {
                // Update current time every second for relative time calculations
                this.timeUpdateInterval = setInterval(() => {
                    this.currentTime = new Date();
                }, 1000);
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
                    // For dates older than 30 days, show the actual date and time
                    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                }
            },

            destroy() {
                if (this.channel) {
                    window.Echo.leave(`import-progress.${this.sessionId}`);
                }
                if (this.timeUpdateInterval) {
                    clearInterval(this.timeUpdateInterval);
                }
            }
        };
    }
    </script>
</x-layouts.app>