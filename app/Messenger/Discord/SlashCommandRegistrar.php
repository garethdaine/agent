<?php

declare(strict_types=1);

namespace App\Messenger\Discord;

use App\Models\ConnectorAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Registers Discord slash commands for the agent.
 *
 * Assumptions:
 * - Discord connector already configured with bot_token and application_id
 * - Commands registered globally (up to 1 hour propagation)
 * - Bulk overwrite via PUT /applications/{id}/commands
 *
 * Command Schema:
 * - /agent - Primary command with subcommands: run, status, cancel, list
 *
 * Version Tracking:
 * - COMMAND_VERSION constant tracks schema version
 * - Version stored in connector config['slash_command_version']
 * - needsUpdate() compares versions to trigger re-registration
 *
 * Error Handling:
 * - 401: Invalid bot token
 * - 403: Missing applications.commands scope
 * - 429: Rate limited, includes retry_after
 * - 5xx: Discord server error
 *
 * Failure Modes:
 * - Malicious caller: Cannot inject commands since schema is hardcoded
 * - Tired maintainer: Clear error messages with actionable guidance
 *
 * Known Limitations:
 * - Global commands take up to 1 hour to propagate
 * - Guild commands would propagate instantly but require guild IDs
 * - No retry logic - caller should handle retries
 */
class SlashCommandRegistrar
{
    /**
     * Command schema version - increment when schema changes.
     * Triggers re-registration when version differs from stored version.
     */
    private const COMMAND_VERSION = '1.0.0';

    /**
     * Discord API v10 base URL.
     */
    private const DISCORD_API_BASE = 'https://discord.com/api/v10';

    /**
     * Get the slash command schema for registration.
     *
     * Discord Application Command Types:
     * - 1: CHAT_INPUT (slash commands)
     * - 2: USER (context menu)
     * - 3: MESSAGE (context menu)
     *
     * Option Types:
     * - 1: SUB_COMMAND
     * - 2: SUB_COMMAND_GROUP
     * - 3: STRING
     * - 4: INTEGER
     * - 5: BOOLEAN
     * - 6: USER
     * - 7: CHANNEL
     * - 8: ROLE
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCommandSchema(): array
    {
        return [
            [
                'name' => 'agent',
                'description' => 'Interact with the agent',
                'type' => 1, // CHAT_INPUT
                'options' => [
                    [
                        'name' => 'run',
                        'description' => 'Run a job or command',
                        'type' => 1, // SUB_COMMAND
                        'options' => [
                            [
                                'name' => 'command',
                                'description' => 'The command or task to run',
                                'type' => 3, // STRING
                                'required' => true,
                            ],
                        ],
                    ],
                    [
                        'name' => 'status',
                        'description' => 'Check job status',
                        'type' => 1, // SUB_COMMAND
                        'options' => [
                            [
                                'name' => 'job_id',
                                'description' => 'Optional job ID to check (defaults to latest)',
                                'type' => 3, // STRING
                                'required' => false,
                            ],
                        ],
                    ],
                    [
                        'name' => 'cancel',
                        'description' => 'Cancel a running job',
                        'type' => 1, // SUB_COMMAND
                        'options' => [
                            [
                                'name' => 'job_id',
                                'description' => 'The job ID to cancel',
                                'type' => 3, // STRING
                                'required' => true,
                            ],
                        ],
                    ],
                    [
                        'name' => 'list',
                        'description' => 'List recent jobs',
                        'type' => 1, // SUB_COMMAND
                        'options' => [
                            [
                                'name' => 'limit',
                                'description' => 'Number of jobs to show (default: 5)',
                                'type' => 4, // INTEGER
                                'required' => false,
                                'min_value' => 1,
                                'max_value' => 25,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Register slash commands with Discord.
     *
     * Uses PUT /applications/{id}/commands for bulk overwrite.
     * Stores version and command IDs in connector config.
     */
    public function register(ConnectorAccount $account): RegistrationResult
    {
        $credentials = $account->credentials;
        $applicationId = $credentials['application_id'] ?? null;
        $botToken = $credentials['bot_token'] ?? null;

        if (! $applicationId || ! $botToken) {
            return RegistrationResult::failure(
                'Missing required credentials: application_id and bot_token are required'
            );
        }

        $url = sprintf(
            '%s/applications/%s/commands',
            self::DISCORD_API_BASE,
            $applicationId
        );

        try {
            $response = Http::withHeaders([
                'Authorization' => sprintf('Bot %s', $botToken),
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->put($url, $this->getCommandSchema());

            if ($response->successful()) {
                $commands = $response->json();

                // Update connector config with version and command IDs
                $this->updateConnectorMetadata($account, $commands);

                Log::info('Discord slash commands registered successfully', [
                    'account_id' => $account->id,
                    'command_count' => count($commands),
                ]);

                return RegistrationResult::success($commands);
            }

            $error = $this->parseErrorResponse($response);

            Log::warning('Discord slash command registration failed', [
                'account_id' => $account->id,
                'status' => $response->status(),
                'error' => $error,
            ]);

            return RegistrationResult::failure($error);
        } catch (\Throwable $e) {
            Log::error('Discord slash command registration exception', [
                'account_id' => $account->id,
                'exception' => $e->getMessage(),
            ]);

            return RegistrationResult::failure(
                sprintf('Registration failed: %s', $e->getMessage())
            );
        }
    }

    /**
     * Check if slash commands need to be re-registered.
     *
     * Returns true if:
     * - No version stored (never registered)
     * - Stored version differs from current COMMAND_VERSION
     */
    public function needsUpdate(ConnectorAccount $account): bool
    {
        $storedVersion = $account->config['slash_command_version'] ?? null;

        return $storedVersion !== self::COMMAND_VERSION;
    }

    /**
     * Get the current command version.
     */
    public function getVersion(): string
    {
        return self::COMMAND_VERSION;
    }

    /**
     * Update connector metadata with registration result.
     *
     * @param  array<int, array<string, mixed>>  $commands
     */
    private function updateConnectorMetadata(ConnectorAccount $account, array $commands): void
    {
        $commandIds = collect($commands)
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        $account->update([
            'config' => array_merge($account->config ?? [], [
                'slash_command_version' => self::COMMAND_VERSION,
                'slash_command_ids' => $commandIds,
                'slash_commands_registered_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Parse Discord API error response into actionable message.
     *
     * @param  \Illuminate\Http\Client\Response  $response
     */
    private function parseErrorResponse($response): string
    {
        $status = $response->status();
        $data = $response->json() ?? [];
        $message = $data['message'] ?? 'Unknown error';

        return match ($status) {
            401 => sprintf('Unauthorized: %s. Check that your bot token is valid.', $message),
            403 => sprintf('Forbidden: %s. Ensure your bot has the applications.commands scope.', $message),
            429 => sprintf(
                'Rate limited: %s. Retry after %d seconds.',
                $message,
                (int) ($data['retry_after'] ?? 5)
            ),
            500, 502, 503, 504 => sprintf('Discord server error (%d): %s. Try again later.', $status, $message),
            default => sprintf('Error (%d): %s', $status, $message),
        };
    }
}
