<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners\Compliance;

use App\Events\AgentJobRunFinished;
use App\Jobs\Compliance\LessonExtractionJob;
use App\Listeners\Compliance\LessonExtractionListener;
use App\Models\AgentJobRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LessonExtractionListenerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('compliance.lessons_enabled', true);
        Queue::fake([LessonExtractionJob::class]);
    }

    public function test_skips_when_flag_disabled(): void
    {
        config()->set('compliance.lessons_enabled', false);

        $run = AgentJobRun::factory()->failed()->create();

        $listener = new LessonExtractionListener;
        $listener->handle(new AgentJobRunFinished($run, AgentJobRun::STATUS_FAILED));

        Queue::assertNotPushed(LessonExtractionJob::class);
    }

    public function test_skips_for_succeeded_status(): void
    {
        $run = AgentJobRun::factory()->succeeded()->create();

        $listener = new LessonExtractionListener;
        $listener->handle(new AgentJobRunFinished($run, AgentJobRun::STATUS_SUCCEEDED));

        Queue::assertNotPushed(LessonExtractionJob::class);
    }

    public function test_skips_for_timed_out_status(): void
    {
        $run = AgentJobRun::factory()->create(['status' => AgentJobRun::STATUS_TIMED_OUT]);

        $listener = new LessonExtractionListener;
        $listener->handle(new AgentJobRunFinished($run, AgentJobRun::STATUS_TIMED_OUT));

        Queue::assertNotPushed(LessonExtractionJob::class);
    }

    public function test_skips_for_killed_status(): void
    {
        $run = AgentJobRun::factory()->create(['status' => AgentJobRun::STATUS_KILLED]);

        $listener = new LessonExtractionListener;
        $listener->handle(new AgentJobRunFinished($run, AgentJobRun::STATUS_KILLED));

        Queue::assertNotPushed(LessonExtractionJob::class);
    }

    public function test_dispatches_job_with_suggested_lesson_from_metadata(): void
    {
        $run = AgentJobRun::factory()->failed()->create([
            'metadata_json' => [
                'failure_mode_hint' => [
                    'suggested_lesson' => 'Always validate config before running CLI processes.',
                ],
            ],
        ]);

        $listener = new LessonExtractionListener;
        $listener->handle(new AgentJobRunFinished($run, AgentJobRun::STATUS_FAILED));

        Queue::assertPushed(LessonExtractionJob::class, function (LessonExtractionJob $job) {
            return $job->lessonContent === 'Always validate config before running CLI processes.'
                && $job->source === 'auto_failure_extraction';
        });
    }

    public function test_dispatches_job_with_error_code_fallback(): void
    {
        $run = AgentJobRun::factory()->failed()->create([
            'error_code' => 'TIMEOUT_EXCEEDED',
            'error_summary' => 'Process timed out after 600 seconds.',
            'metadata_json' => null,
        ]);

        $listener = new LessonExtractionListener;
        $listener->handle(new AgentJobRunFinished($run, AgentJobRun::STATUS_FAILED));

        Queue::assertPushed(LessonExtractionJob::class, function (LessonExtractionJob $job) {
            return str_contains($job->lessonContent, 'TIMEOUT_EXCEEDED')
                && str_contains($job->lessonContent, 'Process timed out after 600 seconds.');
        });
    }

    public function test_skips_when_no_error_info_available(): void
    {
        $run = AgentJobRun::factory()->failed()->create([
            'error_code' => null,
            'error_summary' => null,
            'metadata_json' => null,
        ]);

        $listener = new LessonExtractionListener;
        $listener->handle(new AgentJobRunFinished($run, AgentJobRun::STATUS_FAILED));

        Queue::assertNotPushed(LessonExtractionJob::class);
    }

    public function test_catches_dispatch_exceptions_without_propagating(): void
    {
        // Reset Queue::fake so dispatch actually executes
        Queue::swap(app('queue'));

        // This should NOT throw - the listener catches exceptions
        $run = AgentJobRun::factory()->failed()->create([
            'error_code' => 'TEST_ERROR',
            'error_summary' => 'Test error message.',
        ]);

        $listener = new LessonExtractionListener;

        // The listener wraps dispatch in try/catch, so even if dispatch fails
        // under test conditions, it should not propagate
        $listener->handle(new AgentJobRunFinished($run, AgentJobRun::STATUS_FAILED));

        $this->assertTrue(true, 'Listener did not throw');
    }

    public function test_context_includes_job_details(): void
    {
        $run = AgentJobRun::factory()->failed()->create([
            'error_code' => 'SOME_ERROR',
            'error_summary' => 'Detailed error description.',
        ]);

        $listener = new LessonExtractionListener;
        $listener->handle(new AgentJobRunFinished($run, AgentJobRun::STATUS_FAILED));

        Queue::assertPushed(LessonExtractionJob::class, function (LessonExtractionJob $job) use ($run) {
            return $job->context['run_id'] === $run->id
                && $job->context['error_code'] === 'SOME_ERROR'
                && $job->context['status'] === AgentJobRun::STATUS_FAILED
                && isset($job->context['task_title'])
                && isset($job->context['task_category'])
                && isset($job->context['runner_type']);
        });
    }
}
