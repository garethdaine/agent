<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\InterrogationSession;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('interrogation.{sessionId}', function ($user, $sessionId) {
    return InterrogationSession::query()
        ->whereKey((int) $sessionId)
        ->where('user_id', (int) $user->id)
        ->exists();
});
