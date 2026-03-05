<?php

namespace Tests\Feature\Org;

use App\Jobs\Org\OrgDispatchDueRitualsJob;
use App\Jobs\Org\OrgExecuteRitualJob;
use App\Models\OrgRitualTemplate;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrgRitualSchedulerTest extends TestCase
{
    public function test_dispatch_job_queues_due_rituals(): void
    {
        Queue::fake();
        config(['agent.org.enabled' => true]);

        // Create a ritual due now (cron: every minute)
        $template = OrgRitualTemplate::factory()->create([
            'cron_expression' => '* * * * *',
            'is_paused' => false,
            'archived_at' => null,
        ]);

        (new OrgDispatchDueRitualsJob)->handle();

        Queue::assertPushed(OrgExecuteRitualJob::class, function ($job) use ($template) {
            return $job->template->id === $template->id;
        });
    }

    public function test_paused_rituals_not_dispatched(): void
    {
        Queue::fake();
        config(['agent.org.enabled' => true]);

        $template = OrgRitualTemplate::factory()->create([
            'cron_expression' => '* * * * *',
            'is_paused' => true,
        ]);

        (new OrgDispatchDueRitualsJob)->handle();

        Queue::assertNotPushed(OrgExecuteRitualJob::class, function ($job) use ($template) {
            return $job->template->id === $template->id;
        });
    }

    public function test_archived_rituals_not_dispatched(): void
    {
        Queue::fake();
        config(['agent.org.enabled' => true]);

        $template = OrgRitualTemplate::factory()->archived()->create([
            'cron_expression' => '* * * * *',
            'is_paused' => false,
        ]);

        (new OrgDispatchDueRitualsJob)->handle();

        Queue::assertNotPushed(OrgExecuteRitualJob::class, function ($job) use ($template) {
            return $job->template->id === $template->id;
        });
    }

    public function test_execute_job_creates_run_and_delegation_graph(): void
    {
        config(['agent.org.enabled' => true]);

        $template = OrgRitualTemplate::factory()->create();

        $job = new OrgExecuteRitualJob($template);
        app()->call([$job, 'handle']);

        $this->assertDatabaseHas('org_ritual_runs', [
            'ritual_template_id' => $template->id,
        ]);
    }

    public function test_dispatch_job_skips_when_org_disabled(): void
    {
        Queue::fake();
        config(['agent.org.enabled' => false]);

        OrgRitualTemplate::factory()->create([
            'cron_expression' => '* * * * *',
            'is_paused' => false,
            'archived_at' => null,
        ]);

        (new OrgDispatchDueRitualsJob)->handle();

        Queue::assertNotPushed(OrgExecuteRitualJob::class);
    }
}
