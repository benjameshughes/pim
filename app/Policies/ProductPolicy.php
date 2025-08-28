<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\Team;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->canViewTeam($team);
    }

    public function view(User $user, Product $product): bool
    {
        // For now, allow all authenticated users to view products
        // You can add team logic here later if needed
        return true;
    }

    public function create(User $user): bool
    {
        // For now, allow all authenticated users to create products
        // You can add team logic here later if needed
        return true;
    }

    public function update(User $user, Product $product): bool
    {
        // For now, allow all authenticated users to update products
        // You can add team logic here later if needed
        return true;
    }

    public function delete(User $user, Product $product): bool
    {
        // For now, allow all authenticated users to delete products
        // You can add team logic here later if needed
        return true;
    }

    public function restore(User $user, Product $product): bool
    {
        return true;
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return true;
    }
}
