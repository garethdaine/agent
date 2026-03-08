<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;

/**
 * Handler for the /mode slash command.
 *
 * Changes the execution mode for the user's active session.
 *
 * Usage:
 * - /mode safe - Switch to safe mode (read-only operations)
 * - /mode standard - Switch to standard mode (approve mutations)
 * - /mode full - Switch to full mode (approve all external calls)
 */
final class ModeCommandHandler implements SlashCommandHandlerInterface
{
    /**
     * @param  array<int, string>  $args
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        if (empty($args)) {
            return $this->showCurrentMode($user);
        }

        $requestedMode = strtolower($args[0]);

        $mode = RuntimeMode::tryFrom($requestedMode);
        if ($mode === null) {
            $validModes = implode(', ', array_map(fn ($m) => $m->value, RuntimeMode::cases()));

            return CommandResult::failure("Invalid mode: {$requestedMode}. Valid modes: {$validModes}");
        }

        return $this->setMode($user, $mode);
    }

    private function showCurrentMode(User $user): CommandResult
    {
        $session = RuntimeSession::where('user_id', $user->id)
            ->where('status', RuntimeSessionStatus::Active)
            ->orderBy('started_at', 'desc')
            ->first();

        if ($session === null) {
            $defaultMode = config('runtime.default_mode', 'safe');

            return CommandResult::success(
                "No active session. Default mode: {$defaultMode}",
                ['mode' => $defaultMode, 'session_active' => false]
            );
        }

        return CommandResult::success(
            "Current mode: {$session->mode->value}", // @phpstan-ignore property.nonObject
            ['mode' => $session->mode->value, 'session_id' => $session->id] // @phpstan-ignore property.nonObject
        );
    }

    private function setMode(User $user, RuntimeMode $mode): CommandResult
    {
        $session = RuntimeSession::where('user_id', $user->id)
            ->where('status', RuntimeSessionStatus::Active)
            ->orderBy('started_at', 'desc')
            ->first();

        if ($session === null) {
            return CommandResult::failure('No active session to change mode for');
        }

        $previousMode = $session->mode;

        if ($previousMode === $mode) { // @phpstan-ignore identical.alwaysFalse
            return CommandResult::success(
                "Mode is already set to {$mode->value}",
                ['mode' => $mode->value, 'session_id' => $session->id]
            );
        }

        $session->update(['mode' => $mode]);

        return CommandResult::success(
            "Mode changed from {$previousMode->value} to {$mode->value}", // @phpstan-ignore property.nonObject
            [
                'mode' => $mode->value,
                'previous_mode' => $previousMode->value, // @phpstan-ignore property.nonObject
                'session_id' => $session->id,
            ]
        );
    }
}
