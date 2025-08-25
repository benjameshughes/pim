<?php

namespace App\Contracts;

/**
 * Draft Storage Contract
 *
 * Defines the interface for draft storage operations.
 * Allows swapping between cache, session, database, or any other storage backend.
 */
interface DraftStorageInterface
{
    /**
     * Store draft data for a user
     */
    public function store(string $userId, string $draftKey, array $data, ?int $ttl = null): bool;

    /**
     * Retrieve draft data for a user
     */
    public function retrieve(string $userId, string $draftKey): ?array;

    /**
     * Check if draft exists for a user
     */
    public function exists(string $userId, string $draftKey): bool;

    /**
     * Delete draft data for a user
     */
    public function delete(string $userId, string $draftKey): bool;

    /**
     * Clear all drafts for a user
     */
    public function clearAll(string $userId): bool;

    /**
     * Get draft metadata (timestamp, size, etc.)
     */
    public function getMetadata(string $userId, string $draftKey): ?array;
}
