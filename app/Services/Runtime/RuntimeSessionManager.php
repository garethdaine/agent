<?php

namespace App\Services\Runtime;

use App\Enums\Runtime\BrowserPersistenceMode;
use App\Enums\Runtime\PolicySnapshotReason;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Exceptions\Runtime\ConcurrentSessionLimitExceededException;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RuntimeSessionManager
{
    public function __construct(
        private PolicyEngine $policyEngine,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Create a new runtime session for the user.
     *
     * @param  User  $user  The user creating the session
     * @param  array  $options  Session options including:
     *                          - chat_session_id: UUID of the linked chat session (optional)
     *                          - title: Session title (optional)
     *                          - workspace_root: Workspace directory path (optional)
     *                          - mode: Execution mode ('safe', 'standard', 'full') defaults to config default
     *                          - browser_persistence: Browser persistence mode ('ephemeral', 'persistent')
     * @param  ConnectorAccount|null  $connector  Optional connector account for limit override
     *
     * @throws ConcurrentSessionLimitExceededException When concurrent session limit is exceeded
     */
    public function createSession(
        User $user,
        array $options = [],
        ?ConnectorAccount $connector = null
    ): RuntimeSession {
        $this->enforceConcurrentLimit($user, $connector);

        $session = RuntimeSession::create([
            'user_id' => $user->id,
            'chat_session_id' => $options['chat_session_id'] ?? null,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::from($options['mode'] ?? config('runtime.default_mode')),
            'title' => $options['title'] ?? null,
            'workspace_root' => $options['workspace_root'] ?? null,
            'browser_persistence_mode' => BrowserPersistenceMode::from(
                $options['browser_persistence'] ?? config('runtime.browser.default_persistence')
            ),
            'started_at' => now(),
        ]);

        if ($this->policyEngine->shouldTriggerSnapshot('session_start')) {
            $this->policyEngine->captureSnapshot($session, PolicySnapshotReason::SessionStart);
        }

        $this->auditLogger->recordSystemAction(
            'runtime.session.created',
            'runtime_session',
            $session->id,
            ['status', 'mode', 'workspace_root'],
        );

        return $session;
    }

    /**
     * Stop an active runtime session.
     *
     * Updates the session status to Stopped and sets the ended_at timestamp.
     */
    public function stopSession(RuntimeSession $session): void
    {
        $session->update([
            'status' => RuntimeSessionStatus::Stopped,
            'ended_at' => now(),
        ]);

        $this->auditLogger->recordSystemAction(
            'runtime.session.stopped',
            'runtime_session',
            $session->id,
            ['status', 'ended_at'],
        );

        $this->dispatchMemoryFormation($session);
    }

    /**
     * Dispatch memory formation for a completed session's turns when memory is enabled.
     */
    private function dispatchMemoryFormation(RuntimeSession $session): void
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            return;
        }

        try {
            $turnSummaries = $session->turns()
                ->whereNotNull('summary')
                ->pluck('summary', 'id')
                ->toArray();

            if ($turnSummaries === []) {
                return;
            }

            $contextPath = storage_path(
                "app/memory/context/runtime/{$session->id}.md"
            );

            $contextDir = dirname($contextPath);
            if (! is_dir($contextDir)) {
                mkdir($contextDir, 0755, true);
            }

            $markdown = "# Runtime Session {$session->id}\n\n";
            $markdown .= "Mode: {$session->mode->value}\n";
            $markdown .= "Started: {$session->started_at?->toIso8601String()}\n";
            $markdown .= "Ended: {$session->ended_at?->toIso8601String()}\n\n";

            foreach ($turnSummaries as $turnId => $summary) {
                $markdown .= "## Turn {$turnId}\n\n{$summary}\n\n";
            }

            file_put_contents($contextPath, $markdown);

            Log::channel('runtime')->info('Memory context written for runtime session', [
                'session_id' => $session->id,
                'turns_count' => count($turnSummaries),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write runtime session memory context', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Change the execution mode of a runtime session.
     *
     * Updates the session mode and captures a policy snapshot if configured.
     */
    public function changeMode(RuntimeSession $session, RuntimeMode $newMode): void
    {
        $previousMode = $session->mode;
        $session->update(['mode' => $newMode]);

        if ($this->policyEngine->shouldTriggerSnapshot('mode_change')) {
            $this->policyEngine->captureSnapshot($session, PolicySnapshotReason::ModeChange);
        }

        $this->auditLogger->recordSystemAction(
            'runtime.session.mode_changed',
            'runtime_session',
            $session->id,
            ['mode'],
        );
    }

    /**
     * Get all active sessions for a user.
     *
     * @return Collection<int, RuntimeSession>
     */
    public function getActiveSessions(User $user): Collection
    {
        return RuntimeSession::where('user_id', $user->id)
            ->where('status', RuntimeSessionStatus::Active)
            ->orderBy('started_at', 'desc')
            ->get();
    }

    /**
     * Get the active runtime session for a user and chat session, if any.
     */
    public function getActiveSessionForChat(User $user, ?string $chatSessionId): ?RuntimeSession
    {
        if ($chatSessionId === null || $chatSessionId === '') {
            return null;
        }

        return RuntimeSession::where('user_id', $user->id)
            ->where('chat_session_id', $chatSessionId)
            ->where('status', RuntimeSessionStatus::Active)
            ->orderByDesc('started_at')
            ->first();
    }

    /**
     * Get existing active session for this chat or create a new one.
     *
     * @param  array  $options  Same as createSession (title, workspace_root, mode, etc.)
     */
    public function getOrCreateSessionForChat(
        User $user,
        string $chatSessionId,
        ConnectorAccount $connector,
        array $options = []
    ): RuntimeSession {
        $existing = $this->getActiveSessionForChat($user, $chatSessionId);

        if ($existing !== null) {
            return $existing;
        }

        $options['chat_session_id'] = $chatSessionId;

        return $this->createSession($user, $options, $connector);
    }

    /**
     * Enforce the concurrent session limit for a user.
     *
     * The limit can be overridden per connector account via runtime_settings.concurrent_session_limit.
     * Default limit is from config/runtime.php concurrent_session_limit_default (3).
     *
     * @throws ConcurrentSessionLimitExceededException When the limit would be exceeded
     */
    private function enforceConcurrentLimit(User $user, ?ConnectorAccount $connector): void
    {
        $limit = $connector?->runtime_settings['concurrent_session_limit']
            ?? config('runtime.concurrent_session_limit_default', 3);

        $activeCount = RuntimeSession::where('user_id', $user->id)
            ->where('status', RuntimeSessionStatus::Active)
            ->count();

        if ($activeCount >= $limit) {
            throw new ConcurrentSessionLimitExceededException(
                "Concurrent session limit of {$limit} exceeded. Stop an existing session first."
            );
        }
    }
}
