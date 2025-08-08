<div class="space-y-6" wire:poll.5s="loadProgressData">
    {{-- Header Section --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Import Progress
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ $session->original_filename }} • Session: {{ $session->session_id }}
            </p>
        </div>
        
        <div class="flex items-center space-x-3">
            @if($session->canCancel())
                <flux:button wire:click="cancelImport" size="sm" variant="danger">
                    Cancel Import
                </flux:button>
            @endif
            
            @if($session->status === 'failed')
                <flux:button wire:click="retryImport" size="sm" variant="primary">
                    Retry Import
                </flux:button>
            @endif
            
            <flux:button wire:click="refreshProgress" size="sm" variant="ghost">
                <flux:icon.arrow-path class="size-4" />
                Refresh
            </flux:button>
        </div>
    </div>

    {{-- Status Card --}}
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    @php
                        $statusConfig = [
                            'initializing' => ['color' => 'blue', 'icon' => 'cog-6-tooth'],
                            'analyzing_file' => ['color' => 'blue', 'icon' => 'document-magnifying-glass'],
                            'awaiting_mapping' => ['color' => 'amber', 'icon' => 'pause-circle'],
                            'dry_run' => ['color' => 'blue', 'icon' => 'beaker'],
                            'processing' => ['color' => 'green', 'icon' => 'arrow-path'],
                            'completed' => ['color' => 'green', 'icon' => 'check-circle'],
                            'failed' => ['color' => 'red', 'icon' => 'x-circle'],
                            'cancelled' => ['color' => 'gray', 'icon' => 'stop-circle'],
                        ];
                        $config = $statusConfig[$session->status] ?? ['color' => 'gray', 'icon' => 'question-mark-circle'];
                    @endphp
                    
                    <div class="p-2 rounded-full bg-{{ $config['color'] }}-100 dark:bg-{{ $config['color'] }}-900">
                        <flux:icon name="{{ $config['icon'] }}" class="size-6 text-{{ $config['color'] }}-600 dark:text-{{ $config['color'] }}-400" />
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white capitalize">
                            {{ str_replace('_', ' ', $session->status) }}
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $session->current_operation ?? 'Processing...' }}
                        </p>
                    </div>
                </div>
                
                <div class="text-right">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $session->progress_percentage }}%
                    </div>
                    @if($session->isRunning() && $this->getEstimatedTimeRemaining())
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            ~{{ $this->getEstimatedTimeRemaining() }} remaining
                        </div>
                    @endif
                </div>
            </div>

            {{-- Progress Bar --}}
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 mb-4">
                <div 
                    class="bg-blue-600 h-3 rounded-full transition-all duration-500 ease-out"
                    style="width: {{ $session->progress_percentage }}%"
                ></div>
            </div>

            {{-- Stats Grid --}}
            @if($session->total_rows > 0)
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded">
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ number_format($session->total_rows) }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Total Rows</div>
                    </div>
                    
                    <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded">
                        <div class="text-lg font-semibold text-green-700 dark:text-green-400">
                            {{ number_format($session->successful_rows) }}
                        </div>
                        <div class="text-xs text-green-600 dark:text-green-500">Successful</div>
                    </div>
                    
                    <div class="bg-red-50 dark:bg-red-900/20 p-3 rounded">
                        <div class="text-lg font-semibold text-red-700 dark:text-red-400">
                            {{ number_format($session->failed_rows) }}
                        </div>
                        <div class="text-xs text-red-600 dark:text-red-500">Failed</div>
                    </div>
                    
                    <div class="bg-amber-50 dark:bg-amber-900/20 p-3 rounded">
                        <div class="text-lg font-semibold text-amber-700 dark:text-amber-400">
                            {{ number_format($session->skipped_rows) }}
                        </div>
                        <div class="text-xs text-amber-600 dark:text-amber-500">Skipped</div>
                    </div>
                </div>
            @endif

            {{-- Processing Speed --}}
            @if($session->rows_per_second && $session->isRunning())
                <div class="mt-4 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                        <flux:icon.bolt class="size-3 mr-1" />
                        {{ number_format($session->rows_per_second, 1) }} rows/second
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- Errors Section --}}
    @if(!empty($session->errors))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
            <div class="p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                        Errors ({{ count($session->errors) }})
                    </h3>
                    <flux:button wire:click="downloadErrorLog" size="sm" variant="ghost">
                        <flux:icon.arrow-down-tray class="size-4" />
                        Download Log
                    </flux:button>
                </div>
                
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @foreach(array_slice($session->errors, -5) as $error)
                        <div class="text-sm text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-900/30 p-2 rounded">
                            <div class="font-mono text-xs text-red-600 dark:text-red-400 mb-1">
                                {{ $error['timestamp'] ?? 'Unknown time' }}
                            </div>
                            {{ $error['message'] ?? $error }}
                        </div>
                    @endforeach
                    
                    @if(count($session->errors) > 5)
                        <div class="text-xs text-red-600 dark:text-red-400 text-center">
                            And {{ count($session->errors) - 5 }} more errors...
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Warnings Section --}}
    @if(!empty($session->warnings))
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
            <div class="p-4">
                <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200 mb-3">
                    Warnings ({{ count($session->warnings) }})
                </h3>
                
                <div class="space-y-2 max-h-32 overflow-y-auto">
                    @foreach(array_slice($session->warnings, -3) as $warning)
                        <div class="text-sm text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-900/30 p-2 rounded">
                            {{ $warning['message'] ?? $warning }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- File Analysis Results --}}
    @if($session->file_analysis)
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
            <div class="p-4">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-3">File Analysis</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">File Type</div>
                        <div class="font-medium text-gray-900 dark:text-white uppercase">
                            {{ $session->file_analysis['file_type'] ?? 'Unknown' }}
                        </div>
                    </div>
                    
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Worksheets</div>
                        <div class="font-medium text-gray-900 dark:text-white">
                            {{ $session->file_analysis['total_worksheets'] ?? 0 }}
                        </div>
                    </div>
                    
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Rows</div>
                        <div class="font-medium text-gray-900 dark:text-white">
                            {{ number_format($session->file_analysis['total_rows'] ?? 0) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Debug Info (Development Only) --}}
    @if(app()->environment('local') && !empty($progress))
        <div class="bg-gray-100 dark:bg-gray-800 p-4 rounded border">
            <details>
                <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-300">
                    Debug Info
                </summary>
                <pre class="mt-2 text-xs text-gray-600 dark:text-gray-400 overflow-x-auto">{{ json_encode($progress, JSON_PRETTY_PRINT) }}</pre>
            </details>
        </div>
    @endif
</div>

@script
<script>
    // Handle file downloads
    $wire.on('download-file', (event) => {
        const { content, filename, mimeType } = event[0];
        
        const blob = new Blob([content], { type: mimeType });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    });

    // Auto-scroll to bottom on progress updates
    $wire.on('progress-updated', () => {
        // Smooth scroll to bottom if user is near bottom
        if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 100) {
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        }
    });
</script>
@endscript
