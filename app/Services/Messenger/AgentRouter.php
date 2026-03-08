<?php

declare(strict_types=1);

namespace App\Services\Messenger;

use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Services\Runtime\RuntimeSessionManager;

/**
 * Routes free-form messages to either slash commands, runtime conversation, or structured intent.
 *
 * Decision order:
 * 1. Slash command (CommandRouter) — handled by ProcessChatIntent before AgentRouter
 * 2. Runtime — active runtime session for this user + chat session
 * 3. Intent — ChatIntentParser + ChatActionExecutor
 */
final class AgentRouter
{
    public function __construct(
        private RuntimeSessionManager $sessionManager,
    ) {}

    /**
     * Whether the next message should be handled by the runtime (active session for this chat).
     */
    public function shouldRouteToRuntime(User $user, ?string $chatSessionId): bool
    {
        return $this->getActiveSessionForChat($user, $chatSessionId) !== null;
    }

    /**
     * Get the active runtime session for this user and chat session, if any.
     */
    public function getActiveSessionForChat(User $user, ?string $chatSessionId): ?RuntimeSession
    {
        return $this->sessionManager->getActiveSessionForChat($user, $chatSessionId);
    }
}
