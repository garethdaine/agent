<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Events\Office\AgentActivityChanged;
use App\Events\RunEventsAvailable;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\AgentRunEvent;
use App\Models\User;
use App\Support\Agent\EventBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EventBroadcasterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AgentJob $job;

    private AgentJobRun $run;

    private EventBroadcaster $broadcaster;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Queue::fake();
        Cache::flush();

        $this->user = User::factory()->create();
        $this->job = AgentJob::factory()->for($this->user)->create();
        $this->run = AgentJobRun::factory()->for($this->job, 'job')->create([
            'user_id' => $this->user->id,
            'status' => 'running',
        ]);

        $this->broadcaster = new EventBroadcaster($this->run);
    }

    public function test_broadcasts_events_available(): void
    {
        $this->broadcaster->broadcastEventsAvailable(1);

        Event::assertDispatched(RunEventsAvailable::class, function (RunEventsAvailable $event) {
            return $event->runId === $this->run->id && $event->latestSequence === 1;
        });
    }

    public function test_rate_limits_broadcasts(): void
    {
        $this->broadcaster->broadcastEventsAvailable(1);
        $this->broadcaster->broadcastEventsAvailable(2);

        Event::assertDispatchedTimes(RunEventsAvailable::class, 1);
    }

    public function test_broadcasts_escalation(): void
    {
        $this->broadcaster->broadcastEscalation('test_reason', 'Test summary');

        Event::assertDispatched(AgentActivityChanged::class, function (AgentActivityChanged $event) {
            return $event->userId === (int) $this->user->id
                && $event->eventType === 'agent.escalation';
        });
    }

    public function test_broadcasts_office_snippet(): void
    {
        AgentRunEvent::query()->create([
            'agent_job_run_id' => $this->run->id,
            'event_type' => 'stdout',
            'sequence' => 1,
            'payload' => 'Agent is working on the task now',
            'event_ts' => now(),
        ]);

        $this->broadcaster->broadcastEventsAvailable(1);

        Event::assertDispatched(AgentActivityChanged::class, function (AgentActivityChanged $event) {
            return $event->eventType === 'agent.output';
        });
    }

    public function test_skips_snippet_for_json_only_events(): void
    {
        AgentRunEvent::query()->create([
            'agent_job_run_id' => $this->run->id,
            'event_type' => 'stdout',
            'sequence' => 1,
            'payload' => '{"type":"ping"}',
            'event_ts' => now(),
        ]);

        $this->broadcaster->broadcastEventsAvailable(1);

        Event::assertNotDispatched(AgentActivityChanged::class, function (AgentActivityChanged $event) {
            return $event->eventType === 'agent.output';
        });
    }
}
