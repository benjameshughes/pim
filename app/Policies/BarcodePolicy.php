<?php

namespace App\Policies;

use App\Models\Barcode;
use App\Models\Team;
use App\Models\User;

class BarcodePolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->canViewTeam($team);
    }

    public function view(User $user, Barcode $barcode): bool
    {
        // For now, allow all authenticated users to view barcodes
        return true;
    }

    public function create(User $user): bool
    {
        // For now, allow all authenticated users to create barcodes
        return true;
    }

    public function update(User $user, Barcode $barcode): bool
    {
        // For now, allow all authenticated users to update barcodes
        return true;
    }

    public function delete(User $user, Barcode $barcode): bool
    {
        // For now, allow all authenticated users to delete barcodes
        return true;
    }

    public function import(User $user): bool
    {
        // For now, allow all authenticated users to import barcodes
        return true;
    }

    public function assign(User $user): bool
    {
        // For now, allow all authenticated users to assign barcodes
        return true;
    }
}
