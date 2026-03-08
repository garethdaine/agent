<?php

declare(strict_types=1);

namespace Tests\Unit\Skills;

use App\Models\AgentSkill;
use App\Services\Skills\SkillTelemetryRecorder;
use App\Services\Telemetry\IngestionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SkillTelemetryRecorderTest extends TestCase
{
    use RefreshDatabase;

    private SkillTelemetryRecorder $recorder;

    private IngestionService|Mockery\MockInterface $ingestionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ingestionService = Mockery::mock(IngestionService::class);
        $this->recorder = new SkillTelemetryRecorder($this->ingestionService);
    }

    public function test_records_event_in_telemetry_ledger(): void
    {
        $skill = AgentSkill::factory()->create([
            'invocation_count' => 0,
        ]);

        $this->ingestionService
            ->shouldReceive('ingest')
            ->once()
            ->withArgs(function (array $event) use ($skill) {
                return $event['event_type'] === 'skill.completed'
                    && $event['run_attempt_id'] === 'run-123'
                    && str_contains($event['payload_json'], (string) $skill->id);
            })
            ->andReturn(['id' => 1, 'duplicate' => false]);

        $this->recorder->recordInvocation(
            skill: $skill,
            runAttemptId: 'run-123',
            workflowKey: 'wf-456',
            outcome: 'completed',
            durationMs: 1500,
            tokenUsage: 200,
        );

        // Verify the ingestion was called (mock assertion above)
        $this->assertTrue(true);
    }

    public function test_updates_skill_invocation_count(): void
    {
        $skill = AgentSkill::factory()->create([
            'invocation_count' => 5,
        ]);

        $this->ingestionService
            ->shouldReceive('ingest')
            ->once()
            ->andReturn(['id' => 1, 'duplicate' => false]);

        $this->recorder->recordInvocation(
            skill: $skill,
            runAttemptId: 'run-456',
            workflowKey: 'wf-789',
            outcome: 'invoked',
            durationMs: 100,
            tokenUsage: 50,
        );

        $skill->refresh();
        $this->assertSame(6, $skill->invocation_count);
    }

    public function test_updates_last_invoked_at(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-07 12:00:00'));

        $skill = AgentSkill::factory()->create([
            'last_invoked_at' => null,
        ]);

        $this->ingestionService
            ->shouldReceive('ingest')
            ->once()
            ->andReturn(['id' => 1, 'duplicate' => false]);

        $this->recorder->recordInvocation(
            skill: $skill,
            runAttemptId: 'run-789',
            workflowKey: 'wf-abc',
            outcome: 'completed',
            durationMs: 500,
            tokenUsage: 100,
        );

        $skill->refresh();
        $this->assertNotNull($skill->last_invoked_at);
        $this->assertEquals('2026-03-07 12:00:00', $skill->last_invoked_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_handles_ledger_write_failure_gracefully(): void
    {
        $skill = AgentSkill::factory()->create([
            'invocation_count' => 3,
        ]);

        $this->ingestionService
            ->shouldReceive('ingest')
            ->once()
            ->andThrow(new \RuntimeException('Ledger write failed'));

        $this->recorder->recordInvocation(
            skill: $skill,
            runAttemptId: 'run-fail',
            workflowKey: 'wf-fail',
            outcome: 'failed',
            durationMs: 200,
            tokenUsage: 30,
            failureReason: 'timeout',
        );

        // Skill row should still be updated even if ledger fails
        $skill->refresh();
        $this->assertSame(4, $skill->invocation_count);
    }
}
