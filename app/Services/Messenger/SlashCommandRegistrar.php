<?php

declare(strict_types=1);

namespace App\Services\Messenger;

use App\Models\ConnectorAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Registers Discord slash commands for the agent bot.
 *
 * Single source of truth for all 10 top-level commands. Supports both
 * credentials array (API test) and ConnectorAccount (agent:install).
 * Version tracking triggers re-registration when schema changes.
 */
final class SlashCommandRegistrar
{
    private const COMMAND_VERSION = '2.3.0';

    private const DISCORD_API_BASE = 'https://discord.com/api/v10';

    /**
     * Full Discord slash command schema (10 commands).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCommands(): array
    {
        return [
            [
                'name' => 'jobs',
                'description' => 'Manage agent jobs',
                'options' => [
                    ['name' => 'list', 'description' => 'List your jobs', 'type' => 1],
                    ['name' => 'show', 'description' => 'Show job details', 'type' => 1, 'options' => [
                        ['name' => 'job_id', 'description' => 'Job ID', 'type' => 3, 'required' => true],
                    ]],
                    ['name' => 'create', 'description' => 'Create a new job', 'type' => 1, 'options' => [
                        ['name' => 'name', 'description' => 'Job name', 'type' => 3, 'required' => true],
                    ]],
                    ['name' => 'delete', 'description' => 'Delete a job', 'type' => 1, 'options' => [
                        ['name' => 'job_id', 'description' => 'Job ID', 'type' => 3, 'required' => true],
                    ]],
                    ['name' => 'run', 'description' => 'Run a job immediately', 'type' => 1, 'options' => [
                        ['name' => 'job_id', 'description' => 'Job ID', 'type' => 3, 'required' => true],
                    ]],
                    ['name' => 'enable', 'description' => 'Enable a job', 'type' => 1, 'options' => [
                        ['name' => 'job_id', 'description' => 'Job ID', 'type' => 3, 'required' => true],
                    ]],
                    ['name' => 'disable', 'description' => 'Disable a job', 'type' => 1, 'options' => [
                        ['name' => 'job_id', 'description' => 'Job ID', 'type' => 3, 'required' => true],
                    ]],
                ],
            ],
            [
                'name' => 'runs',
                'description' => 'Manage agent job runs',
                'options' => [
                    ['name' => 'active', 'description' => 'List active runs', 'type' => 1],
                    ['name' => 'history', 'description' => 'Run history', 'type' => 1, 'options' => [
                        ['name' => 'job_id', 'description' => 'Filter by job ID', 'type' => 3, 'required' => false],
                        ['name' => 'limit', 'description' => 'Max results (1-25)', 'type' => 4, 'required' => false, 'min_value' => 1, 'max_value' => 25],
                    ]],
                    ['name' => 'show', 'description' => 'Show run details', 'type' => 1, 'options' => [
                        ['name' => 'run_id', 'description' => 'Run ID', 'type' => 3, 'required' => true],
                    ]],
                    ['name' => 'stop', 'description' => 'Stop a run', 'type' => 1, 'options' => [
                        ['name' => 'run_id', 'description' => 'Run ID', 'type' => 3, 'required' => true],
                    ]],
                    ['name' => 'retry', 'description' => 'Retry a failed run', 'type' => 1, 'options' => [
                        ['name' => 'run_id', 'description' => 'Run ID', 'type' => 3, 'required' => true],
                    ]],
                    ['name' => 'steer', 'description' => 'Steer a running process', 'type' => 1, 'options' => [
                        ['name' => 'run_id', 'description' => 'Run ID', 'type' => 3, 'required' => true],
                        ['name' => 'guidance', 'description' => 'Guidance message', 'type' => 3, 'required' => true],
                    ]],
                ],
            ],
            [
                'name' => 'status',
                'description' => 'Overall system status',
            ],
            [
                'name' => 'sessions',
                'description' => 'Manage runtime sessions',
                'options' => [
                    ['name' => 'list', 'description' => 'List active sessions', 'type' => 1],
                    ['name' => 'stop', 'description' => 'Stop a session', 'type' => 1, 'options' => [
                        ['name' => 'session_id', 'description' => 'Session ID (optional, stops most recent)', 'type' => 3, 'required' => false],
                    ]],
                ],
            ],
            [
                'name' => 'mode',
                'description' => 'View or change execution mode',
                'options' => [
                    ['name' => 'mode', 'description' => 'safe, standard, or full', 'type' => 3, 'required' => false],
                ],
            ],
            [
                'name' => 'approve',
                'description' => 'Approve a pending tool call',
                'options' => [
                    ['name' => 'id', 'description' => 'Tool call ID', 'type' => 3, 'required' => true],
                ],
            ],
            [
                'name' => 'deny',
                'description' => 'Deny a pending tool call',
                'options' => [
                    ['name' => 'id', 'description' => 'Tool call ID', 'type' => 3, 'required' => true],
                    ['name' => 'reason', 'description' => 'Reason (optional)', 'type' => 3, 'required' => false],
                ],
            ],
            [
                'name' => 'browser',
                'description' => 'Browser sidecar control',
                'options' => [
                    ['name' => 'start', 'description' => 'Start browser', 'type' => 1],
                    ['name' => 'stop', 'description' => 'Stop browser', 'type' => 1],
                    ['name' => 'reset', 'description' => 'Reset browser', 'type' => 1],
                    ['name' => 'status', 'description' => 'Browser status', 'type' => 1],
                ],
            ],
            [
                'name' => 'ask',
                'description' => 'Ask a question or run a task',
                'options' => [
                    ['name' => 'question', 'description' => 'Your question or task', 'type' => 3, 'required' => true],
                ],
            ],
            [
                'name' => 'context',
                'description' => 'Context usage (messages, tokens)',
                'options' => [
                    ['name' => 'list', 'description' => 'Short summary', 'type' => 1],
                    ['name' => 'detail', 'description' => 'Include turn details', 'type' => 1],
                    ['name' => 'json', 'description' => 'Machine-readable output', 'type' => 1],
                ],
            ],
            [
                'name' => 'new',
                'description' => 'Start a new runtime session (stops current for this chat)',
            ],
            [
                'name' => 'help',
                'description' => 'List available commands',
            ],
            [
                'name' => 'commands',
                'description' => 'Detailed command list',
            ],
            [
                'name' => 'whoami',
                'description' => 'Show your user and connector id',
            ],
            [
                'name' => 'compact',
                'description' => 'Run compaction (summarize older messages)',
                'options' => [
                    ['name' => 'instructions', 'description' => 'Optional instructions for summarization', 'type' => 3, 'required' => false],
                ],
            ],
            [
                'name' => 'subagents',
                'description' => 'Manage sub-agents for the current session',
                'options' => [
                    ['name' => 'list', 'description' => 'List active and recent sub-agents', 'type' => 1],
                    ['name' => 'spawn', 'description' => 'Spawn a sub-agent with a task', 'type' => 1, 'options' => [
                        ['name' => 'task', 'description' => 'Task description for the sub-agent', 'type' => 3, 'required' => true],
                    ]],
                    ['name' => 'kill', 'description' => 'Stop a running sub-agent', 'type' => 1, 'options' => [
                        ['name' => 'id', 'description' => 'Sub-agent session ID', 'type' => 3, 'required' => true],
                    ]],
                ],
            ],
            [
                'name' => 'progress',
                'description' => 'Show live progress for the current running turn',
            ],
        ];
    }

    /**
     * Register slash commands with Discord.
     *
     * @param  ConnectorAccount|array{bot_token: string, application_id: string}  $accountOrCredentials
     */
    public function register(ConnectorAccount|array $accountOrCredentials): RegistrationResult
    {
        $credentials = $accountOrCredentials instanceof ConnectorAccount
            ? ($accountOrCredentials->credentials ?? [])
            : $accountOrCredentials;
        $account = $accountOrCredentials instanceof ConnectorAccount ? $accountOrCredentials : null;

        $applicationId = trim((string) ($credentials['application_id'] ?? ''));
        $botToken = trim((string) ($credentials['bot_token'] ?? ''));

        if ($applicationId === '' || $botToken === '') {
            return RegistrationResult::failure('Missing application_id or bot_token');
        }

        $url = self::DISCORD_API_BASE.'/applications/'.$applicationId.'/commands';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bot '.$botToken,
                'Content-Type' => 'application/json',
            ])->timeout(30)->put($url, $this->getCommands());

            if ($response->successful()) {
                $commands = $response->json();
                if ($account !== null) {
                    $this->updateConnectorMetadata($account, $commands);
                }
                Log::info('Discord slash commands registered successfully', [
                    'account_id' => $account?->id,
                    'command_count' => is_array($commands) ? count($commands) : 0,
                ]);

                return RegistrationResult::success(
                    'Slash commands registered successfully',
                    is_array($commands) ? count($commands) : 0,
                    is_array($commands) ? $commands : []
                );
            }

            $error = $this->parseErrorResponse($response);
            if ($account !== null) {
                Log::warning('Discord slash command registration failed', [
                    'account_id' => $account->id,
                    'status' => $response->status(),
                    'error' => $error,
                ]);
            }

            return RegistrationResult::failure($error);
        } catch (Throwable $e) {
            if ($account !== null) {
                Log::error('Discord slash command registration exception', [
                    'account_id' => $account->id,
                    'exception' => $e->getMessage(),
                ]);
            }

            return RegistrationResult::failure('Slash command registration failed: '.$e->getMessage());
        }
    }

    public function needsUpdate(ConnectorAccount $account): bool
    {
        $storedVersion = $account->config['slash_command_version'] ?? null;

        return $storedVersion !== self::COMMAND_VERSION;
    }

    public function getVersion(): string
    {
        return self::COMMAND_VERSION;
    }

    /**
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
     * @param  \Illuminate\Http\Client\Response  $response
     */
    private function parseErrorResponse($response): string
    {
        $status = $response->status();
        $data = $response->json() ?? [];
        $message = (string) ($data['message'] ?? 'Unknown error');

        return match ($status) {
            401 => 'Unauthorized: '.$message.'. Check that your bot token is valid.',
            403 => 'Forbidden: '.$message.'. Ensure your bot has the applications.commands scope.',
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
