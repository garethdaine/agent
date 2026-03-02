<?php

namespace App\Services\Messenger;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Registers Discord slash commands for the agent bot.
 *
 * Slash commands allow users to interact with the agent via Discord's
 * native command interface (e.g., /jobs list, /runs active).
 */
class SlashCommandRegistrar
{
    /**
     * Discord slash command definitions for agent operations.
     *
     * @var array<int, array{name: string, description: string, options: array}>
     */
    private array $commands = [
        [
            'name' => 'jobs',
            'description' => 'Manage agent jobs',
            'options' => [
                [
                    'name' => 'list',
                    'description' => 'List your jobs',
                    'type' => 1, // SUB_COMMAND
                ],
                [
                    'name' => 'create',
                    'description' => 'Create a new job',
                    'type' => 1,
                    'options' => [
                        [
                            'name' => 'name',
                            'description' => 'Job name',
                            'type' => 3, // STRING
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'name' => 'run',
                    'description' => 'Run a job immediately',
                    'type' => 1,
                    'options' => [
                        [
                            'name' => 'job_id',
                            'description' => 'Job ID to run',
                            'type' => 4, // INTEGER
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ],
        [
            'name' => 'runs',
            'description' => 'Manage job runs',
            'options' => [
                [
                    'name' => 'active',
                    'description' => 'List active runs',
                    'type' => 1,
                ],
                [
                    'name' => 'stop',
                    'description' => 'Stop a running job',
                    'type' => 1,
                    'options' => [
                        [
                            'name' => 'run_id',
                            'description' => 'Run ID to stop',
                            'type' => 4, // INTEGER
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ],
    ];

    /**
     * Register slash commands with Discord for the specified application.
     *
     * @param  array{bot_token: string, application_id: string}  $credentials
     */
    public function register(array $credentials): RegistrationResult
    {
        $applicationId = trim((string) ($credentials['application_id'] ?? ''));
        $botToken = trim((string) ($credentials['bot_token'] ?? ''));

        if ($applicationId === '' || $botToken === '') {
            return RegistrationResult::failure('Missing application_id or bot_token');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bot '.$botToken,
            ])->timeout(15)->put(
                "https://discord.com/api/v10/applications/{$applicationId}/commands",
                $this->commands
            );

            if ($response->successful()) {
                return RegistrationResult::success(
                    'Slash commands registered successfully',
                    count($this->commands)
                );
            }

            $error = $response->json('message') ?? $response->body();

            return RegistrationResult::failure(
                'Failed to register slash commands: '.$error
            );
        } catch (Throwable $e) {
            return RegistrationResult::failure(
                'Slash command registration failed: '.$e->getMessage()
            );
        }
    }

    /**
     * Get the list of commands that will be registered.
     *
     * @return array<int, array{name: string, description: string, options: array}>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }
}
