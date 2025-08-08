<?php

namespace App\Livewire\Import;

use App\Models\ImportSession;
use App\Services\Import\ImportOrchestrator;
use Livewire\Attributes\On;
use Livewire\Component;

class ImportProgress extends Component
{
    public string $sessionId;
    public ImportSession $session;
    public array $progress = [];
    public bool $autoRefresh = true;

    public function mount(string $sessionId): void
    {
        $this->sessionId = $sessionId;
        $this->session = ImportSession::where('session_id', $sessionId)->firstOrFail();
        $this->loadProgressData();
    }

    public function loadProgressData(): void
    {
        $this->session->refresh();
        
        $orchestrator = app(ImportOrchestrator::class);
        $this->progress = $orchestrator->getStatus($this->session);
    }

    #[On('echo:import-progress.{sessionId},ImportProgressUpdated')]
    public function updateProgress($payload): void
    {
        $this->progress = $payload;
        
        // Trigger a UI update
        $this->dispatch('progress-updated', $payload);
    }

    public function refreshProgress(): void
    {
        $this->loadProgressData();
    }

    public function cancelImport(): void
    {
        if (!$this->session->canCancel()) {
            session()->flash('error', 'This import cannot be cancelled at this stage.');
            return;
        }

        $orchestrator = app(ImportOrchestrator::class);
        
        if ($orchestrator->cancel($this->session)) {
            session()->flash('message', 'Import has been cancelled successfully.');
            $this->loadProgressData();
        } else {
            session()->flash('error', 'Failed to cancel import.');
        }
    }

    public function retryImport(): void
    {
        if ($this->session->status !== 'failed') {
            session()->flash('error', 'Only failed imports can be retried.');
            return;
        }

        try {
            $orchestrator = app(ImportOrchestrator::class);
            $newSession = $orchestrator->retry($this->session);
            
            session()->flash('message', 'Import retry has been started.');
            
            // Redirect to new session
            return $this->redirect(
                route('import.progress', ['sessionId' => $newSession->session_id])
            );
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to retry import: ' . $e->getMessage());
        }
    }

    public function downloadErrorLog(): void
    {
        if (empty($this->session->errors)) {
            session()->flash('error', 'No errors to download.');
            return;
        }

        $errors = collect($this->session->errors);
        $csvContent = "Timestamp,Error Message\n";
        
        foreach ($errors as $error) {
            $csvContent .= '"' . ($error['timestamp'] ?? now()) . '","' . str_replace('"', '""', $error['message'] ?? $error) . "\"\n";
        }

        $this->dispatch('download-file', [
            'content' => $csvContent,
            'filename' => "import-errors-{$this->session->session_id}.csv",
            'mimeType' => 'text/csv',
        ]);
    }

    public function getEstimatedTimeRemaining(): ?string
    {
        if (!isset($this->progress['rows_per_second']) || !$this->progress['rows_per_second'] || !isset($this->progress['total_rows'])) {
            return null;
        }

        $remainingRows = $this->progress['total_rows'] - $this->progress['processed_rows'];
        if ($remainingRows <= 0) {
            return null;
        }

        $secondsRemaining = ceil($remainingRows / $this->progress['rows_per_second']);
        
        if ($secondsRemaining < 60) {
            return $secondsRemaining . ' seconds';
        } elseif ($secondsRemaining < 3600) {
            return ceil($secondsRemaining / 60) . ' minutes';
        } else {
            return ceil($secondsRemaining / 3600) . ' hours';
        }
    }

    public function render()
    {
        return view('livewire.import.import-progress');
    }
}
