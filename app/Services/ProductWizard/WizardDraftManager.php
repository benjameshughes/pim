<?php

namespace App\Services\ProductWizard;

use App\Actions\Products\Wizard\SaveWizardDraftAction;
use Illuminate\Support\Facades\Auth;

class WizardDraftManager
{
    public function __construct(
        private SaveWizardDraftAction $draftAction
    ) {}

    public function loadDraftIfAvailable(): array
    {
        if (! Auth::check()) {
            return $this->getEmptyDraftResult();
        }

        $result = $this->draftAction->loadDraft(Auth::id());

        if (! $result['data']['exists']) {
            return $this->getEmptyDraftResult();
        }

        return [
            'exists' => true,
            'data' => $result['data']['data'],
            'completedSteps' => $result['data']['steps'] ?? [],
            'savedAt' => $result['data']['saved_at'] ?? null,
        ];
    }

    public function saveDraft(array $wizardData): array
    {
        if (! Auth::check()) {
            return [
                'success' => false,
                'message' => 'Authentication required to save draft',
            ];
        }

        try {
            $result = $this->draftAction->execute(Auth::id(), $wizardData);

            return [
                'success' => $result['success'],
                'savedAt' => $result['success'] ? now()->format('H:i:s') : null,
                'message' => $result['message'] ?? null,
            ];
        } catch (\Exception $e) {
            \Log::error('Draft save failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to save draft',
            ];
        }
    }

    public function clearDraft(): array
    {
        if (! Auth::check()) {
            return [
                'success' => false,
                'message' => 'Authentication required',
            ];
        }

        return $this->draftAction->clearDraft(Auth::id());
    }

    public function getDraftStatus(): array
    {
        if (! Auth::check()) {
            return [
                'exists' => false,
                'message' => 'Login to use drafts',
            ];
        }

        return $this->draftAction->getDraftInfo(Auth::id());
    }

    public function getAutoSaveStatus(bool $isEditMode, bool $isSaving, ?string $lastSaveTime): string
    {
        if ($isEditMode) {
            return 'Changes saved directly to product';
        }

        if (! Auth::check()) {
            return 'Login required for auto-save';
        }

        if ($isSaving) {
            return 'Saving draft...';
        }

        if ($lastSaveTime) {
            return "Last saved at {$lastSaveTime}";
        }

        return 'Auto-save ready';
    }

    private function getEmptyDraftResult(): array
    {
        return [
            'exists' => false,
            'data' => [],
            'completedSteps' => [],
            'savedAt' => null,
        ];
    }
}
