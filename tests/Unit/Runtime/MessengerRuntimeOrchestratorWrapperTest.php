<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime;

use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Services\Credentials\CredentialsManager;
use App\Services\Runtime\CliRuntimeExecutor;
use App\Services\Runtime\MessengerRuntimeOrchestrator;
use App\Services\Runtime\SessionProcessManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessengerRuntimeOrchestratorWrapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_turn_routes_through_wrapper_when_enabled(): void
    {
        config([
            'runtime.use_cli' => true,
            'runtime.cli.wrapper_enabled' => true,
            'agent.runner_executables' => ['claude' => '/usr/local/bin/claude'],
            'agent.allowed_working_directory_bases' => ['/tmp'],
        ]);

        $user = User::factory()->create();
        $session = RuntimeSession::create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
            'started_at' => now(),
        ]);

        $credentialsManager = $this->createMock(CredentialsManager::class);
        $credentialsManager->method('get')->willReturn('test-api-key');

        $sessionProcessManager = $this->createMock(SessionProcessManager::class);
        $sessionProcessManager->method('isWrapperEnabled')->willReturn(true);
        $sessionProcessManager->method('hasActiveWrapper')->willReturn(false);
        $sessionProcessManager->method('startWrapper')->willReturn(true);
        $sessionProcessManager->method('sendMessage')->willReturn([
            'status' => 'completed',
            'text' => 'Wrapper response',
            'runner_session_id' => 'session-abc',
        ]);

        $this->app->instance(SessionProcessManager::class, $sessionProcessManager);
        $this->app->instance(CredentialsManager::class, $credentialsManager);

        $orchestrator = $this->app->make(MessengerRuntimeOrchestrator::class);
        $result = $orchestrator->executeTurn($session, 'Hello via wrapper');

        $this->assertEquals('completed', $result['status']);
        $this->assertEquals('Wrapper response', $result['text']);
    }

    public function test_execute_turn_falls_back_to_executor_when_wrapper_disabled(): void
    {
        config(['runtime.use_cli' => true, 'runtime.cli.wrapper_enabled' => false]);

        $user = User::factory()->create();
        $session = RuntimeSession::create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
            'started_at' => now(),
        ]);

        $sessionProcessManager = $this->createMock(SessionProcessManager::class);
        $sessionProcessManager->method('isWrapperEnabled')->willReturn(false);
        $sessionProcessManager->method('getRunnerSessionId')->willReturn(null);

        $cliExecutor = $this->createMock(CliRuntimeExecutor::class);
        $cliExecutor->method('executeTurn')->willReturn([
            'status' => 'completed',
            'text' => 'Direct executor response',
        ]);

        $this->app->instance(SessionProcessManager::class, $sessionProcessManager);
        $this->app->instance(CliRuntimeExecutor::class, $cliExecutor);

        $orchestrator = $this->app->make(MessengerRuntimeOrchestrator::class);
        $result = $orchestrator->executeTurn($session, 'Hello via executor');

        $this->assertEquals('completed', $result['status']);
        $this->assertEquals('Direct executor response', $result['text']);
    }

    public function test_execute_turn_returns_failed_when_wrapper_cannot_start(): void
    {
        config(['runtime.use_cli' => true, 'runtime.cli.wrapper_enabled' => true]);

        $user = User::factory()->create();
        $session = RuntimeSession::create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
            'started_at' => now(),
        ]);

        $credentialsManager = $this->createMock(CredentialsManager::class);
        $credentialsManager->method('get')->willReturn('test-api-key');

        $sessionProcessManager = $this->createMock(SessionProcessManager::class);
        $sessionProcessManager->method('isWrapperEnabled')->willReturn(true);
        $sessionProcessManager->method('hasActiveWrapper')->willReturn(false);
        $sessionProcessManager->method('startWrapper')->willReturn(false);

        $this->app->instance(SessionProcessManager::class, $sessionProcessManager);
        $this->app->instance(CredentialsManager::class, $credentialsManager);

        $orchestrator = $this->app->make(MessengerRuntimeOrchestrator::class);
        $result = $orchestrator->executeTurn($session, 'Hello');

        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('Failed to start wrapper', $result['error']);
    }
}
