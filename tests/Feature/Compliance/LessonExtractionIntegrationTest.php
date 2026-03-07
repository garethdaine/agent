<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Events\AgentJobRunFinished;
use App\Jobs\Compliance\LessonExtractionJob;
use App\Listeners\Compliance\LessonExtractionListener;
use App\Models\AgentJobRun;
use App\Support\Compliance\LessonsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LessonExtractionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $lessonsPath;

    private string $projectDir;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('compliance.lessons_enabled', true);

        $this->projectDir = sys_get_temp_dir().'/lesson-extraction-test-'.uniqid();
        mkdir($this->projectDir.'/tasks', 0755, true);
        $this->lessonsPath = $this->projectDir.'/tasks/lessons.md';
    }

    protected function tearDown(): void
    {
        if (File::exists($this->lessonsPath)) {
            File::delete($this->lessonsPath);
        }
        if (File::isDirectory($this->projectDir.'/tasks')) {
            File::deleteDirectory($this->projectDir.'/tasks');
        }
        if (File::isDirectory($this->projectDir)) {
            File::deleteDirectory($this->projectDir);
        }

        parent::tearDown();
    }

    public function test_end_to_end_failed_run_creates_lesson_entry(): void
    {
        $run = AgentJobRun::factory()->failed()->create([
            'metadata_json' => [
                'failure_mode_hint' => [
                    'suggested_lesson' => 'Ensure API tokens are refreshed before long-running tasks.',
                ],
            ],
        ]);

        $run->loadMissing('job');

        $job = new LessonExtractionJob(
            lessonContent: 'Ensure API tokens are refreshed before long-running tasks.',
            source: 'auto_failure_extraction',
            projectDirectory: $this->projectDir,
            context: [
                'task_title' => $run->job->name ?? 'Unknown Job',
                'task_category' => $run->job->task_category?->value ?? 'unknown',
                'runner_type' => $run->job->runner_type ?? 'unknown',
                'run_id' => $run->id,
                'error_code' => $run->error_code,
                'status' => AgentJobRun::STATUS_FAILED,
            ],
        );

        $job->handle(app(LessonsManager::class));

        $this->assertFileExists($this->lessonsPath);
        $content = File::get($this->lessonsPath);
        $this->assertStringContainsString('Ensure API tokens are refreshed before long-running tasks.', $content);
        $this->assertStringContainsString('auto_failure_extraction', $content);
    }

    public function test_no_lesson_when_flag_disabled(): void
    {
        config()->set('compliance.lessons_enabled', false);

        $job = new LessonExtractionJob(
            lessonContent: 'This should not be written.',
            source: 'test',
            projectDirectory: $this->projectDir,
        );

        $job->handle(app(LessonsManager::class));

        $this->assertFileDoesNotExist($this->lessonsPath);
    }

    public function test_multiple_failures_append_multiple_entries(): void
    {
        $lessons = app(LessonsManager::class);

        $job1 = new LessonExtractionJob(
            lessonContent: 'First failure: timeout during build.',
            source: 'auto_failure_extraction',
            projectDirectory: $this->projectDir,
            context: ['task_title' => 'Build A'],
        );

        $job2 = new LessonExtractionJob(
            lessonContent: 'Second failure: missing dependency.',
            source: 'code_analysis_task_failure',
            projectDirectory: $this->projectDir,
            context: ['task_title' => 'Analysis B'],
        );

        $job1->handle($lessons);
        $job2->handle($lessons);

        $this->assertFileExists($this->lessonsPath);
        $content = File::get($this->lessonsPath);
        $this->assertStringContainsString('First failure: timeout during build.', $content);
        $this->assertStringContainsString('Second failure: missing dependency.', $content);
        $this->assertStringContainsString('auto_failure_extraction', $content);
        $this->assertStringContainsString('code_analysis_task_failure', $content);
    }

    public function test_lesson_content_matches_suggested_lesson_from_failure_hint(): void
    {
        $suggestedLesson = 'Use exponential backoff when retrying external API calls to avoid rate limiting.';

        $job = new LessonExtractionJob(
            lessonContent: $suggestedLesson,
            source: 'auto_failure_extraction',
            projectDirectory: $this->projectDir,
            context: [
                'task_title' => 'API Sync Job',
                'task_category' => 'sync',
                'runner_type' => 'claude',
            ],
        );

        $job->handle(app(LessonsManager::class));

        $content = File::get($this->lessonsPath);
        $this->assertStringContainsString($suggestedLesson, $content);
        $this->assertStringContainsString('API Sync Job', $content);
        $this->assertStringContainsString('claude', $content);
    }
}
