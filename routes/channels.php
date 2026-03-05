<?php

use App\Models\AgentJobRun;
use App\Models\DelegationGraph;
use App\Models\InterrogationSession;
use App\Models\RepoAnalysisSession;
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

Broadcast::channel('code-analysis.{sessionId}', function ($user, $sessionId) {
    $session = RepoAnalysisSession::query()->find((int) $sessionId);
    if ($session === null) {
        return false;
    }

    if ($user->hasRole('admin')) {
        return true;
    }

    return (int) $session->user_id === (int) $user->id;
});

// Memory system channels
Broadcast::channel('memory.diagnostics.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Run event stream channel
Broadcast::channel('run.{runId}', function ($user, $runId) {
    return AgentJobRun::query()
        ->whereKey((int) $runId)
        ->where('user_id', (int) $user->id)
        ->exists();
});

// Agent Office real-time channel
Broadcast::channel('office.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
