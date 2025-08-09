<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Import session channels - public for now (could be made private later)
Broadcast::channel('import.{sessionId}', function ($user) {
    // For now, allow any authenticated user to listen to any import session
    // In production, you might want to check if user owns the session
    return true;
});
