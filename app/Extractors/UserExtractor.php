<?php

namespace App\Extractors;

use App\Models\User;

class UserExtractor
{
    public static function extract(User $user): array
    {
        return [
            'id' => $user->id,
            'type' => 'User',
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles?->pluck('name')->toArray() ?? [],
        ];
    }
}