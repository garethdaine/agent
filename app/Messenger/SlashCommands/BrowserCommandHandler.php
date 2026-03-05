<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Models\ConnectorAccount;
use App\Models\User;
use App\Services\Runtime\RuntimeSessionManager;

/**
 * Handler for the /browser slash command.
 *
 * Controls the browser sidecar for the user's runtime sessions.
 * /browser start creates (or resumes) a runtime session for this chat so
 * subsequent messages are handled by the runtime with the browser tool available.
 *
 * Usage:
 * - /browser start - Start the browser sidecar and attach runtime to this chat
 * - /browser stop - Stop the browser sidecar
 * - /browser reset - Reset the browser sidecar (stop and start)
 * - /browser status - Check browser sidecar status
 */
final class BrowserCommandHandler implements SlashCommandHandlerInterface
{
    private const VALID_ACTIONS = ['start', 'stop', 'reset', 'status'];

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
            'status' => $this->browserStatus($user),
            default => $this->showUsage(),
        };
    }

    private function showUsage(): CommandResult
    {
        return CommandResult::failure(
            "Usage: /browser <start|stop|reset|status>\n".
            "  start  - Start the browser sidecar\n".
            "  stop   - Stop the browser sidecar\n".
            "  reset  - Reset the browser sidecar\n".
            '  status - Check browser status'
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
            "Browser sidecar is ready. You can ask me to use the browser (e.g. “navigate to x.com” or “check example.com”) and I’ll use the browser tool.",
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

    private function browserStatus(User $user): CommandResult
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

        // Note: Actual status check will be handled by BrowserSidecarManager
        // This handler provides the command interface; actual implementation pending Phase 5

        return CommandResult::success(
            "Browser sidecar configured\nBinary: {$browserPath}\nDefault persistence: {$defaultPersistence}",
            [
                'configured' => true,
                'binary' => $browserPath,
                'default_persistence' => $defaultPersistence,
                'status' => 'unknown', // Will be populated by BrowserSidecarManager
            ]
        );
    }
}
