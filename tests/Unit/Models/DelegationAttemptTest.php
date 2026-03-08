<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\AgentJobRun;
use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegationAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_constants_defined(): void
    {
        $this->assertEquals('running', DelegationAttempt::STATUS_RUNNING);
        $this->assertEquals('succeeded', DelegationAttempt::STATUS_SUCCEEDED);
        $this->assertEquals('failed', DelegationAttempt::STATUS_FAILED);
    }

    public function test_belongs_to_task(): void
    {
        $task = DelegationTask::factory()->create();
        $attempt = DelegationAttempt::factory()->create(['delegation_task_id' => $task->id]);

        $this->assertInstanceOf(DelegationTask::class, $attempt->task);
        $this->assertEquals($task->id, $attempt->task->id);
    }

    public function test_belongs_to_profile(): void
    {
        $profile = DelegateeProfile::factory()->create();
        $attempt = DelegationAttempt::factory()->create(['delegatee_profile_id' => $profile->id]);

        $this->assertInstanceOf(DelegateeProfile::class, $attempt->profile);
        $this->assertEquals($profile->id, $attempt->profile->id);
    }

    public function test_belongs_to_agent_job_run_nullable(): void
    {
        $task = DelegationTask::factory()->create();
        $profile = DelegateeProfile::factory()->create();
        $run = AgentJobRun::factory()->create();

        $attemptWithRun = DelegationAttempt::factory()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile->id,
            'agent_job_run_id' => $run->id,
        ]);
        $attemptWithoutRun = DelegationAttempt::factory()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile->id,
            'agent_job_run_id' => null,
        ]);

        $this->assertInstanceOf(AgentJobRun::class, $attemptWithRun->agentJobRun);
        $this->assertEquals($run->id, $attemptWithRun->agentJobRun->id);
        $this->assertNull($attemptWithoutRun->agentJobRun);
    }

    public function test_casts_timestamps(): void
    {
        $now = now();
        $attempt = DelegationAttempt::factory()->create([
            'started_at' => $now,
            'finished_at' => $now->addMinutes(10),
        ]);

        $attempt->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $attempt->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $attempt->finished_at);
    }

    public function test_casts_duration_ms(): void
    {
        $attempt = DelegationAttempt::factory()->create(['duration_ms' => 60000]);

        $attempt->refresh();

        $this->assertIsInt($attempt->duration_ms);
        $this->assertEquals(60000, $attempt->duration_ms);
    }

    public function test_casts_attempt_number(): void
    {
        $attempt = DelegationAttempt::factory()->create(['attempt_number' => 3]);

        $attempt->refresh();

        $this->assertIsInt($attempt->attempt_number);
        $this->assertEquals(3, $attempt->attempt_number);
    }

    public function test_casts_metadata_json(): void
    {
        $attempt = DelegationAttempt::factory()->create([
            'metadata_json' => ['retry_reason' => 'transient_timeout', 'previous_attempt_id' => 42],
        ]);

        $attempt->refresh();

        $this->assertIsArray($attempt->metadata_json);
        $this->assertEquals('transient_timeout', $attempt->metadata_json['retry_reason']);
        $this->assertEquals(42, $attempt->metadata_json['previous_attempt_id']);
    }
}
