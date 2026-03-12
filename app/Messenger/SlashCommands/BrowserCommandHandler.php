<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Enums\Runtime\BrowserPersistenceMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Services\Runtime\BrowserProfileResolver;
use App\Services\Runtime\RuntimeSessionManager;
use Illuminate\Support\Facades\Log;

/**
 * Handler for the /browser slash command.
 *
 * Controls the browser sidecar for the user's runtime sessions.
 * /browser start creates (or resumes) a runtime session for this chat so
 * subsequent messages are handled by the runtime with the browser tool available.
 *
 * Usage:
 * - /browser start     - Start the browser sidecar and attach runtime to this chat
 * - /browser stop      - Stop the browser sidecar
 * - /browser reset     - Reset the browser sidecar (stop and start)
 * - /browser status    - Check browser sidecar status
 * - /browser profile <name> - Set a persistent browser profile for the active session
 * - /browser profiles  - List available browser profiles
 * - /browser cdp <ws://...> - Connect to a running Chrome via CDP
 * - /browser headed    - Toggle headed (visible) browser mode
 */
final class BrowserCommandHandler implements SlashCommandHandlerInterface
{
    private const VALID_ACTIONS = ['start', 'stop', 'reset', 'status', 'profile', 'profiles', 'cdp', 'headed'];

    /**
     * @param  array<int, string>  $args
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        if (empty($args)) {
            return $this->showUsage();
        }

        $action = strtolower($args[0]);

        if (! in_array($action, self::VALID_ACTIONS, true)) {
            return $this->showUsage();
        }

        $connector = $connectorAccountId !== null
            ? ConnectorAccount::find($connectorAccountId)
            : null;

        return match ($action) {
            'start' => $this->startBrowser($user, $chatSessionId, $connector),
            'stop' => $this->stopBrowser($user),
            'reset' => $this->resetBrowser($user),
            'status' => $this->browserStatus($user, $chatSessionId),
            'profile' => $this->setProfile($user, $chatSessionId, array_slice($args, 1)),
            'profiles' => $this->listProfiles($user),
            'cdp' => $this->setCdp($user, $chatSessionId, array_slice($args, 1)),
            'headed' => $this->toggleHeaded($user, $chatSessionId),
            default => $this->showUsage(),
        };
    }

    private function showUsage(): CommandResult
    {
        return CommandResult::failure(
            "Usage: /browser <action> [args]\n".
            "  start           - Start the browser sidecar\n".
            "  stop            - Stop the browser sidecar\n".
            "  reset           - Reset the browser sidecar\n".
            "  status          - Check browser status\n".
            "  profile <name>  - Set persistent browser profile for this session\n".
            "  profiles        - List available browser profiles\n".
            "  cdp <ws://...>  - Connect to running Chrome via CDP\n".
            '  headed          - Toggle headed (visible) browser mode'
        );
    }

    private function startBrowser(User $user, ?string $chatSessionId, ?ConnectorAccount $connector): CommandResult
    {
        $browserPath = config('runtime.browser.sidecar_binary');
        if (empty($browserPath)) {
            return CommandResult::failure('Browser sidecar is not configured');
        }

        if ($chatSessionId !== null && $chatSessionId !== '' && $connector !== null) {
            $sessionManager = app(RuntimeSessionManager::class);
            $sessionManager->getOrCreateSessionForChat($user, $chatSessionId, $connector, []);
        }

        return CommandResult::success(
            'Browser sidecar is ready. You can ask me to use the browser (e.g. "navigate to x.com" or "check example.com") and I\'ll use the browser tool.',
            ['action' => 'start', 'status' => 'ready']
        );
    }

    private function stopBrowser(User $user): CommandResult
    {
        // Note: Actual browser lifecycle management will be handled by BrowserSidecarManager
        // This handler provides the command interface; actual implementation pending Phase 5

        return CommandResult::success(
            'Browser sidecar stop requested',
            ['action' => 'stop', 'status' => 'pending']
        );
    }

    private function resetBrowser(User $user): CommandResult
    {
        // Note: Actual browser lifecycle management will be handled by BrowserSidecarManager
        // This handler provides the command interface; actual implementation pending Phase 5

        return CommandResult::success(
            'Browser sidecar reset requested',
            ['action' => 'reset', 'status' => 'pending']
        );
    }

    private function browserStatus(User $user, ?string $chatSessionId = null): CommandResult
    {
        // Check configuration
        $browserPath = config('runtime.browser.sidecar_binary');
        $defaultPersistence = config('runtime.browser.default_persistence', 'ephemeral');

        if (empty($browserPath)) {
            return CommandResult::success(
                'Browser sidecar is not configured',
                ['configured' => false]
            );
        }

        $statusLines = [
            'Browser sidecar configured',
            "Binary: {$browserPath}",
            "Default persistence: {$defaultPersistence}",
        ];

        $data = [
            'configured' => true,
            'binary' => $browserPath,
            'default_persistence' => $defaultPersistence,
            'status' => 'unknown',
        ];

        // Show active session browser settings if available
        $session = $this->findActiveSession($user, $chatSessionId);
        if ($session !== null) {
            $mode = $session->browser_persistence_mode?->value ?? 'ephemeral';
            $statusLines[] = "Session mode: {$mode}";
            $data['session_persistence_mode'] = $mode;

            if ($session->hasPersistentBrowserProfile()) {
                $statusLines[] = "Profile: {$session->browser_profile_name}";
                $data['profile_name'] = $session->browser_profile_name;
            }

            if ($session->hasCdpConnection()) {
                $statusLines[] = "CDP: {$session->browser_cdp_endpoint}";
                $data['cdp_endpoint'] = $session->browser_cdp_endpoint;
            }
        }

        return CommandResult::success(implode("\n", $statusLines), $data);
    }

    /**
     * Set a persistent browser profile for the active runtime session.
     *
     * Updates ALL active sessions for this chat to prevent mismatches when
     * duplicate sessions exist due to race conditions.
     *
     * @param  array<int, string>  $args
     */
    private function setProfile(User $user, ?string $chatSessionId, array $args): CommandResult
    {
        if (empty($args)) {
            return CommandResult::failure(
                "Usage: /browser profile <name>\n".
                'Provide a profile name (e.g. "twitter", "linkedin", "default").'
            );
        }

        $profileName = implode('-', $args);

        $session = $this->findActiveSession($user, $chatSessionId);
        if ($session === null) {
            return CommandResult::failure(
                'No active runtime session found. Use `/browser start` first.'
            );
        }

        Log::channel('runtime')->info('BrowserCommandHandler: Setting profile', [
            'session_id' => $session->id,
            'profile_name' => $profileName,
            'current_mode' => $session->browser_persistence_mode?->value,
            'current_profile' => $session->browser_profile_name,
        ]);

        // Update ALL active sessions for this chat to prevent mismatches when
        // duplicate sessions exist (race condition between /browser start and first message)
        $updatedCount = $this->updateAllActiveSessionsForChat($user, $chatSessionId, [
            'browser_persistence_mode' => BrowserPersistenceMode::Persistent,
            'browser_profile_name' => $profileName,
            'browser_cdp_endpoint' => null, // mutually exclusive
        ]);

        Log::channel('runtime')->info('BrowserCommandHandler: Profile set on sessions', [
            'sessions_updated' => $updatedCount,
            'chat_session_id' => $chatSessionId,
            'profile_name' => $profileName,
        ]);

        // Verify the primary session was persisted
        $session->refresh();
        if ($session->browser_profile_name !== $profileName) {
            Log::channel('runtime')->error('BrowserCommandHandler: Profile name NOT saved after update!', [
                'session_id' => $session->id,
                'expected' => $profileName,
                'actual' => $session->browser_profile_name,
                'mode' => $session->browser_persistence_mode?->value,
            ]);
        }

        $resolver = app(BrowserProfileResolver::class);
        $profilePath = $resolver->resolveProfilePath($user, $profileName);
        $resolver->ensureProfileDirectory($profilePath);

        $isExisting = $resolver->profileExists($profilePath);
        $statusLabel = $isExisting ? 'existing' : 'new';

        return CommandResult::success(
            "Browser profile set to \"{$profileName}\" ({$statusLabel} profile).\n".
            "Cookies, localStorage, and login sessions will persist across turns.\n".
            'The browser will run in headed mode for initial login.',
            [
                'action' => 'profile',
                'profile_name' => $profileName,
                'profile_path' => $profilePath,
                'persistence_mode' => 'persistent',
                'sessions_updated' => $updatedCount,
            ]
        );
    }

    /**
     * List available browser profiles for the user.
     */
    private function listProfiles(User $user): CommandResult
    {
        $resolver = app(BrowserProfileResolver::class);
        $profiles = $resolver->listProfiles($user);

        if (empty($profiles)) {
            return CommandResult::success(
                'No browser profiles found. Use `/browser profile <name>` to create one.',
                ['action' => 'profiles', 'profiles' => []]
            );
        }

        $lines = ['Available browser profiles:'];
        foreach ($profiles as $profile) {
            $lines[] = "  - {$profile}";
        }

        return CommandResult::success(
            implode("\n", $lines),
            ['action' => 'profiles', 'profiles' => $profiles]
        );
    }

    /**
     * Connect the active runtime session to a running Chrome via CDP.
     *
     * @param  array<int, string>  $args
     */
    private function setCdp(User $user, ?string $chatSessionId, array $args): CommandResult
    {
        if (empty($args)) {
            return CommandResult::failure(
                "Usage: /browser cdp <ws://...>\n".
                "Provide a WebSocket CDP URL (e.g. ws://localhost:9222/devtools/browser/abc123).\n".
                'Find it by opening chrome://inspect in your Chrome browser.'
            );
        }

        $endpoint = $args[0];

        if (! str_starts_with($endpoint, 'ws://') && ! str_starts_with($endpoint, 'wss://')) {
            return CommandResult::failure(
                "Invalid CDP endpoint: must start with ws:// or wss://\n".
                'Example: ws://localhost:9222/devtools/browser/abc123'
            );
        }

        $session = $this->findActiveSession($user, $chatSessionId);
        if ($session === null) {
            return CommandResult::failure(
                'No active runtime session found. Use `/browser start` first.'
            );
        }

        // Update ALL active sessions for this chat
        $updatedCount = $this->updateAllActiveSessionsForChat($user, $chatSessionId, [
            'browser_persistence_mode' => BrowserPersistenceMode::Cdp,
            'browser_cdp_endpoint' => $endpoint,
            'browser_profile_name' => null, // mutually exclusive
        ]);

        return CommandResult::success(
            "Connected to Chrome via CDP: {$endpoint}\n".
            'All browser commands will go through the connected Chrome instance.',
            [
                'action' => 'cdp',
                'cdp_endpoint' => $endpoint,
                'persistence_mode' => 'cdp',
                'sessions_updated' => $updatedCount,
            ]
        );
    }

    /**
     * Toggle headed (visible) browser mode for the current config.
     */
    private function toggleHeaded(User $user, ?string $chatSessionId): CommandResult
    {
        $currentHeaded = (bool) config('runtime.browser.headed', false);

        // Note: Runtime-level toggle would require config writing or env mutation.
        // For now, report the current state and recommend env var or profile approach.
        $session = $this->findActiveSession($user, $chatSessionId);

        if ($session !== null && $session->hasPersistentBrowserProfile()) {
            $autoHeaded = (bool) config('runtime.browser.auto_headed_for_persistent', true);

            return CommandResult::success(
                'Headed mode: **'.($autoHeaded ? 'ON' : 'OFF')."** (auto-enabled for persistent profile)\n".
                'Persistent profiles automatically run headed for login visibility.',
                [
                    'action' => 'headed',
                    'headed' => $autoHeaded,
                    'reason' => 'persistent_profile',
                ]
            );
        }

        return CommandResult::success(
            'Headed mode: **'.($currentHeaded ? 'ON' : 'OFF')."**\n".
            "Set AGENT_BROWSER_HEADED=true in .env to enable globally.\n".
            'Or use `/browser profile <name>` to auto-enable headed mode with a persistent profile.',
            [
                'action' => 'headed',
                'headed' => $currentHeaded,
                'reason' => 'global_config',
            ]
        );
    }

    /**
     * Find the active runtime session for this user/chat.
     *
     * Delegates to RuntimeSessionManager to ensure consistent session selection
     * with the message routing pipeline (AgentRouter / ProcessChatIntent).
     */
    private function findActiveSession(User $user, ?string $chatSessionId): ?RuntimeSession
    {
        $manager = app(RuntimeSessionManager::class);

        if ($chatSessionId !== null && $chatSessionId !== '') {
            return $manager->getActiveSessionForChat($user, $chatSessionId);
        }

        // Fallback: most recent active session for user across all chats
        $sessions = $manager->getActiveSessions($user);

        return $sessions->first();
    }

    /**
     * Update browser settings on ALL active sessions for this user/chat.
     *
     * When multiple queue workers race and create duplicate sessions, slash
     * commands must update ALL of them so whichever session the turn job
     * picks up will have the correct browser configuration.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function updateAllActiveSessionsForChat(User $user, ?string $chatSessionId, array $attributes): int
    {
        if ($chatSessionId === null || $chatSessionId === '') {
            // No chat context — update the most recent session only
            $session = $this->findActiveSession($user, $chatSessionId);
            if ($session !== null) {
                $session->update($attributes);

                return 1;
            }

            return 0;
        }

        return RuntimeSession::where('user_id', $user->id)
            ->where('chat_session_id', $chatSessionId)
            ->where('status', RuntimeSessionStatus::Active)
            ->update($attributes);
    }
}
