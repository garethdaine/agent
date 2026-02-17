<?php

namespace Tests\Unit;

use App\Jobs\ExecuteInterrogationSummaryJob;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Interrogation\AdapterFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecuteInterrogationSummaryJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_job_skips_when_session_is_not_in_summary_phase(): void
    {
        $user = User::factory()->create();
        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Interrogation in progress',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_INTERROGATING,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
            'summary_json' => ['summary_markdown' => 'Existing'],
        ]);

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')->never();

        $job = new ExecuteInterrogationSummaryJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $session->refresh();
        $this->assertSame(InterrogationSession::PHASE_INTERROGATION, (int) $session->phase);
        $this->assertSame(InterrogationSession::STATUS_INTERROGATING, (string) $session->status);
        $this->assertFalse(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_SUMMARY)
                ->exists()
        );
    }

    public function test_summary_job_skips_when_summary_open_question_queue_is_active(): void
    {
        $user = User::factory()->create();
        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Open question queue active',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'phase' => InterrogationSession::PHASE_SUMMARY,
            'summary_json' => ['summary_markdown' => 'Existing'],
            'metadata_json' => [
                'summary_open_question_queue' => [
                    'active' => true,
                    'pending_questions' => ['Question A'],
                ],
            ],
        ]);

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')->never();

        $job = new ExecuteInterrogationSummaryJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $this->assertFalse(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_SUMMARY)
                ->exists()
        );
    }
}
