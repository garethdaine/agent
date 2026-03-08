<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime;

use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Services\Credentials\CredentialsManager;
use App\Services\Runtime\MessengerRuntimeOrchestrator;
use App\Services\Runtime\SessionProcessManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrchestratorProgressCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_turn_forwards_progress_callback_to_wrapper(): void
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

        $callbackReceived = false;
        $progressCallback = function () use (&$callbackReceived) {
            $callbackReceived = true;
        };

        $sessionProcessManager = $this->createMock(SessionProcessManager::class);
        $sessionProcessManager->method('isWrapperEnabled')->willReturn(true);
        $sessionProcessManager->method('hasActiveWrapper')->willReturn(true);
        $sessionProcessManager->expects($this->once())
            ->method('sendMessage')
            ->with(
                $session->id,
                'Hello with callback',
                $this->anything(),
                $this->isInstanceOf(\Closure::class),
                $this->anything(),
            )
            ->willReturn([
                'status' => 'completed',
                'text' => 'Done',
                'runner_session_id' => 'sess-123',
            ]);

        $this->app->instance(SessionProcessManager::class, $sessionProcessManager);
        $this->app->instance(CredentialsManager::class, $credentialsManager);

        $orchestrator = $this->app->make(MessengerRuntimeOrchestrator::class);
        $result = $orchestrator->executeTurn(
            $session,
            'Hello with callback',
            onProgress: $progressCallback,
        );

        $this->assertEquals('completed', $result['status']);
    }

    public function test_execute_turn_passes_null_callback_when_not_provided(): void
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
        $sessionProcessManager->method('hasActiveWrapper')->willReturn(true);
        $sessionProcessManager->expects($this->once())
            ->method('sendMessage')
            ->with(
                $session->id,
                'Hello no callback',
                $this->anything(),
                null,
                $this->anything(),
            )
            ->willReturn([
                'status' => 'completed',
                'text' => 'Done',
                'runner_session_id' => 'sess-456',
            ]);

        $this->app->instance(SessionProcessManager::class, $sessionProcessManager);
        $this->app->instance(CredentialsManager::class, $credentialsManager);

        $orchestrator = $this->app->make(MessengerRuntimeOrchestrator::class);
        $result = $orchestrator->executeTurn($session, 'Hello no callback');

        $this->assertEquals('completed', $result['status']);
    }
}
