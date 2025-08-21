<?php

namespace App\Actions\Products\Wizard;

use App\Actions\Base\BaseAction;
use InvalidArgumentException;

/**
 * Save Wizard Draft Action
 *
 * Handles saving and loading of wizard drafts using Laravel sessions.
 * Replaces the complex cache-based system with simple session storage.
 */
class SaveWizardDraftAction extends BaseAction
{
    protected bool $useTransactions = false; // No database operations needed

    /**
     * Save wizard draft data
     *
     * @param  int  $userId  User ID
     * @param  array  $wizardData  Draft data to save
     * @return array Action result
     */
    protected function performAction(...$params): array
    {
        $userId = $params[0] ?? null;
        $wizardData = $params[1] ?? [];

        if (! $userId) {
            throw new InvalidArgumentException('User ID is required');
        }

        $sessionKey = $this->getSessionKey($userId);

        // Save to session with timestamp
        session([
            $sessionKey => [
                'data' => $wizardData,
                'saved_at' => now()->toISOString(),
                'user_id' => $userId,
            ],
        ]);

        return $this->success('Draft saved successfully', [
            'session_key' => $sessionKey,
            'saved_at' => now()->toISOString(),
            'steps_saved' => array_keys($wizardData),
        ]);
    }

    /**
     * Load wizard draft data
     *
     * @param  int  $userId  User ID
     * @return array Action result with draft data
     */
    public function loadDraft(int $userId): array
    {
        $sessionKey = $this->getSessionKey($userId);
        $draftData = session($sessionKey);

        if (! $draftData) {
            return $this->success('No draft found', [
                'data' => [],
                'exists' => false,
            ]);
        }

        return $this->success('Draft loaded successfully', [
            'data' => $draftData['data'],
            'saved_at' => $draftData['saved_at'],
            'exists' => true,
            'steps' => array_keys($draftData['data']),
        ]);
    }

    /**
     * Clear wizard draft
     *
     * @param  int  $userId  User ID
     * @return array Action result
     */
    public function clearDraft(int $userId): array
    {
        $sessionKey = $this->getSessionKey($userId);
        session()->forget($sessionKey);

        return $this->success('Draft cleared successfully', [
            'session_key' => $sessionKey,
            'cleared_at' => now()->toISOString(),
        ]);
    }

    /**
     * Check if draft exists
     *
     * @param  int  $userId  User ID
     * @return array Action result with existence status
     */
    public function draftExists(int $userId): array
    {
        $sessionKey = $this->getSessionKey($userId);
        $exists = session()->has($sessionKey);

        $data = ['exists' => $exists];

        if ($exists) {
            $draftData = session($sessionKey);
            $data['saved_at'] = $draftData['saved_at'] ?? null;
            $data['steps'] = array_keys($draftData['data'] ?? []);
        }

        return $this->success('Draft status checked', $data);
    }

    /**
     * Get draft info
     *
     * @param  int  $userId  User ID
     * @return array Draft information
     */
    public function getDraftInfo(int $userId): array
    {
        $sessionKey = $this->getSessionKey($userId);
        $draftData = session($sessionKey);

        if (! $draftData) {
            return [
                'exists' => false,
                'message' => 'No draft available',
                'saved_at' => null,
                'steps' => [],
            ];
        }

        $stepCount = count($draftData['data']);

        return [
            'exists' => true,
            'message' => $stepCount === 1 ? '1 step' : "{$stepCount} steps",
            'saved_at' => $draftData['saved_at'],
            'steps' => array_keys($draftData['data']),
        ];
    }

    /**
     * Generate session key for user's wizard draft
     */
    protected function getSessionKey(int $userId): string
    {
        return "wizard.product.draft.{$userId}";
    }
}
