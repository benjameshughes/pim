<?php

namespace App\Services\Import\Conflicts;

interface ConflictResolverInterface
{
    /**
     * Resolve a specific type of conflict
     *
     * @param array $conflictData Data about the conflict extracted from exception
     * @param array $context Import context (row data, configuration, etc.)
     * @return ConflictResolution
     */
    public function resolve(array $conflictData, array $context = []): ConflictResolution;

    /**
     * Check if this resolver can handle the given conflict type
     *
     * @param array $conflictData
     * @return bool
     */
    public function canResolve(array $conflictData): bool;
}