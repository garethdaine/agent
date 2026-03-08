<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners\Messenger;

use App\DTOs\Messenger\SystemNotificationPayload;
use App\Events\DelegationGraphCompleted;
use App\Listeners\Messenger\SendRitualRunCompletedNotification;
use App\Models\DelegationGraph;
use App\Models\OrgRitualRun;
use App\Models\OrgRitualTemplate;
use App\Models\User;
use App\Services\Messenger\SystemNotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendRitualRunCompletedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private OrgRitualTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->template = OrgRitualTemplate::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    public function test_sends_notification_on_ritual_linked_graph_completion(): void
    {
        $graph = DelegationGraph::factory()->create([
            'user_id' => $this->user->id,
            'status' => DelegationGraph::STATUS_SUCCEEDED,
        ]);

        OrgRitualRun::factory()->running()->create([
            'ritual_template_id' => $this->template->id,
            'user_id' => $this->user->id,
            'delegation_graph_id' => $graph->id,
        ]);

        $dispatcher = Mockery::mock(SystemNotificationDispatcher::class);
        $dispatcher->shouldReceive('shouldNotify')->andReturn(true);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (SystemNotificationPayload $payload) {
                return $payload->type === 'ritual.completed'
                    && $payload->severity === SystemNotificationPayload::SEVERITY_INFO
                    && $payload->userId === $this->user->id
                    && str_contains($payload->title, 'succeeded');
            });

        $listener = new SendRitualRunCompletedNotification($dispatcher);
        $listener->handle(new DelegationGraphCompleted($graph, DelegationGraph::STATUS_SUCCEEDED));
    }

    public function test_skips_non_ritual_graph_completions(): void
    {
        $graph = DelegationGraph::factory()->create([
            'user_id' => $this->user->id,
            'status' => DelegationGraph::STATUS_SUCCEEDED,
        ]);

        // No OrgRitualRun linked to this graph

        $dispatcher = Mockery::mock(SystemNotificationDispatcher::class);
        $dispatcher->shouldNotReceive('dispatch');

        $listener = new SendRitualRunCompletedNotification($dispatcher);
        $listener->handle(new DelegationGraphCompleted($graph, DelegationGraph::STATUS_SUCCEEDED));
    }

    public function test_maps_failed_status_to_error_severity(): void
    {
        $graph = DelegationGraph::factory()->create([
            'user_id' => $this->user->id,
            'status' => DelegationGraph::STATUS_FAILED,
        ]);

        OrgRitualRun::factory()->running()->create([
            'ritual_template_id' => $this->template->id,
            'user_id' => $this->user->id,
            'delegation_graph_id' => $graph->id,
        ]);

        $dispatcher = Mockery::mock(SystemNotificationDispatcher::class);
        $dispatcher->shouldReceive('shouldNotify')->andReturn(true);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (SystemNotificationPayload $payload) {
                return $payload->severity === SystemNotificationPayload::SEVERITY_ERROR
                    && str_contains($payload->title, 'failed');
            });

        $listener = new SendRitualRunCompletedNotification($dispatcher);
        $listener->handle(new DelegationGraphCompleted($graph, DelegationGraph::STATUS_FAILED));
    }

    public function test_maps_partial_status_to_warning_severity(): void
    {
        $graph = DelegationGraph::factory()->create([
            'user_id' => $this->user->id,
            'status' => DelegationGraph::STATUS_PARTIAL,
        ]);

        OrgRitualRun::factory()->running()->create([
            'ritual_template_id' => $this->template->id,
            'user_id' => $this->user->id,
            'delegation_graph_id' => $graph->id,
        ]);

        $dispatcher = Mockery::mock(SystemNotificationDispatcher::class);
        $dispatcher->shouldReceive('shouldNotify')->andReturn(true);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (SystemNotificationPayload $payload) {
                return $payload->severity === SystemNotificationPayload::SEVERITY_WARNING;
            });

        $listener = new SendRitualRunCompletedNotification($dispatcher);
        $listener->handle(new DelegationGraphCompleted($graph, DelegationGraph::STATUS_PARTIAL));
    }

    public function test_respects_template_notification_level(): void
    {
        $template = OrgRitualTemplate::factory()->create([
            'user_id' => $this->user->id,
            'notification_level' => 'escalations_only',
        ]);

        $graph = DelegationGraph::factory()->create([
            'user_id' => $this->user->id,
            'status' => DelegationGraph::STATUS_SUCCEEDED,
        ]);

        OrgRitualRun::factory()->running()->create([
            'ritual_template_id' => $template->id,
            'user_id' => $this->user->id,
            'delegation_graph_id' => $graph->id,
        ]);

        $dispatcher = Mockery::mock(SystemNotificationDispatcher::class);
        // escalations_only should not allow info severity
        $dispatcher->shouldReceive('shouldNotify')
            ->with('escalations_only', SystemNotificationPayload::SEVERITY_INFO)
            ->andReturn(false);
        $dispatcher->shouldNotReceive('dispatch');

        $listener = new SendRitualRunCompletedNotification($dispatcher);
        $listener->handle(new DelegationGraphCompleted($graph, DelegationGraph::STATUS_SUCCEEDED));
    }
}
