<?php

namespace Tests\Unit\Runtime;

use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Jobs\Runtime\ProcessRuntimeTurnJob;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Services\Runtime\SubAgentSpawner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SubAgentSpawnerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ConnectorAccount $account;

    private ChatSession $chatSession;

    private RuntimeSession $parentSession;

    protected function setUp(): void
    {
        parent::setUp();

        config(['runtime.subagents.enabled' => true]);

        $this->user = User::factory()->create();
        $this->account = ConnectorAccount::factory()->create(['provider' => 'discord']);
        $this->chatSession = ChatSession::factory()->create([
            'connector_account_id' => $this->account->id,
        ]);
        $this->parentSession = RuntimeSession::create([
            'user_id' => $this->user->id,
            'chat_session_id' => $this->chatSession->id,
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
            'spawn_depth' => 0,
            'started_at' => now(),
        ]);
    }

    public function test_spawn_creates_child_session(): void
    {
        Bus::fake([ProcessRuntimeTurnJob::class]);

        $spawner = app(SubAgentSpawner::class);
        $result = $spawner->spawn($this->parentSession, 'Write a cover letter');

        $this->assertEquals('spawned', $result['status']);
        $this->assertArrayHasKey('child_session_id', $result);

        $child = RuntimeSession::find($result['child_session_id']);
        $this->assertNotNull($child);
        $this->assertEquals($this->parentSession->id, $child->parent_session_id);
        $this->assertEquals(1, $child->spawn_depth);
        $this->assertEquals($this->user->id, $child->user_id);
        $this->assertEquals($this->chatSession->id, $child->chat_session_id);
        $this->assertEquals(RuntimeSessionStatus::Active, $child->status);
    }

    public function test_spawn_dispatches_turn_job_on_subagent_queue(): void
    {
        Bus::fake([ProcessRuntimeTurnJob::class]);

        $spawner = app(SubAgentSpawner::class);
        $spawner->spawn($this->parentSession, 'Write a cover letter');

        Bus::assertDispatched(ProcessRuntimeTurnJob::class, function ($job) {
            return $job->queue === config('runtime.subagents.queue', 'subagent')
                && $job->userMessage === 'Write a cover letter';
        });
    }

    public function test_spawn_sets_label_as_child_title(): void
    {
        Bus::fake([ProcessRuntimeTurnJob::class]);

        $spawner = app(SubAgentSpawner::class);
        $result = $spawner->spawn($this->parentSession, 'Write a cover letter', label: 'Cover Letter Writer');

        $child = RuntimeSession::find($result['child_session_id']);
        $this->assertEquals('Cover Letter Writer', $child->title);
    }

    public function test_spawn_rejected_when_disabled(): void
    {
        config(['runtime.subagents.enabled' => false]);

        $spawner = app(SubAgentSpawner::class);
        $result = $spawner->spawn($this->parentSession, 'Write a cover letter');

        $this->assertEquals('rejected', $result['status']);
        $this->assertStringContainsString('disabled', $result['reason']);
    }

    public function test_spawn_rejected_when_max_depth_exceeded(): void
    {
        Bus::fake([ProcessRuntimeTurnJob::class]);
        config(['runtime.subagents.max_spawn_depth' => 1]);

        $this->parentSession->update(['spawn_depth' => 1]);

        $spawner = app(SubAgentSpawner::class);
        $result = $spawner->spawn($this->parentSession, 'Nested task');

        $this->assertEquals('rejected', $result['status']);
        $this->assertStringContainsString('depth', $result['reason']);
    }

    public function test_spawn_rejected_when_max_concurrent_exceeded(): void
    {
        Bus::fake([ProcessRuntimeTurnJob::class]);
        config(['runtime.subagents.max_concurrent_per_session' => 2]);

        for ($i = 0; $i < 2; $i++) {
            RuntimeSession::create([
                'user_id' => $this->user->id,
                'parent_session_id' => $this->parentSession->id,
                'spawn_depth' => 1,
                'status' => RuntimeSessionStatus::Active,
                'mode' => RuntimeMode::Safe,
                'started_at' => now(),
            ]);
        }

        $spawner = app(SubAgentSpawner::class);
        $result = $spawner->spawn($this->parentSession, 'Another task');

        $this->assertEquals('rejected', $result['status']);
        $this->assertStringContainsString('concurrent', $result['reason']);
    }

    public function test_spawn_allows_when_ended_children_dont_count(): void
    {
        Bus::fake([ProcessRuntimeTurnJob::class]);
        config(['runtime.subagents.max_concurrent_per_session' => 2]);

        for ($i = 0; $i < 2; $i++) {
            RuntimeSession::create([
                'user_id' => $this->user->id,
                'parent_session_id' => $this->parentSession->id,
                'spawn_depth' => 1,
                'status' => RuntimeSessionStatus::Stopped,
                'mode' => RuntimeMode::Safe,
                'started_at' => now(),
                'ended_at' => now(),
            ]);
        }

        $spawner = app(SubAgentSpawner::class);
        $result = $spawner->spawn($this->parentSession, 'New task');

        $this->assertEquals('spawned', $result['status']);
    }
}
