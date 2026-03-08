<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Org;

use App\Models\OrgRitualRun;
use App\Models\OrgRitualTemplate;
use App\Models\User;
use App\Support\Org\OrgRitualRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgRitualRunServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrgRitualRunService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrgRitualRunService::class);
    }

    public function test_create_run_sets_queued_state(): void
    {
        $template = OrgRitualTemplate::factory()->create();

        $run = $this->service->createRun($template);

        $this->assertEquals(OrgRitualRun::STATE_QUEUED, $run->state);
        $this->assertNotNull($run->correlation_id);
    }

    public function test_create_run_associates_template_and_user(): void
    {
        $user = User::factory()->create();
        $template = OrgRitualTemplate::factory()->for($user)->create();

        $run = $this->service->createRun($template);

        $this->assertEquals($template->id, $run->ritual_template_id);
        $this->assertEquals($user->id, $run->user_id);
    }

    public function test_complete_phase_persists_output(): void
    {
        $run = OrgRitualRun::factory()->create(['phase_outputs' => []]);

        $this->service->completePhase($run, 'phase1', ['result' => 'success']);

        $this->assertEquals(
            ['phase1' => ['result' => 'success']],
            $run->fresh()->phase_outputs
        );
    }

    public function test_complete_phase_merges_with_existing_outputs(): void
    {
        $run = OrgRitualRun::factory()->create([
            'phase_outputs' => ['phase1' => ['result' => 'done']],
        ]);

        $this->service->completePhase($run, 'phase2', ['value' => 42]);

        $this->assertEquals(
            [
                'phase1' => ['result' => 'done'],
                'phase2' => ['value' => 42],
            ],
            $run->fresh()->phase_outputs
        );
    }

    public function test_retry_preserves_successful_phase_outputs(): void
    {
        $template = OrgRitualTemplate::factory()->create();
        $originalRun = OrgRitualRun::factory()->for($template, 'template')->create([
            'state' => OrgRitualRun::STATE_PARTIAL,
            'phase_outputs' => [
                'phase1' => ['status' => 'completed'],
                'phase2' => null, // Failed
            ],
        ]);

        $newRun = $this->service->retryFailedPhases($originalRun);

        $this->assertNotEquals($originalRun->id, $newRun->id);
        $this->assertEquals(
            ['phase1' => ['status' => 'completed']],
            $newRun->phase_outputs
        );
    }

    public function test_retry_creates_new_run_in_queued_state(): void
    {
        $template = OrgRitualTemplate::factory()->create();
        $originalRun = OrgRitualRun::factory()->for($template, 'template')->create([
            'state' => OrgRitualRun::STATE_PARTIAL,
            'phase_outputs' => ['phase1' => null],
        ]);

        $newRun = $this->service->retryFailedPhases($originalRun);

        $this->assertEquals(OrgRitualRun::STATE_QUEUED, $newRun->state);
        $this->assertNotNull($newRun->correlation_id);
    }

    public function test_retry_preserves_only_non_null_outputs(): void
    {
        $template = OrgRitualTemplate::factory()->create();
        $originalRun = OrgRitualRun::factory()->for($template, 'template')->create([
            'state' => OrgRitualRun::STATE_PARTIAL,
            'phase_outputs' => [
                'phase1' => ['data' => 'good'],
                'phase2' => ['data' => 'also_good'],
                'phase3' => null,
                'phase4' => null,
            ],
        ]);

        $newRun = $this->service->retryFailedPhases($originalRun);

        $this->assertEquals(
            [
                'phase1' => ['data' => 'good'],
                'phase2' => ['data' => 'also_good'],
            ],
            $newRun->phase_outputs
        );
    }

    public function test_mark_running_sets_state_and_started_at(): void
    {
        $run = OrgRitualRun::factory()->queued()->create();

        $this->service->markRunning($run);

        $run->refresh();
        $this->assertEquals(OrgRitualRun::STATE_RUNNING, $run->state);
        $this->assertNotNull($run->started_at);
    }

    public function test_mark_succeeded_sets_terminal_state(): void
    {
        $run = OrgRitualRun::factory()->running()->create();

        $this->service->markSucceeded($run);

        $run->refresh();
        $this->assertEquals(OrgRitualRun::STATE_SUCCEEDED, $run->state);
        $this->assertNotNull($run->completed_at);
    }

    public function test_mark_failed_sets_terminal_state(): void
    {
        $run = OrgRitualRun::factory()->running()->create();

        $this->service->markFailed($run);

        $run->refresh();
        $this->assertEquals(OrgRitualRun::STATE_FAILED, $run->state);
        $this->assertNotNull($run->completed_at);
    }

    public function test_mark_partial_sets_terminal_state(): void
    {
        $run = OrgRitualRun::factory()->running()->create();

        $this->service->markPartial($run);

        $run->refresh();
        $this->assertEquals(OrgRitualRun::STATE_PARTIAL, $run->state);
        $this->assertNotNull($run->completed_at);
    }
}
