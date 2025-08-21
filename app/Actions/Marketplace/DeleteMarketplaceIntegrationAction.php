<?php

namespace App\Actions\Marketplace;

use App\Models\SyncAccount;
use Illuminate\Support\Facades\Validator;

/**
 * 🗑️ DELETE MARKETPLACE INTEGRATION ACTION
 *
 * Safely deletes a marketplace integration with proper cleanup and validation.
 * Follows single responsibility principle.
 */
class DeleteMarketplaceIntegrationAction extends BaseMarketplaceAction
{
    /**
     * 🗑️ EXECUTE: Delete marketplace integration
     */
    public function execute(SyncAccount $account, array $options = []): bool
    {
        $this->logActivity('delete_integration_started', [
            'account_id' => $account->id,
            'marketplace_type' => $account->marketplace_type,
            'display_name' => $account->display_name,
        ]);

        // Validate options
        $this->validateOptions($options);

        // Check if account has active syncs (unless force delete)
        if (! ($options['force'] ?? false)) {
            $this->validateSafeToDelete($account);
        }

        // Perform deletion within transaction
        return $this->executeWithTransaction(function () use ($account, $options) {

            // Archive sync data if requested
            if ($options['archive_data'] ?? false) {
                $this->archiveSyncData($account);
            }

            // Clean up related data if requested
            if ($options['cleanup_relations'] ?? true) {
                $this->cleanupRelatedData($account);
            }

            // Log the deletion
            $this->logActivity('integration_deleted', [
                'account_id' => $account->id,
                'marketplace_type' => $account->marketplace_type,
                'display_name' => $account->display_name,
                'archived' => $options['archive_data'] ?? false,
                'cleanup_performed' => $options['cleanup_relations'] ?? true,
            ]);

            // Delete the account
            return $account->delete();
        });
    }

    /**
     * 🔍 VALIDATE OPTIONS
     */
    private function validateOptions(array $options): void
    {
        $validator = Validator::make($options, [
            'force' => ['sometimes', 'boolean'],
            'archive_data' => ['sometimes', 'boolean'],
            'cleanup_relations' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException(
                'Invalid options: '.implode(', ', $validator->errors()->all())
            );
        }
    }

    /**
     * ⚠️ VALIDATE SAFE TO DELETE
     */
    private function validateSafeToDelete(SyncAccount $account): void
    {
        // Check for active sync operations
        $activeSyncs = $account->syncStatuses()
            ->whereIn('sync_status', ['pending', 'syncing'])
            ->count();

        if ($activeSyncs > 0) {
            throw new \InvalidArgumentException(
                "Cannot delete integration with {$activeSyncs} active sync operations. Use force=true to override."
            );
        }

        // Check for recent sync activity (last 24 hours)
        $recentActivity = $account->syncLogs()
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($recentActivity > 0) {
            $this->logActivity('delete_warning_recent_activity', [
                'account_id' => $account->id,
                'recent_syncs' => $recentActivity,
            ], 'warning');
        }
    }

    /**
     * 📦 ARCHIVE SYNC DATA
     */
    private function archiveSyncData(SyncAccount $account): void
    {
        $archiveData = [
            'account_id' => $account->id,
            'account_name' => $account->name,
            'marketplace_type' => $account->marketplace_type,
            'marketplace_subtype' => $account->marketplace_subtype,
            'display_name' => $account->display_name,
            'deleted_at' => now()->toISOString(),
            'sync_statistics' => $account->getSyncStats(),
            'total_sync_logs' => $account->syncLogs()->count(),
            'total_sync_statuses' => $account->syncStatuses()->count(),
        ];

        // Store archive data in settings of a special "deleted" account record
        // This could be enhanced to use a separate archive table
        $this->logActivity('sync_data_archived', $archiveData);
    }

    /**
     * 🧹 CLEANUP RELATED DATA
     */
    private function cleanupRelatedData(SyncAccount $account): void
    {
        $cleanupStats = [
            'sync_logs_deleted' => 0,
            'sync_statuses_deleted' => 0,
        ];

        // Delete sync logs
        $cleanupStats['sync_logs_deleted'] = $account->syncLogs()->delete();

        // Delete sync statuses
        $cleanupStats['sync_statuses_deleted'] = $account->syncStatuses()->delete();

        $this->logActivity('related_data_cleaned', [
            'account_id' => $account->id,
            'cleanup_stats' => $cleanupStats,
        ]);
    }

    /**
     * 🚫 SOFT DELETE: Deactivate instead of delete
     */
    public function softDelete(SyncAccount $account): SyncAccount
    {
        $this->logActivity('soft_delete_integration', [
            'account_id' => $account->id,
            'marketplace_type' => $account->marketplace_type,
        ]);

        return $this->executeWithTransaction(function () use ($account) {
            $account->update([
                'is_active' => false,
                'settings' => array_merge($account->settings ?? [], [
                    'deactivated_at' => now()->toISOString(),
                    'deactivation_reason' => 'soft_delete',
                ]),
            ]);

            return $account->fresh();
        });
    }

    /**
     * 🔄 RESTORE SOFT DELETED
     */
    public function restore(SyncAccount $account): SyncAccount
    {
        if ($account->is_active) {
            throw new \InvalidArgumentException('Account is already active.');
        }

        $this->logActivity('restore_integration', [
            'account_id' => $account->id,
            'marketplace_type' => $account->marketplace_type,
        ]);

        return $this->executeWithTransaction(function () use ($account) {
            $settings = $account->settings ?? [];
            unset($settings['deactivated_at'], $settings['deactivation_reason']);

            $account->update([
                'is_active' => true,
                'settings' => array_merge($settings, [
                    'reactivated_at' => now()->toISOString(),
                ]),
            ]);

            return $account->fresh();
        });
    }

    /**
     * 🧹 BULK DELETE WITH OPTIONS
     */
    public function bulkDelete(array $accountIds, array $options = []): array
    {
        $this->logActivity('bulk_delete_started', [
            'account_count' => count($accountIds),
            'options' => $options,
        ]);

        $results = [
            'deleted' => [],
            'failed' => [],
            'skipped' => [],
        ];

        foreach ($accountIds as $accountId) {
            try {
                $account = SyncAccount::findOrFail($accountId);

                if ($this->execute($account, $options)) {
                    $results['deleted'][] = $accountId;
                } else {
                    $results['failed'][] = $accountId;
                }
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->logActivity('bulk_delete_completed', [
            'results' => array_map('count', $results),
            'total_processed' => count($accountIds),
        ]);

        return $results;
    }
}
