<?php

use App\Models\DelegationGraph;
use App\Models\InterrogationSession;
use Illuminate\Support\Facades\Broadcast;

// Register the /broadcasting/auth endpoint for private channel authentication.
// Uses 'web' middleware for Sanctum session-based auth (Inertia SPA).
Broadcast::routes(['middleware' => ['web']]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});

Broadcast::channel('interrogation.{sessionId}', function ($user, $sessionId) {
    return InterrogationSession::query()
        ->whereKey((int) $sessionId)
        ->where('user_id', (int) $user->id)
        ->exists();
});

Broadcast::channel('delegation.graph.{graphId}', function ($user, $graphId) {
    return DelegationGraph::query()
        ->whereKey((int) $graphId)
        ->where('user_id', (int) $user->id)
        ->exists();
});

Broadcast::channel('delegation.user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Memory system channels
Broadcast::channel('memory.diagnostics.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
