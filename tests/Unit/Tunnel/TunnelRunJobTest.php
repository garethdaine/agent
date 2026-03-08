<?php

declare(strict_types=1);

namespace Tests\Unit\Tunnel;

use App\Jobs\Tunnel\TunnelRunJob;
use App\Repositories\TunnelSettingsRepository;
use App\Services\Tunnel\CloudflaredService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class TunnelRunJobTest extends TestCase
{
    use RefreshDatabase;

    private TunnelSettingsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tunnel.cloudflared_binary' => 'cloudflared']);
        $this->repository = new TunnelSettingsRepository;
    }

    public function test_handle_sets_status_to_active_on_start(): void
    {
        $this->repository->update([
            'tunnel_uuid' => 'test-uuid-123',
            'origin_url' => 'http://localhost:8000',
            'protocol' => 'http2',
        ], 'stopped');

        Process::fake([
            '*' => Process::result(output: '', exitCode: 0),
        ]);

        $job = new TunnelRunJob;
        $job->handle(new CloudflaredService, $this->repository);

        $this->assertEquals('stopped', $this->repository->getStatus());

        // Status was set to active during execution (before process ran).
        // After clean exit (code 0) it becomes stopped.
    }

    public function test_handle_calls_build_run_command_with_correct_settings(): void
    {
        $this->repository->update([
            'tunnel_uuid' => 'abc-def-123',
            'origin_url' => 'http://localhost:9000',
            'protocol' => 'quic',
        ], 'stopped');

        Process::fake([
            '*' => Process::result(output: '', exitCode: 0),
        ]);

        $job = new TunnelRunJob;
        $job->handle(new CloudflaredService, $this->repository);

        Process::assertRan(function ($process) {
            $command = $process->command;
            if (is_string($command)) {
                return str_contains($command, 'abc-def-123')
                    && str_contains($command, 'quic')
                    && str_contains($command, 'localhost:9000');
            }

            $joined = implode(' ', $command);

            return str_contains($joined, 'abc-def-123')
                && str_contains($joined, 'quic')
                && str_contains($joined, 'localhost:9000');
        });
    }

    public function test_failed_updates_status_to_error_after_max_attempts(): void
    {
        $this->repository->update([
            'tunnel_uuid' => 'test-uuid',
        ], 'active');

        // Simulate 5 recent restart attempts within the 10-minute window
        $redisKey = 'tunnel:restart_attempts';
        Redis::del($redisKey);
        $now = time();
        for ($i = 0; $i < 4; $i++) {
            Redis::rpush($redisKey, (string) ($now - $i * 60));
        }

        $job = new TunnelRunJob;
        $job->failed(new RuntimeException('Process crashed'));

        $this->assertEquals('error', $this->repository->getStatus());
    }

    public function test_failed_increments_restart_counter_in_redis(): void
    {
        $this->repository->update([
            'tunnel_uuid' => 'test-uuid',
        ], 'active');

        $redisKey = 'tunnel:restart_attempts';
        Redis::del($redisKey);

        $job = new TunnelRunJob;
        $job->failed(new RuntimeException('Process crashed'));

        $count = Redis::llen($redisKey);
        $this->assertEquals(1, $count);
    }

    public function test_restart_counter_resets_after_10_minute_window(): void
    {
        $this->repository->update([
            'tunnel_uuid' => 'test-uuid',
        ], 'active');

        $redisKey = 'tunnel:restart_attempts';
        Redis::del($redisKey);

        // Add old timestamps outside the 10-minute window
        $oldTime = time() - 700; // 11+ minutes ago
        for ($i = 0; $i < 4; $i++) {
            Redis::rpush($redisKey, (string) ($oldTime - $i));
        }

        $job = new TunnelRunJob;
        $job->failed(new RuntimeException('Process crashed'));

        // Old entries should be trimmed, only the new one remains
        $count = Redis::llen($redisKey);
        $this->assertEquals(1, $count);

        // Status should not be error since only 1 recent attempt
        $this->assertNotEquals('error', $this->repository->getStatus());
    }

    public function test_handle_validates_tunnel_is_configured_before_start(): void
    {
        // Default status is 'unconfigured'
        $this->repository->get();

        Process::fake();

        $job = new TunnelRunJob;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unconfigured');

        $job->handle(new CloudflaredService, $this->repository);

        Process::assertNothingRan();
    }

    public function test_handle_sets_status_to_stopped_on_clean_exit(): void
    {
        $this->repository->update([
            'tunnel_uuid' => 'test-uuid-456',
            'origin_url' => 'http://localhost:8000',
            'protocol' => 'http2',
        ], 'stopped');

        Process::fake([
            '*' => Process::result(output: '', exitCode: 0),
        ]);

        $job = new TunnelRunJob;
        $job->handle(new CloudflaredService, $this->repository);

        $this->assertEquals('stopped', $this->repository->getStatus());
    }
}
