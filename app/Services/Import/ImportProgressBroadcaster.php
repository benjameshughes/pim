<?php

namespace App\Services\Import;

use App\Events\ImportSessionUpdated;
use App\Models\ImportSession;
use Illuminate\Support\Facades\Log;

class ImportProgressBroadcaster
{
    public function broadcastProgress(ImportSession $session): void
    {
        try {
            event(new ImportSessionUpdated($session));
            
            Log::debug('Import progress broadcasted', [
                'session_id' => $session->session_id,
                'status' => $session->status,
                'progress' => $session->progress_percentage,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast import progress', [
                'session_id' => $session->session_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function broadcastError(ImportSession $session, string $error): void
    {
        $session->addError($error);
        $this->broadcastProgress($session);
    }

    public function broadcastWarning(ImportSession $session, string $warning): void
    {
        $session->addWarning($warning);
        $this->broadcastProgress($session);
    }

    public function broadcastStageUpdate(ImportSession $session, string $stage, string $operation, int $percentage): void
    {
        $session->updateProgress($stage, $operation, $percentage);
        $this->broadcastProgress($session);
    }
}