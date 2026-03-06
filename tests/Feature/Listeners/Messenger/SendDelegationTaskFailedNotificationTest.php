<?php

namespace Tests\Feature\Listeners\Messenger;

use App\DTOs\Messenger\SystemNotificationPayload;
use App\Events\DelegationTaskVerified;
use App\Listeners\Messenger\SendDelegationTaskFailedNotification;
use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\User;
use App\Services\Messenger\SystemNotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendDelegationTaskFailedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_sends_notification_on_task_verification_failure(): void
    {
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
        ]);

        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile->id,
        ]);

        $dispatcher = Mockery::mock(SystemNotificationDispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (SystemNotificationPayload $payload) use ($task) {
                return $payload->type === 'delegation.task_failed'
                    && $payload->severity === SystemNotificationPayload::SEVERITY_WARNING
                    && $payload->userId === $this->user->id
                    && str_contains($payload->title, $task->name);
            });

        $listener = new SendDelegationTaskFailedNotification($dispatcher);
        $listener->handle(new DelegationTaskVerified($task, $attempt, false, 2));
    }

    public function test_skips_on_task_verification_success(): void
    {
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
        ]);

        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile->id,
        ]);

        $dispatcher = Mockery::mock(SystemNotificationDispatcher::class);
        $dispatcher->shouldNotReceive('dispatch');

        $listener = new SendDelegationTaskFailedNotification($dispatcher);
        $listener->handle(new DelegationTaskVerified($task, $attempt, true));
    }
}
