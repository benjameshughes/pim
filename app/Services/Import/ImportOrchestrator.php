<?php

namespace App\Services\Import;

use App\Jobs\Import\AnalyzeFileJob;
use App\Models\ImportSession;
use Illuminate\Support\Facades\Log;

class ImportOrchestrator
{
    public function start(ImportSession $session, ImportConfiguration $configuration): void
    {
        Log::info('Starting import orchestration', [
            'session_id' => $session->session_id,
            'file_name' => $configuration->getFile()->getClientOriginalName(),
            'import_mode' => $configuration->getImportMode(),
            'background_processing' => $configuration->shouldProcessInBackground(),
        ]);

        $session->updateProgress(
            stage: 'initializing',
            operation: 'Starting import analysis',
            percentage: 0
        );

        if ($configuration->shouldProcessInBackground()) {
            $this->startBackgroundProcessing($session);
        } else {
            $this->startSynchronousProcessing($session);
        }
    }

    private function startBackgroundProcessing(ImportSession $session): void
    {
        // Dispatch the first job in the chain
        AnalyzeFileJob::dispatch($session)
            ->onQueue('imports')
            ->onConnection('database');
        
        $session->update([
            'status' => 'analyzing_file',
            'current_operation' => 'Queued for background processing',
        ]);

        Log::info('Import queued for background processing', [
            'session_id' => $session->session_id,
        ]);
    }

    private function startSynchronousProcessing(ImportSession $session): void
    {
        Log::info('Starting synchronous processing', [
            'session_id' => $session->session_id,
        ]);

        try {
            // Run jobs synchronously
            app(AnalyzeFileJob::class)->handle($session);
            
        } catch (\Exception $e) {
            Log::error('Synchronous import failed', [
                'session_id' => $session->session_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $session->markAsFailed($e->getMessage());
        }
    }

    public function cancel(ImportSession $session): bool
    {
        if (!$session->canCancel()) {
            return false;
        }

        Log::info('Cancelling import session', [
            'session_id' => $session->session_id,
            'current_status' => $session->status,
        ]);

        // Cancel any queued jobs if possible
        if ($session->current_job_id) {
            // TODO: Implement job cancellation when needed
        }

        $session->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'failure_reason' => 'Cancelled by user',
        ]);

        return true;
    }

    public function retry(ImportSession $session): ImportSession
    {
        if (!$session->isCompleted() || $session->status !== 'failed') {
            throw new \InvalidArgumentException('Can only retry failed import sessions');
        }

        Log::info('Retrying failed import session', [
            'original_session_id' => $session->session_id,
        ]);

        // Create a new session with the same configuration
        $newSession = ImportSession::create([
            'user_id' => $session->user_id,
            'original_filename' => $session->original_filename,
            'file_path' => $session->file_path,
            'file_type' => $session->file_type,
            'file_size' => $session->file_size,
            'file_hash' => $session->file_hash,
            'status' => 'initializing',
            'configuration' => $session->configuration,
        ]);

        // Restart the process
        $configuration = $this->reconstructConfiguration($session->configuration);
        $this->start($newSession, $configuration);

        return $newSession;
    }

    public function getStatus(ImportSession $session): array
    {
        return [
            'session_id' => $session->session_id,
            'status' => $session->status,
            'current_stage' => $session->current_stage,
            'current_operation' => $session->current_operation,
            'progress_percentage' => $session->progress_percentage,
            'processed_rows' => $session->processed_rows,
            'total_rows' => $session->total_rows,
            'successful_rows' => $session->successful_rows,
            'failed_rows' => $session->failed_rows,
            'skipped_rows' => $session->skipped_rows,
            'rows_per_second' => $session->rows_per_second,
            'estimated_completion' => $session->estimated_completion,
            'errors' => $session->errors ?? [],
            'warnings' => $session->warnings ?? [],
            'is_running' => $session->isRunning(),
            'is_completed' => $session->isCompleted(),
            'can_cancel' => $session->canCancel(),
            'created_at' => $session->created_at,
            'started_at' => $session->started_at,
            'completed_at' => $session->completed_at,
        ];
    }

    private function reconstructConfiguration(array $configData): ImportConfiguration
    {
        // This would need to be implemented to reconstruct the configuration
        // from the stored array data. For now, we'll throw an exception.
        throw new \RuntimeException('Configuration reconstruction not yet implemented');
    }
}